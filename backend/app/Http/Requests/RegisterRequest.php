<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "Anna@Mail.com " and "anna@mail.com" are the same e-mail: store and compare it in lower case. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            // The name may be written in any language (Russian names are fine)
            'name' => ['required', 'string', 'min:2', 'max:60'],

            // "bail" = stop at the first failed rule, so the user sees ONE clear message.
            // "ascii" refuses Cyrillic (почта@почта.ру) and any other non-ASCII characters.
            'email' => ['bail', 'required', 'string', 'max:120', 'ascii', 'email', 'unique:users,email'],

            // 8+ characters, Latin only, one uppercase letter, one digit, one special character
            'password' => ['bail', 'required', 'string', 'min:8', 'max:100', new StrongPassword, 'confirmed'],
        ];
    }
}
