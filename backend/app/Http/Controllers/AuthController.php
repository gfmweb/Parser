<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email())->first();

        if ($user === null || ! Hash::check($request->password(), $user->password)) {
            return $this->apiError('Неверный email или пароль.', 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->apiSuccess([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $token = $user->currentAccessToken();
        $token->delete();

        return $this->apiSuccess(['message' => 'Вы вышли из системы.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->apiSuccess([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
