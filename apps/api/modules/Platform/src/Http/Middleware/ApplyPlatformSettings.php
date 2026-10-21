<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Platform\Settings\PlatformSettings;
use Symfony\Component\HttpFoundation\Response;

/** First on every request: the platform's settings from the console, over the server's .env (PlatformSettings). */
final class ApplyPlatformSettings
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->apply();

        return $next($request);
    }
}
