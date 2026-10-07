<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every console request: no academy is set (the console works across them
 * on the platform connection), the screens speak the admin's language and
 * keep the admin's clock, and what is done is audited as the platform admin.
 */
final class ConsoleContext
{
    public function __construct(private readonly ViewerTimezone $viewer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth()->guard('platform')->user();
        app()->setLocale($admin instanceof PlatformAdmin ? $admin->preferredLocale() : 'ar');

        $this->viewer->reset();
        if ($admin instanceof PlatformAdmin) {
            $this->viewer->setUser($admin);
            AuditActor::use(new AuditActor('platform', $admin->id, $admin->name));
        }

        return $next($request);
    }
}
