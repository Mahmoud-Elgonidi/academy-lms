<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Actions\IssueAccessToken;
use Modules\Identity\Actions\Otp\RequestSignInCode;
use Modules\Identity\Actions\Otp\VerifySignInCode;
use Modules\Identity\Http\V1\Requests\SendCodeRequest;
use Modules\Identity\Http\V1\Requests\VerifyCodeRequest;
use Modules\Identity\Http\V1\Resources\SentCodeResource;
use Modules\Identity\Http\V1\Responses\TokenResponse;

/** Signing in to an academy with a one-time code instead of a password. */
final class CodeSignInController
{
    /**
     * Send a sign-in code.
     *
     * Always 202 with the same body, whether or not the contact has an account here.
     */
    public function send(SendCodeRequest $request, RequestSignInCode $codes): SentCodeResource
    {
        return new SentCodeResource($codes->handle(
            (string) $request->string('contact'),
            $request->input('channel'),
            $request->ip(),
        ));
    }

    /** Sign in with the code. */
    public function verify(VerifyCodeRequest $request, VerifySignInCode $verify, IssueAccessToken $tokens): JsonResponse
    {
        $user = $verify->handle((string) $request->string('challenge_id'), (string) $request->string('code'));

        return TokenResponse::make($tokens->handle($user, (string) $request->string('device_name')));
    }
}
