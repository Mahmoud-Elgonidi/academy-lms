<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Login;
use Modules\Identity\Actions\UpdateProfile;
use Modules\Identity\Http\V1\Requests\LoginRequest;
use Modules\Identity\Http\V1\Requests\UpdateProfileRequest;
use Modules\Identity\Http\V1\Resources\UserResource;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;

final class AuthController
{
    public function login(LoginRequest $request, Login $login, ViewerTimezone $viewer, LocalTimePresenter $time): JsonResponse
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
        $viewer->setUser($result['user']);

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $time->present($result['expires_at']),
            'user' => new UserResource($result['user']),
        ]);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('roleRecords'));
    }

    public function updateMe(UpdateProfileRequest $request, UpdateProfile $update): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($update->handle($user, $request->validated())->load('roleRecords'));
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
