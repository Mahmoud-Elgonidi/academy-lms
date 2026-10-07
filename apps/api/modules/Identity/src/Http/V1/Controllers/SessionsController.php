<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Identity\Actions\EndSession;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** The apps signed in to my account, one per device, and signing one out from another. */
final class SessionsController
{
    /** My signed-in devices. */
    public function index(Request $request, LocalTimePresenter $time): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $user->currentAccessToken();
        $currentId = $current instanceof PersonalAccessToken ? $current->id : null;

        $tokens = $user->tokens()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_used_at')->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $tokens->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'device' => $token->name,
                /** This app, the one asking. */
                'current' => $token->id === $currentId,
                'created_at' => $time->present($token->created_at),
                'last_used_at' => $time->present($token->last_used_at),
                'expires_at' => $time->present($token->expires_at),
            ])->values(),
        ]);
    }

    /** Sign a device out. */
    public function destroy(Request $request, int $session, EndSession $end): Response
    {
        /** @var User $user */
        $user = $request->user();
        $end->handle($user, $session);

        return response()->noContent();
    }
}
