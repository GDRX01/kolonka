<?php

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesBlogData;
use Tests\TestCase;

/** Tags suggested by ordinary users wait for the administrator; admins' tags are approved at once. */
class TagModerationTest extends TestCase
{
    use MakesBlogData, RefreshDatabase;

    private function payload(array $tags): array
    {
        return [
            'title' => 'Статья про теги', 'body' => 'Достаточно длинный основной текст статьи.',
            'category_id' => $this->makeCategory()->id, 'tags' => $tags, 'status' => 'published',
        ];
    }

    private function approvedTag(string $name): Tag
    {
        return Tag::create(['name' => $name, 'slug' => $name, 'status' => Tag::STATUS_APPROVED]);
    }

    /** Creates a post by a regular user with a brand-new tag; returns [post id, author]. */
    private function postWithNewTag(string $tag = 'brandnew'): array
    {
        $author = $this->makeUser();
        Sanctum::actingAs($author);
        $id = $this->postJson('/api/posts', $this->payload([$tag, 'laravel']))->assertCreated()->json('data.id');

        return [$id, $author];
    }

    public function test_a_new_tag_from_a_regular_user_is_pending(): void
    {
        $this->approvedTag('laravel');
        [, $author] = $this->postWithNewTag();

        $new = Tag::where('slug', 'brandnew')->first();
        $this->assertSame(Tag::STATUS_PENDING, $new->status);
        $this->assertSame($author->id, $new->created_by);
        $this->assertSame(Tag::STATUS_APPROVED, Tag::where('slug', 'laravel')->first()->status);   // existing tags are untouched
    }

    public function test_a_new_tag_from_an_administrator_is_approved(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/posts', $this->payload(['adminstag']))->assertCreated();

        $this->assertSame(Tag::STATUS_APPROVED, Tag::where('slug', 'adminstag')->first()->status);
    }

    public function test_pending_tags_are_hidden_from_the_public(): void
    {
        $this->approvedTag('laravel');
        [$id] = $this->postWithNewTag();
        $names = fn ($res) => collect($res->json('data.tags'))->pluck('name')->all();

        // the author sees both (the new one is marked as pending)
        $mine = $this->getJson("/api/posts/$id");
        $this->assertEqualsCanonicalizing(['brandnew', 'laravel'], $names($mine));
        $this->assertTrue(collect($mine->json('data.tags'))->firstWhere('name', 'brandnew')['pending']);

        // an administrator sees both
        Sanctum::actingAs($this->makeAdmin());
        $this->assertEqualsCanonicalizing(['brandnew', 'laravel'], $names($this->getJson("/api/posts/$id")));

        // another user and a guest see only the approved tag
        Sanctum::actingAs($this->makeUser());
        $this->assertSame(['laravel'], $names($this->getJson("/api/posts/$id")));
        $this->app['auth']->forgetGuards();
        $this->assertSame(['laravel'], $names($this->getJson("/api/posts/$id")));

        // not in the public tag lists, not usable as a filter
        $this->assertNotContains('brandnew', collect($this->getJson('/api/tags')->json('data'))->pluck('slug')->all());
        $this->assertNotContains('brandnew', collect($this->getJson('/api/home')->json('tags'))->pluck('slug')->all());
        $this->getJson('/api/posts?tags=brandnew')->assertJsonCount(0, 'data');
        $this->getJson('/api/posts?tags=laravel')->assertJsonCount(1, 'data');
    }

    public function test_only_administrators_can_open_the_moderation_queue(): void
    {
        $this->postWithNewTag();

        Sanctum::actingAs($this->makeUser());
        $this->getJson('/api/admin/tags')->assertForbidden();
        $this->patchJson('/api/admin/tags/1/approve')->assertForbidden();
    }

    public function test_admin_sees_pending_tags_first_and_can_search_and_filter(): void
    {
        $this->approvedTag('laravel');
        $this->approvedTag('lara-extra');
        $this->postWithNewTag('laracasts');
        Sanctum::actingAs($this->makeAdmin());

        $all = $this->getJson('/api/admin/tags')->assertOk();
        $all->assertJsonPath('pending_total', 1)->assertJsonPath('total', 3);
        $this->assertSame('laracasts', $all->json('data.0.name'));            // pending first
        $this->assertSame('pending', $all->json('data.0.status'));
        $this->assertNotNull($all->json('data.0.created_by.name'));           // who suggested it

        $this->getJson('/api/admin/tags?status=pending')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/tags?q=lara')->assertJsonPath('matched', 3);
        $this->getJson('/api/admin/tags?q=%23laracasts')->assertJsonCount(1, 'data');   // "#" is ignored
        $this->getJson('/api/admin/tags?q=zzz')->assertJsonCount(0, 'data');
    }

    public function test_approving_makes_the_tag_visible(): void
    {
        $this->approvedTag('laravel');
        [$id] = $this->postWithNewTag();
        $tag = Tag::where('slug', 'brandnew')->first();

        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson("/api/admin/tags/{$tag->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        $this->app['auth']->forgetGuards();
        $names = collect($this->getJson("/api/posts/$id")->json('data.tags'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['brandnew', 'laravel'], $names);
        $this->getJson('/api/posts?tags=brandnew')->assertJsonCount(1, 'data');
    }

    public function test_rejecting_removes_the_tag_from_the_posts(): void
    {
        [$id] = $this->postWithNewTag();
        $tag = Tag::where('slug', 'brandnew')->first();

        Sanctum::actingAs($this->makeAdmin());
        $this->deleteJson("/api/admin/tags/{$tag->id}")->assertOk();

        $this->assertModelMissing($tag);
        $this->assertDatabaseMissing('post_tag', ['tag_id' => $tag->id]);
        $this->assertNotContains('brandnew', collect($this->getJson("/api/posts/$id")->json('data.tags'))->pluck('name')->all());
    }

    public function test_an_administrator_can_add_an_approved_tag_and_cannot_duplicate_one(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/tags', ['name' => '#Docker'])->assertCreated()->assertJsonPath('data.status', 'approved');
        $this->postJson('/api/admin/tags', ['name' => 'docker'])->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->postJson('/api/admin/tags', ['name' => '   '])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_an_ordinary_user_cannot_pile_up_pending_tags(): void
    {
        $user = $this->makeUser();
        for ($i = 1; $i <= Tag::MAX_PENDING_PER_USER; $i++) {
            Tag::create(['name' => "t$i", 'slug' => "t$i", 'status' => Tag::STATUS_PENDING, 'created_by' => $user->id]);
        }
        Sanctum::actingAs($user);

        $this->postJson('/api/posts', $this->payload(['one-more-new-tag']))
            ->assertStatus(422)->assertJsonValidationErrors(['tags']);
        $this->assertDatabaseCount('posts', 0);                                   // nothing was saved

        $this->postJson('/api/posts', $this->payload(['t1', 't2']))->assertCreated();   // existing tags are still fine
    }

    public function test_a_pending_tag_can_be_reused_by_another_author_without_being_approved(): void
    {
        $this->postWithNewTag('sharedtag');
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/posts', $this->payload(['sharedtag']))->assertCreated();

        $this->assertSame(1, Tag::where('slug', 'sharedtag')->count());
        $this->assertSame(Tag::STATUS_PENDING, Tag::where('slug', 'sharedtag')->first()->status);
    }

    public function test_the_dashboard_counts_pending_tags(): void
    {
        $this->postWithNewTag();
        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/admin/stats')->assertOk()->assertJsonPath('pending_tags', 1);
    }
}
