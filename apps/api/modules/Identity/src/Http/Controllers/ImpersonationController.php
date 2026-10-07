<?php

namespace Modules\Identity\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Actions\Impersonation\EndImpersonation;
use Modules\Identity\Actions\Impersonation\RedeemImpersonation;
use Modules\Identity\Exceptions\ImpersonationUnavailable;
use Modules\Identity\Http\Middleware\WatchImpersonation;
use Modules\Identity\Models\Impersonation;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\ConsoleApi;
use Symfony\Component\HttpFoundation\Response;

/**
 * The academy's side of a platform sign-in: the one-time link signs this
 * browser in as the person and opens their own area; leaving (or the end
 * of its time) signs it out and points back to the console.
 */
final class ImpersonationController
{
    public function redeem(Request $request, string $token, RedeemImpersonation $redeem): Response
    {
        try {
            $impersonation = $redeem->handle($token);
        } catch (ImpersonationUnavailable) {
            return $this->page('used', 410);
        }

        // Whoever was signed in on this browser before is signed out first, with a session of its own.
        Auth::guard('web')->logout();
        WatchImpersonation::freshSession($request);
        Auth::guard('web')->login($impersonation->user);
        $request->session()->put(Impersonation::SESSION_KEY, $impersonation->id);

        return redirect()->to(WelcomeController::home($impersonation->user));
    }

    public function exit(Request $request, EndImpersonation $end): RedirectResponse
    {
        $impersonation = Impersonation::query()->find($request->session()->get(Impersonation::SESSION_KEY));
        if ($impersonation !== null) {
            $end->handle($impersonation, 'exit');
        }
        WatchImpersonation::signOut($request);

        return redirect()->route('identity.impersonate.ended', ['why' => 'exit']);
    }

    public function ended(Request $request): Response
    {
        $why = $request->query('why');

        return $this->page(in_array($why, ['exit', 'expired', 'ended'], true) ? $why : 'exit');
    }

    private function page(string $why, int $status = 200): Response
    {
        return response()->view('identity::impersonation.ended', [
            'why' => $why,
            'console' => app(ConsoleApi::class)->consoleUrl('support-sign-in'),
            'branding' => app(BrandingApi::class)->current(),
        ], $status);
    }
}
