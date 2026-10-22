<?php

namespace Modules\Identity\Http\V1\Responses;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Modules\Identity\Http\V1\Resources\UserResource;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerClock;
use Modules\SharedKernel\Time\ViewerTimezone;

/** The answer to every way of signing in from an app: the bearer token and who it is for. */
final class TokenResponse
{
    /** @param  array{user: User, token: string, expires_at: ?CarbonImmutable}  $result */
    public static function make(array $result): JsonResponse
    {
        app(ViewerTimezone::class)->setUser($result['user']);
        app(ViewerClock::class)->setUser($result['user']);

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => app(LocalTimePresenter::class)->present($result['expires_at']),
            'user' => new UserResource($result['user']),
        ]);
    }
}
