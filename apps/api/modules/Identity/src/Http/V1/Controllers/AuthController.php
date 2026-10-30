<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Login;
use Modules\Identity\Actions\TwoFactor\CompleteTwoFactorSignIn;
use Modules\Identity\Actions\UpdatePreferences;
use Modules\Identity\Actions\UpdateProfile;
use Modules\Identity\Http\V1\Requests\LoginRequest;
use Modules\Identity\Http\V1\Requests\UpdateProfileRequest;
use Modules\Identity\Http\V1\Resources\UserResource;
use Modules\Identity\Http\V1\Responses\TokenResponse;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;
use Modules\Identity\Preferences\ZoneChipPreference;

final class AuthController
{
    /**
     * Sign in with a password.
     *
     * The token for this device. With two-step sign-in on, a 403 problem of type "two-factor-required" instead,
     * carrying two_factor_token for auth/two-factor; "two-factor-setup-required" when the account must set it up in the back office first.
     */
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

        return TokenResponse::make($result);
    }

    /**
     * Finish signing in with the second step.
     *
     * After a sign-in answered "two-factor-required": its two_factor_token and the code the authenticator app shows now
     * (or one of the recovery codes, used up). Five tries within five minutes; then sign in again. Answers like any sign-in.
     */
    public function twoFactor(Request $request, CompleteTwoFactorSignIn $complete): JsonResponse
    {
        $request->validate([
            'two_factor_token' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        return TokenResponse::make($complete->handle(
            (string) $request->string('two_factor_token'),
            (string) $request->string('code'),
            $request->filled('device_name') ? (string) $request->string('device_name') : null,
        ));
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('roleRecords'));
    }

    public function updateMe(UpdateProfileRequest $request, UpdateProfile $update, UpdatePreferences $preferences): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user = $update->handle($user, $request->validated());
        if ($request->has('zone_chip')) {
            $user = $preferences->handle($user, [ZoneChipPreference::KEY => ZoneChipPreference::value($request->boolean('zone_chip'))]);
        }

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
