<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Platform\Models\PlatformAdmin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone locked out of the console (platform:admin --deactivate) who still
 * has a session: it ends on their next request, before the panel looks at
 * who is signed in, so they land on the sign-in page, where they are refused.
 */
final class EndLockedConsoleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth()->guard('platform');
        $admin = $guard->user();
        if ($admin instanceof PlatformAdmin && ! $admin->is_active) {
            $guard->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return $next($request);
    }
}
