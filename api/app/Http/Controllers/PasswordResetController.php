<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * "I forgot my password" - for users who are NOT logged in.
 *
 *  1. POST /api/password/forgot  {email}                    -> a one-time link is e-mailed
 *  2. POST /api/password/reset   {email, token, password}   -> the new password is saved
 *
 * The old password is not needed, but the link proves that the person owns the mailbox.
 * (Letting anyone set a new password for any e-mail address without that proof would let
 * strangers take over accounts.) The token is stored hashed in the table password_reset_tokens,
 * is valid for 60 minutes and works only once.
 */
class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();

        if ($user && ! $user->is_blocked) {
            try {
                $token = Password::broker()->createToken($user);
                $url = rtrim(config('kolonka.frontend_url'), '/').'/password/reset?'
                    .http_build_query(['token' => $token, 'email' => $user->email]);

                Mail::to($user->email)->send(new PasswordResetMail($user->name, $url));
            } catch (\Throwable $e) {
                report($e);   // e.g. the mail server is down: write it to the log, answer the same way
            }
        }

        // The SAME answer whether or not the e-mail exists - do not reveal who is registered
        return response()->json([
            'message' => 'Если такой email зарегистрирован, мы отправили на него письмо со ссылкой для смены пароля.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:100', new StrongPassword],
        ]);

        $email = mb_strtolower(trim($data['email']));

        $status = Password::broker()->reset(
            ['email' => $email, 'password' => $data['password'], 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();   // hashed by the model cast
                $user->tokens()->delete();                              // sign out on every device
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Ссылка недействительна или устарела. Запросите новую.'],
            ]);
        }

        return response()->json(['message' => 'Пароль изменён. Войдите с новым паролем.']);
    }
}
