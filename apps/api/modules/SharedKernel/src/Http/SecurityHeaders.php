<?php

namespace Modules\SharedKernel\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers every answer carries (docs/architecture/03, section 11): the
 * browser never guesses a file's type, no other site frames our pages, no
 * plugin or foreign <base> runs in them, links to other sites take only our
 * origin with them (signed links carry their signature in the address),
 * features the product never uses stay off, and over https the browser keeps
 * to https. A header an answer already set (a file's own policy) is kept.
 * The portal sends the same set (apps/web/next.config.ts) and the image's
 * web server adds it to static files (infra/deploy/Caddyfile).
 */
final class SecurityHeaders
{
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "base-uri 'self'; object-src 'none'; frame-ancestors 'self'",
        'Permissions-Policy' => 'geolocation=(), usb=(), serial=(), hid=(), bluetooth=()',
    ];

    /** Half a year, this host only: the platform's domain may have other sites under it. */
    public const HSTS = 'max-age=15552000';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }
        if (! headers_sent()) {
            header_remove('X-Powered-By');   // PHP's own, where expose_php is on
        }

        return $response;
    }
}
