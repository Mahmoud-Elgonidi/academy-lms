<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\SharedKernel\Realtime\BrowserEcho;
use Symfony\Component\HttpFoundation\Response;

/**
 * Console panel middleware: a signed-in member of the platform team gets
 * Echo on every page, authorised on the console's own endpoint (no academy
 * to resolve, the platform guard).
 */
final class ConsoleEcho
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('platform') !== null && BrowserEcho::enableOnThisPage($request)) {
            config(['filament.broadcasting.echo.authEndpoint' => route('console.broadcasting.auth', absolute: false)]);
        }

        return $next($request);
    }
}
