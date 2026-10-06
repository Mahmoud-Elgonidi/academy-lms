<?php

namespace Modules\SharedKernel\Realtime;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel middleware: every page of a signed-in person gets Echo, pointed at
 * the host it was asked on (BrowserEcho). The sign-in page opens no socket.
 */
final class EnableBrowserEcho
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            BrowserEcho::enableOnThisPage($request);
        }

        return $next($request);
    }
}
