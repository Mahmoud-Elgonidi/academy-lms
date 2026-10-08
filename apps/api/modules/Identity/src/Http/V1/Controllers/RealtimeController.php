<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\SharedKernel\Realtime\BrowserEcho;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Live updates for the apps and the portal: where to open the socket
 * (Reverb, Pusher protocol) and the person's own channel, then the signed
 * permission to listen on it with the app's token. Signals carry no data
 * ("lessons.changed", "homework.changed", "inbox.changed", ...); the page
 * asks the API again for what it shows.
 */
final class RealtimeController
{
    /**
     * Where to listen.
     *
     * enabled is false when the academy's server does not broadcast; refresh some other way then.
     */
    public function show(Request $request): JsonResponse
    {
        $echo = BrowserEcho::config($request);

        return response()->json(['data' => [
            'enabled' => $echo !== null,
            /** The app key for the socket (wss://{host}:{port}/app/{key}). */
            'key' => $echo['key'] ?? null,
            /** null: the same host as the page (or the API) you came from. */
            'host' => $echo === null ? null : config('broadcasting.connections.reverb.client.host'),
            'port' => $echo['wsPort'] ?? null,
            'tls' => (bool) ($echo['forceTLS'] ?? true),
            /** My own private channel: subscribe to "private-" and this. */
            'channel' => PersonalChannel::name((string) $request->user()?->getAuthIdentifier()),
        ]]);
    }

    /**
     * Permission to listen.
     *
     * Signs a socket's subscription to a private channel I may hear (my own); the socket sends the answer as its auth.
     *
     * @throws AccessDeniedHttpException not a channel I may hear
     * @throws NotFoundHttpException the server does not broadcast
     */
    public function auth(Request $request): JsonResponse
    {
        $request->validate([
            'socket_id' => ['required', 'string', 'max:64', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:200', 'starts_with:private-'],
        ]);
        if (BrowserEcho::config($request) === null) {
            throw new NotFoundHttpException;
        }
        $signed = Broadcast::auth($request);

        return response()->json([
            /** The signature for the subscription. */
            'auth' => is_array($signed) ? (string) ($signed['auth'] ?? '') : '',
        ]);
    }
}
