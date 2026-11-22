<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Password policy (used when registering and when changing a password):
 *  - only printable ASCII without spaces: Latin letters, digits, special characters
 *    (so Cyrillic, other alphabets, emoji and spaces are refused);
 *  - at least one UPPERCASE Latin letter;
 *  - at least one digit;
 *  - at least one special character (! " # $ % & ' ( ) * + , - . / : ; < = > ? @ [ \ ] ^ _ ` { | } ~).
 * The minimum length (8) is checked by the separate "min:8" rule.
 */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (! preg_match('/^[\x21-\x7E]+$/', $password)) {
            $fail('Пароль может содержать только латинские буквы, цифры и спецсимволы (без пробелов и кириллицы).');

            return;
        }

        $missing = [];
        if (! preg_match('/[A-Z]/', $password)) {
            $missing[] = 'заглавной латинской буквы (A–Z)';
        }
        if (! preg_match('/[0-9]/', $password)) {
            $missing[] = 'цифры';
        }
        if (! preg_match('#[!-/:-@\[-`{-~]#', $password)) {
            $missing[] = 'спецсимвола (например ! @ # $ % & *)';
        }

        if ($missing) {
            $fail('В пароле не хватает: '.implode(', ', $missing).'.');
        }
    }
}
