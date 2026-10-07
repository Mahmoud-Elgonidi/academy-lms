<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Actions\Impersonation\EndImpersonation;
use Modules\Identity\Models\Impersonation;
use Modules\SharedKernel\Audit\AuditActor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every panel request in a signed-in-as session: while it lasts, whatever is
 * done is audited as the platform admin on the person's behalf; once it has
 * run out or was ended from the console, the browser is signed out and told.
 */
final class WatchImpersonation
{
    public function __construct(private readonly EndImpersonation $end) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(Impersonation::SESSION_KEY)) {
            return $next($request);
        }

        $impersonation = Impersonation::query()->find($request->session()->get(Impersonation::SESSION_KEY));
        $user = Auth::guard('web')->user();
        if ($impersonation === null || $user === null || (string) $user->getAuthIdentifier() !== $impersonation->user_id) {
            $request->session()->forget(Impersonation::SESSION_KEY);

            return $next($request);
        }

        if (! $impersonation->isLive()) {
            if ($impersonation->ended_at === null) {
                $this->end->handle($impersonation, 'expired');
            }
            self::signOut($request);

            return redirect()->route('identity.impersonate.ended', ['why' => $impersonation->ended_reason === 'ended' ? 'ended' : 'expired']);
        }

        AuditActor::use(new AuditActor('platform', $impersonation->admin_id, $impersonation->admin_name, $impersonation->user_id, $impersonation->id));
        view()->share('lmsImpersonation', $impersonation);

        return $next($request);
    }

    public static function signOut(Request $request): void
    {
        Auth::guard('web')->logout();
        self::freshSession($request);
        AuditActor::forget();
    }

    /**
     * A new session for the academy side, keeping only a platform console
     * sign-in (the console shares the host, and so the session, in local
     * development; in production it has a host of its own).
     */
    public static function freshSession(Request $request): void
    {
        $keep = array_filter($request->session()->all(), fn (string $key) => str_starts_with($key, 'login_platform_') || $key === 'password_hash_platform', ARRAY_FILTER_USE_KEY);
        $request->session()->invalidate();
        $request->session()->put($keep);
    }
}
