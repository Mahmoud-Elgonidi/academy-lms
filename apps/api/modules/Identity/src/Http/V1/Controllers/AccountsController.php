<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Actions\IssueAccessToken;
use Modules\Identity\Actions\Otp\RequestDiscoveryCode;
use Modules\Identity\Actions\Otp\SignInDiscoveredAccount;
use Modules\Identity\Actions\Otp\VerifyDiscoveryCode;
use Modules\Identity\Http\V1\Requests\DiscoveredSignInRequest;
use Modules\Identity\Http\V1\Requests\SendCodeRequest;
use Modules\Identity\Http\V1\Requests\VerifyCodeRequest;
use Modules\Identity\Http\V1\Resources\DiscoveredAccountResource;
use Modules\Identity\Http\V1\Resources\SentCodeResource;
use Modules\Identity\Http\V1\Responses\TokenResponse;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * The shared app's way in: verify an email or number once, see the person's
 * accounts in every academy, and add each one to the device.
 */
final class AccountsController
{
    /**
     * Send a code to find my accounts.
     *
     * No academy is needed. Always 202 with the same body, whether or not the contact has accounts.
     */
    public function send(SendCodeRequest $request, RequestDiscoveryCode $codes): SentCodeResource
    {
        return new SentCodeResource($codes->handle(
            (string) $request->string('contact'),
            $request->input('channel'),
            $request->ip(),
        ));
    }

    /** List my accounts with the code. */
    public function verify(VerifyCodeRequest $request, VerifyDiscoveryCode $verify, LocalTimePresenter $time): JsonResponse
    {
        $found = $verify->handle((string) $request->string('challenge_id'), (string) $request->string('code'));

        return response()->json([
            /** Trade it for a sign-in to each account (POST /accounts/sign-in) until expires_at. */
            'discovery_token' => $found['token'],
            'expires_at' => $time->present($found['expires_at']),
            'accounts' => DiscoveredAccountResource::collection($found['accounts']),
        ]);
    }

    /**
     * Sign in to a discovered account.
     *
     * Send the account's academy as X-Academy. With two-step sign-in on, a 403 problem of type "two-factor-required"
     * carrying two_factor_token for auth/two-factor (in the same academy).
     */
    public function signIn(DiscoveredSignInRequest $request, SignInDiscoveredAccount $signIn, IssueAccessToken $tokens): JsonResponse
    {
        $user = $signIn->handle((string) $request->string('discovery_token'), (string) $request->string('user_id'));

        return TokenResponse::make($tokens->handle($user, (string) $request->string('device_name')));
    }
}
