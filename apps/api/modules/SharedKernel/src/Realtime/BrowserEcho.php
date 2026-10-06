<?php

namespace Modules\SharedKernel\Realtime;

use Illuminate\Http\Request;

/**
 * Laravel Echo settings for the browser, when the app broadcasts through
 * Reverb; null otherwise, and the screen refreshes on a timer as before.
 * Browsers connect to the host the page came from (each academy has its
 * own) unless REVERB_PUBLIC_HOST says where Reverb is.
 *
 * A live screen turns it on for its own page only (enableOnThisPage), so
 * other screens open no socket.
 */
final class BrowserEcho
{
    /** @return array<string, mixed>|null */
    public static function config(Request $request): ?array
    {
        if (config('broadcasting.default') !== 'reverb' || ! config('broadcasting.connections.reverb.key')) {
            return null;
        }
        $client = config('broadcasting.connections.reverb.client', []);
        $port = (int) ($client['port'] ?? 443);

        return [
            'broadcaster' => 'reverb',
            'key' => config('broadcasting.connections.reverb.key'),
            'wsHost' => $client['host'] ?? $request->getHost(),
            'wsPort' => $port,
            'wssPort' => $port,
            'forceTLS' => ($client['scheme'] ?? 'https') === 'https',
            'enabledTransports' => ['ws', 'wss'],
            'authEndpoint' => '/broadcasting/auth',
            'disableStats' => true,
        ];
    }

    /** Filament loads Echo with these settings on the page being rendered. */
    public static function enableOnThisPage(Request $request): bool
    {
        $echo = self::config($request);
        if ($echo === null) {
            return false;
        }
        config(['filament.broadcasting.echo' => $echo]);

        return true;
    }
}
