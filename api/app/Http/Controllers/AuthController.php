<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // role and is_blocked are NEVER taken from the request
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),   // hashed by the model cast
            'role' => User::ROLE_USER,
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('spa')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль. Проверьте данные и попробуйте снова.'],
            ]);
        }

        if ($user->is_blocked) {
            return response()->json(['message' => 'Ваша учётная запись заблокирована.'], 403);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('spa')->plainTextToken,
        ]);
    }

    /**
     * PUT /api/me/password  (logged-in user): current password + new password.
     * Other devices are signed out, the current one stays signed in.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:100', new StrongPassword, 'different:current_password'],
        ], [
            'password.different' => 'Новый пароль должен отличаться от текущего.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Неверный текущий пароль.']]);
        }

        $user->password = $data['password'];   // hashed by the model cast
        $user->save();

        $current = $user->currentAccessToken();
        $user->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $current->id))
            ->delete();

        return response()->json(['message' => 'Пароль изменён.']);
    }

    public function logout(Request $request): JsonResponse
    {
        // Delete only the token that was used for this request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вы вышли из аккаунта.']);
    }

    /** Current user + numbers for the personal cabinet header. */
    public function me(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $user = $request->user();

        $myPostIds = Post::where('user_id', $user->id)->pluck('id');
        $profile = $recommendations->profile($user);

        return response()->json([
            'user' => new UserResource($user),
            'stats' => [
                'posts' => Post::where('user_id', $user->id)->published()->count(),
                'likes' => Like::whereIn('post_id', $myPostIds)->count(),
                'comments' => $user->comments()->count(),
            ],
            'interests' => $recommendations->interestLabels($profile, 5),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        // same e-mail rules as in registration: lower case, Latin characters only, unique
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['bail', 'required', 'string', 'max:120', 'ascii', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return response()->json(['user' => new UserResource($user)]);
    }
}
