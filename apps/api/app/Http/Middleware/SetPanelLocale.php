<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerClock;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office language, timezone and clock (12 or 24 hours): the signed-in
 * user's choice, else the academy's default. Filament switches to
 * right-to-left for Arabic.
 */
final class SetPanelLocale
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly ViewerTimezone $viewer,
        private readonly ViewerClock $clock,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $preferred = $user instanceof HasLocalePreference ? $user->preferredLocale() : null;
        $locale = $preferred ?? $this->tenants->current()?->defaultLocale ?? config('app.locale');

        app()->setLocale(in_array($locale, ['ar', 'en'], true) ? $locale : config('app.locale'));

        $this->viewer->reset();
        $this->clock->reset();
        if ($user !== null) {
            $this->viewer->setUser($user);
            $this->clock->setUser($user);
        }

        return $next($request);
    }
}
