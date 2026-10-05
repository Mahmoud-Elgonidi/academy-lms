<?php

namespace Modules\SharedKernel\Http;

use Closure;
use Illuminate\Http\Request;
use Modules\SharedKernel\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language: Accept-Language if it names a supported
 * locale, else the academy's default, else the app default.
 */
final class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED);

        if (! $request->headers->has('Accept-Language')) {
            $locale = $this->tenants->current()?->defaultLocale ?? config('app.locale');
        }

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : config('app.locale'));

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
