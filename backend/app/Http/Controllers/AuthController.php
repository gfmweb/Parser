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
            return $this->apiError('Invalid credentials.', 401);
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
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->apiError('Unauthenticated.', 401);
        }

        $token = $user->currentAccessToken();
        $token->delete();

        return $this->apiSuccess(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->apiError('Unauthenticated.', 401);
        }

        return $this->apiSuccess([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
