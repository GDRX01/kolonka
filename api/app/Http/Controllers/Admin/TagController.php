<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TagController extends Controller
{
    /**
     * GET /api/admin/tags?q=lar&status=pending|approved&limit=8
     *   q      - search in the tag name ("#" is ignored)
     *   status - only tags waiting for moderation (pending) or only approved; omit for all
     *   limit  - the first N tags ("show all" asks for 500)
     */
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 8), 500));

        $query = Tag::with('creator:id,name')->withCount('posts');

        if ($text = trim(ltrim(trim((string) $request->query('q', '')), '#'))) {
            $query->where('name', 'like', '%'.addcslashes(mb_strtolower($text), '%_\\').'%');
        }
        if (in_array($status = $request->query('status'), [Tag::STATUS_PENDING, Tag::STATUS_APPROVED], true)) {
            $query->where('status', $status);
        }

        $matched = (clone $query)->count();

        // tags waiting for moderation first, then the most used ones
        $tags = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('posts_count')->orderBy('name')
            ->limit($limit)->get();

        return response()->json([
            'total' => Tag::count(),
            'pending_total' => Tag::where('status', Tag::STATUS_PENDING)->count(),
            'matched' => $matched,
            'data' => $tags->map(fn ($t) => $this->present($t)),
        ]);
    }

    /** An administrator adds a tag: it is approved at once. */
    public function store(Request $request): JsonResponse
    {
        [$name, $slug] = $this->nameAndSlug($request);

        if (Tag::where('slug', $slug)->orWhere('name', $name)->exists()) {
            $this->fail('Такой тег уже существует (возможно, он ждёт проверки в списке «На модерации»).');
        }

        $tag = Tag::create([
            'name' => $name, 'slug' => $slug,
            'status' => Tag::STATUS_APPROVED, 'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($tag->loadCount('posts'))], 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        [$name, $slug] = $this->nameAndSlug($request);

        $exists = Tag::where('id', '!=', $tag->id)->where(fn ($q) => $q->where('slug', $slug)->orWhere('name', $name))->exists();
        if ($exists) {
            $this->fail('Такой тег уже существует.');
        }

        $tag->update(['name' => $name, 'slug' => $slug]);

        return response()->json(['data' => $this->present($tag->loadCount('posts'))]);
    }

    /** PATCH /api/admin/tags/{tag}/approve - the tag becomes visible to everybody. */
    public function approve(Tag $tag): JsonResponse
    {
        $tag->update(['status' => Tag::STATUS_APPROVED]);

        return response()->json(['data' => $this->present($tag->loadCount('posts'))]);
    }

    /** Delete = reject for a pending tag: it disappears from every post. */
    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();   // links in post_tag are removed by ON DELETE CASCADE

        return response()->json(['message' => 'Тег удалён.']);
    }

    private function present(Tag $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'status' => $t->status,
            'posts_count' => $t->posts_count ?? 0,
            'created_by' => $t->relationLoaded('creator') && $t->creator
                ? ['id' => $t->creator->id, 'name' => $t->creator->name]
                : null,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function nameAndSlug(Request $request): array
    {
        $name = Tag::normalize((string) $request->input('name', ''));
        $slug = Str::slug($name);

        if ($name === '' || $slug === '' || mb_strlen($name) > 30) {
            $this->fail('Введите корректное название тега (до 30 символов).');
        }

        return [$name, $slug];
    }

    /** Answers 422 with the message under the "name" field. */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['name' => [$message]]);
    }
}
