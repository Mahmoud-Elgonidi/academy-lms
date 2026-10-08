<?php

namespace Modules\Identity\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Social\LinkSocialIdentity;
use Modules\Identity\Actions\Social\SignInWithProvider;
use Modules\Identity\Models\User;
use Modules\Identity\Social\InvalidIdToken;
use Modules\Identity\Social\Provider;
use Modules\Identity\Social\SocialFlow;
use Modules\Identity\Social\SocialTicketRefused;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Http\ProblemException;

/**
 * Signing in with Google or Apple on the panels (and the portal's way back):
 * leaving from the academy's own host, the providers' one callback on the
 * platform, and the hand-off back to the academy's host, where the panel's
 * sign-in page finishes it (two-step sign-in included) or My settings gets
 * the new link.
 */
final class SocialSignInController
{
    public const PANELS = ['academy', 'teach', 'family'];

    /** Off to the provider from a panel: to sign in, or (signed in) to link the account. */
    public function start(Request $request, string $provider, SocialFlow $flow): RedirectResponse
    {
        $provider = Provider::tryFrom($provider);
        $panel = (string) $request->query('panel');
        abort_unless($provider?->onForWeb() && in_array($panel, self::PANELS, true), 404);
        $link = $request->boolean('link');
        $user = $request->user();
        abort_if($link && ! $user instanceof User, 403);

        // Only this browser may finish what it starts.
        $secret = Str::random(40);
        $request->session()->put('identity.social.bind', $secret);

        return redirect()->away($flow->authorizeUrl($provider, "panel:{$panel}", $link ? 'link' : 'sign_in', $link ? $user->id : null, hash('sha256', $secret), app()->getLocale()));
    }

    /**
     * Where Google and Apple send everyone back (on the platform's own address): check what they say, then
     * hand it to the academy's host. Apple answers with a POST from its own site, so no session or CSRF here.
     */
    public function callback(Request $request, string $provider, SocialFlow $flow, TenantDirectory $directory): RedirectResponse
    {
        $state = $flow->readState((string) $request->input('state'));
        $provider = Provider::tryFrom($provider);
        $tenant = $state !== null ? $directory->byId((string) $state['a']) : null;
        abort_if($tenant === null || $provider === null || $provider->value !== $state['p'], 400, __('identity::social.errors.expired'));

        [$kind, $panel] = array_pad(explode(':', (string) $state['c'], 2), 2, null);
        $back = fn (array $query) => redirect()->away(rtrim($directory->baseUrl($tenant), '/')
            .($kind === 'portal' ? '/bff/social/finish' : '/auth/social/finish')
            .'?'.http_build_query($query + ['provider' => $provider->value, 'intent' => $state['i'], 'panel' => $panel, 'locale' => $state['l'] ?? null]));

        if ($request->filled('error')) {
            return $back(['error' => 'denied']);
        }
        try {
            $profile = $flow->profileFrom($provider, $request, $state);
        } catch (InvalidIdToken $e) {
            Log::warning("Sign-in with {$provider->value} refused: {$e->getMessage()}");

            return $back(['error' => 'failed']);
        }

        return $back(['ticket' => $flow->ticket($profile, $state)]);
    }

    /** Back on the academy's host: sign in on the panel's own page, or link the account in My settings. */
    public function finish(Request $request, SocialFlow $flow, SignInWithProvider $signIn, LinkSocialIdentity $link): RedirectResponse
    {
        $panel = in_array($request->query('panel'), self::PANELS, true) ? (string) $request->query('panel') : 'family';
        $provider = Provider::tryFrom((string) $request->query('provider')) ?? abort(404);
        $linking = $request->query('intent') === 'link';
        $secret = (string) $request->session()->pull('identity.social.bind', '');
        $words = ['provider' => $provider->label()];
        $away = fn (string $notice, bool $ok = false) => redirect()->to($linking ? (string) Filament::getPanel($panel)->getProfileUrl() : Filament::getPanel($panel)->getLoginUrl())
            ->with($ok ? 'identity.social.done' : 'identity.social.error', $notice);

        if ($request->filled('error')) {
            return $away(__('identity::social.errors.'.($request->query('error') === 'denied' ? 'denied' : 'failed'), $words));
        }
        try {
            $ticket = $flow->redeem((string) $request->query('ticket'), $secret, $linking ? 'link' : 'sign_in');
        } catch (SocialTicketRefused) {
            return $away(__('identity::social.errors.expired'));
        }

        if ($linking) {
            $user = $request->user();
            if (! $user instanceof User || $user->id !== $ticket->user_id) {
                return $away(__('identity::social.errors.expired'));
            }
            try {
                $link->handle($user, $ticket->profile());
            } catch (ValidationException $e) {
                return $away((string) collect($e->errors())->flatten()->first());
            }

            return $away(__('identity::social.settings.linked_done', $words), ok: true);
        }

        try {
            $user = $signIn->handle($ticket->profile());
        } catch (ProblemException $e) {
            return $away($e->getMessage());
        }
        // The panel's sign-in page finishes it: who may come in, and the authenticator app's code for whoever has it on.
        $request->session()->put('identity.social.passed', encrypt([$user->id, $panel, now()->addMinutes(10)->getTimestamp()]));

        return redirect()->to(Filament::getPanel($panel)->getLoginUrl());
    }
}
