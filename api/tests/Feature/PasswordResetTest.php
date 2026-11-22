<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\MakesBlogData;
use Tests\TestCase;

/** "I forgot my password" (e-mail link) and "change my password" (logged in). */
class PasswordResetTest extends TestCase
{
    use MakesBlogData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();   // nothing is really sent; we only look at what WOULD be sent
        config(['kolonka.frontend_url' => 'http://localhost:5173']);
    }

    private function user(array $attrs = []): User
    {
        return $this->makeUser(array_merge(['email' => 'a@example.test', 'password' => 'OldPass1!'], $attrs));
    }

    private function forgot(string $email = 'a@example.test')
    {
        return $this->postJson('/api/password/forgot', ['email' => $email]);
    }

    /** The reset link that was "e-mailed" (taken from the fake mailbox). */
    private function sentLink(): array
    {
        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return ['url' => $url, 'token' => $query['token'], 'email' => $query['email']];
    }

    private function reset(array $over = [])
    {
        return $this->postJson('/api/password/reset', array_merge([
            'email' => 'a@example.test', 'token' => 'x', 'password' => 'NewPass2@',
        ], $over));
    }

    // ---------------- forgot: ask for the link ----------------

    public function test_a_reset_link_is_mailed_to_a_registered_address(): void
    {
        $this->user();

        $this->forgot()->assertOk();

        Mail::assertSent(PasswordResetMail::class, 1);
        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo('a@example.test'));

        $link = $this->sentLink();
        $this->assertStringStartsWith('http://localhost:5173/password/reset?', $link['url']);
        $this->assertSame('a@example.test', $link['email']);
    }

    public function test_unknown_address_gets_the_same_answer_and_no_mail(): void
    {
        $this->user();

        $known = $this->forgot()->assertOk()->json('message');
        $unknown = $this->forgot('nobody@example.test')->assertOk()->json('message');

        $this->assertSame($known, $unknown);          // nobody can find out who is registered
        Mail::assertSent(PasswordResetMail::class, 1); // only the first request produced an e-mail
    }

    public function test_blocked_users_get_no_mail(): void
    {
        $this->user(['is_blocked' => true]);

        $this->forgot()->assertOk();

        Mail::assertNothingSent();
    }

    public function test_the_address_is_matched_ignoring_letter_case(): void
    {
        $this->user();

        $this->forgot('A@EXAMPLE.TEST')->assertOk();

        Mail::assertSent(PasswordResetMail::class, 1);
    }

    public function test_forgot_requires_a_valid_email(): void
    {
        $this->forgot('not an email')->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // ---------------- reset: set the new password ----------------

    public function test_the_link_sets_a_new_password_without_the_old_one(): void
    {
        $user = $this->user();
        $user->createToken('phone');
        $this->forgot();
        $link = $this->sentLink();

        $this->reset(['token' => $link['token']])->assertOk();

        $this->assertTrue(Hash::check('NewPass2@', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);   // signed out everywhere

        $login = fn ($password) => $this->postJson('/api/login', ['email' => 'a@example.test', 'password' => $password]);
        $login('OldPass1!')->assertStatus(422);
        $login('NewPass2@')->assertOk();
    }

    public function test_a_link_works_only_once(): void
    {
        $this->user();
        $this->forgot();
        $token = $this->sentLink()['token'];

        $this->reset(['token' => $token])->assertOk();
        $this->reset(['token' => $token, 'password' => 'Another3#x'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_wrong_token_or_wrong_email_is_refused(): void
    {
        $this->user();
        $this->makeUser(['email' => 'b@example.test']);
        $this->forgot();
        $token = $this->sentLink()['token'];

        $this->reset(['token' => 'wrong-token'])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->reset(['token' => $token, 'email' => 'b@example.test'])->assertStatus(422);   // token belongs to another account
        $this->reset(['token' => $token, 'email' => 'nobody@example.test'])->assertStatus(422);
    }

    public function test_the_link_expires_after_an_hour(): void
    {
        $this->user();
        $this->forgot();
        $token = $this->sentLink()['token'];

        $this->travel(61)->minutes();

        $this->reset(['token' => $token])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_the_new_password_must_follow_the_policy(): void
    {
        $user = $this->user();
        $this->forgot();
        $token = $this->sentLink()['token'];

        foreach (['weakpass1', 'Weakpass!', 'Weakpass1', 'почтапочтапочта', 'Sh0rt!'] as $password) {
            $this->reset(['token' => $token, 'password' => $password])
                ->assertStatus(422)->assertJsonValidationErrors(['password']);
        }

        $this->assertTrue(Hash::check('OldPass1!', $user->fresh()->password));   // nothing changed
        $this->reset(['token' => $token])->assertOk();                           // and the link still works
    }

    public function test_reset_requires_all_fields(): void
    {
        $this->postJson('/api/password/reset', [])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    }

    // ---------------- logged in: current + new password ----------------

    private function changeOwn(string $token, array $over = [])
    {
        return $this->withToken($token)->putJson('/api/me/password', array_merge([
            'current_password' => 'OldPass1!', 'password' => 'NewPass2@',
        ], $over));
    }

    public function test_logged_in_user_changes_the_password_with_the_current_one(): void
    {
        $user = $this->user();
        $user->createToken('other-device');
        $token = $user->createToken('this-device')->plainTextToken;

        $this->changeOwn($token)->assertOk();

        $this->assertTrue(Hash::check('NewPass2@', $user->fresh()->password));
        $this->assertCount(1, $user->fresh()->tokens);                      // only the current session is kept
        $this->withToken($token)->getJson('/api/me')->assertOk();           // ... and it still works
    }

    public function test_wrong_current_password_is_refused(): void
    {
        $user = $this->user();
        $token = $user->createToken('t')->plainTextToken;

        $this->changeOwn($token, ['current_password' => 'Wrong1234!'])
            ->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('OldPass1!', $user->fresh()->password));
    }

    public function test_new_password_must_follow_the_policy_and_differ_from_the_current_one(): void
    {
        $token = $this->user()->createToken('t')->plainTextToken;

        $this->changeOwn($token, ['password' => 'weakpass'])->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->changeOwn($token, ['password' => 'почтапочтапочта'])->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->changeOwn($token, ['password' => 'OldPass1!'])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_changing_the_password_requires_login(): void
    {
        $this->putJson('/api/me/password', ['current_password' => 'a', 'password' => 'NewPass2@'])->assertUnauthorized();
    }
}
