<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBlogData;
use Tests\TestCase;

/** Registration restrictions: Latin-only e-mail and password, password policy, unique e-mail. */
class PasswordPolicyTest extends TestCase
{
    use MakesBlogData, RefreshDatabase;

    private function register(array $over = [])
    {
        $payload = array_merge([
            'name' => 'Anna', 'email' => 'anna@example.test', 'password' => 'Secret123!',
        ], $over);
        $payload['password_confirmation'] = $over['password_confirmation'] ?? $payload['password'];

        return $this->postJson('/api/register', $payload);
    }

    // ---------------- registration ----------------

    public function test_cyrillic_email_and_password_are_rejected(): void
    {
        $this->register(['email' => 'почта@почта.ру', 'password' => 'почтапочтапочта'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_error_messages_mention_cyrillic(): void
    {
        $res = $this->register(['email' => 'почта@почта.ру', 'password' => 'почтапочтапочта']);

        $this->assertStringContainsString('кириллица', $res->json('errors.email.0'));
        $this->assertStringContainsString('кириллицы', $res->json('errors.password.0'));
    }

    public function test_only_email_and_password_are_restricted_the_name_may_be_russian(): void
    {
        $this->register(['name' => 'Анна Смирнова'])->assertCreated();
    }

    public function test_password_policy_is_enforced(): void
    {
        $weak = [
            'secret123!' => 'заглавной',     // no uppercase letter
            'Secretabc!' => 'цифры',         // no digit
            'Secret1234' => 'спецсимвола',   // no special character
            'Sec1!'      => 'не менее 8',    // too short
            'Secret 123!' => 'без пробелов', // contains a space
        ];

        foreach ($weak as $password => $messagePart) {
            $res = $this->register(['password' => $password])->assertStatus(422)->assertJsonValidationErrors(['password']);
            $this->assertStringContainsString($messagePart, $res->json('errors.password.0'), "password: $password");
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_password_that_follows_the_policy_is_accepted(): void
    {
        $this->register(['password' => 'Aa1!aaaa'])->assertCreated();
    }

    public function test_email_must_be_unique_ignoring_letter_case(): void
    {
        $this->makeUser(['email' => 'taken@example.test']);

        $this->register(['email' => 'TAKEN@Example.test'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_the_email_is_stored_in_lower_case(): void
    {
        $this->register(['email' => 'Anna@Example.TEST'])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'anna@example.test']);
    }

    public function test_malformed_email_is_rejected(): void
    {
        $this->register(['email' => 'not an email'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
