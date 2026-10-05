<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Login;
use Modules\Identity\Http\V1\Requests\LoginRequest;
use Modules\Identity\Http\V1\Resources\UserResource;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;

final class AuthController
{
    public function login(LoginRequest $request, Login $login): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        try {
            $result = $login->handle(
                (string) $request->string('login'),
                (string) $request->string('password'),
                (string) $request->string('device_name'),
            );
        } catch (ValidationException $e) {
            $request->hitRateLimiter();

            throw $e;
        }

        $request->clearRateLimiter();

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']?->format(DATE_ATOM),
            'user' => new UserResource($result['user']),
        ]);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('roleRecords'));
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
