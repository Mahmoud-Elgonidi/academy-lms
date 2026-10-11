<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With a host of its own (PLATFORM_CONSOLE_DOMAIN, e.g. console.progma.store)
 * the console answers there alone: on an academy's host, or the platform's,
 * its pages, its sign-in and its live channel are not there (404), so the
 * platform team never signs in where an academy's pages run and its sign-in
 * is not a door on every academy. Without the setting (local development) it
 * answers at /console on any host, as before.
 */
final class ConsoleHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::allows($request->getHost())) {
            abort(404);
        }

        return $next($request);
    }

    public static function allows(string $host): bool
    {
        $domain = config('platform.console_domain');

        return ! is_string($domain) || trim($domain) === '' || strcasecmp($host, trim($domain)) === 0;
    }
}
