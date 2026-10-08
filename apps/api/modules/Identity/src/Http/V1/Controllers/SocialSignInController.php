<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\IssueAccessToken;
use Modules\Identity\Actions\Social\DiscoverSocialAccounts;
use Modules\Identity\Actions\Social\LinkSocialIdentity;
use Modules\Identity\Actions\Social\SignInWithProvider;
use Modules\Identity\Actions\Social\UnlinkSocialIdentity;
use Modules\Identity\Http\V1\Requests\SocialStartRequest;
use Modules\Identity\Http\V1\Requests\SocialTicketRequest;
use Modules\Identity\Http\V1\Requests\SocialTokenRequest;
use Modules\Identity\Http\V1\Resources\DiscoveredAccountResource;
use Modules\Identity\Http\V1\Responses\TokenResponse;
use Modules\Identity\Models\SocialSignIn;
use Modules\Identity\Models\User;
use Modules\Identity\Queries\SocialAccounts;
use Modules\Identity\Social\IdTokens;
use Modules\Identity\Social\InvalidIdToken;
use Modules\Identity\Social\Provider;
use Modules\Identity\Social\SocialFlow;
use Modules\Identity\Social\SocialProfile;
use Modules\Identity\Social\SocialTicketRefused;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Http\ProblemException;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * Signing in with Google or Apple from the apps (the phone's own sign-in
 * gives an ID token) and the portal (the browser goes to the provider and
 * comes back with a ticket), and linking them to one's account. Only
 * accounts the academy made sign in this way; two-step sign-in and a
 * guardian's consent apply as to any other sign-in.
 */
final class SocialSignInController
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /**
     * Ways to sign in besides the academy's own.
     *
     * Google and Apple, where the platform has them: in a browser (web) and in the apps (app).
     */
    public function providers(): JsonResponse
    {
        $providers = [];
        foreach (Provider::cases() as $p) {
            if ($p->onForWeb() || $p->onForApps()) {
                $providers[] = [
                    'provider' => $p->value,
                    'label' => $p->label(),
                    /** Through the browser (POST auth/social/{provider}/start). */
                    'web' => (bool) $p->onForWeb(),
                    /** With the phone's own sign-in (POST auth/social). */
                    'app' => (bool) $p->onForApps(),
                ];
            }
        }

        return response()->json(['providers' => $providers]);
    }

    /**
     * Sign in with the phone's Google or Apple sign-in.
     *
     * Send the academy as X-Academy. With two-step sign-in on, a 403 problem of type "two-factor-required" carrying
     * two_factor_token for auth/two-factor. No account here with that Google or Apple account or its email: 404
     * "social-account-not-found".
     */
    public function token(SocialTokenRequest $request, SignInWithProvider $signIn, IssueAccessToken $tokens, IdTokens $idTokens): JsonResponse
    {
        $user = $signIn->handle($this->profile($request, $idTokens));

        return TokenResponse::make($tokens->handle($user, (string) $request->string('device_name')));
    }

    /**
     * Find my accounts with Google or Apple.
     *
     * No academy is needed: the accounts linked to that Google or Apple account, or carrying the email it verified,
     * in every academy, and a discovery token for POST /accounts/sign-in, as after a code.
     */
    public function discover(SocialTokenRequest $request, DiscoverSocialAccounts $discover, IdTokens $idTokens): JsonResponse
    {
        $found = $discover->handle($this->profile($request, $idTokens));

        return response()->json([
            /** Trade it for a sign-in to each account (POST /accounts/sign-in) until expires_at. */
            'discovery_token' => $found['token'],
            'expires_at' => $this->time->present($found['expires_at']),
            'accounts' => DiscoveredAccountResource::collection($found['accounts']),
        ]);
    }

    /**
     * Start signing in with Google or Apple in the portal.
     *
     * Send the browser to url; it comes back to the portal's /bff/social/finish with a ticket (or an error).
     */
    public function start(SocialStartRequest $request, string $provider, SocialFlow $flow): JsonResponse
    {
        $provider = $this->webProvider($provider);

        return response()->json(['url' => $flow->authorizeUrl($provider, 'portal', 'sign_in', null, (string) $request->string('bind'), $this->locale($request))]);
    }

    /**
     * Sign in with the ticket the portal got back.
     *
     * With two-step sign-in on, a 403 problem of type "two-factor-required" carrying two_factor_token for auth/two-factor.
     */
    public function ticket(SocialTicketRequest $request, SocialFlow $flow, SignInWithProvider $signIn, IssueAccessToken $tokens): JsonResponse
    {
        $ticket = $this->redeem($flow, $request, 'sign_in');

        return TokenResponse::make($tokens->handle($signIn->handle($ticket->profile()), (string) $request->string('device_name')));
    }

    /** My Google and Apple sign-ins. */
    public function mine(Request $request, SocialAccounts $accounts): JsonResponse
    {
        return $this->list($request->user(), $accounts);
    }

    /**
     * Link Google or Apple from the portal.
     *
     * Send the browser to url; it comes back to the portal's /bff/social/finish with a ticket for POST me/social/ticket.
     */
    public function startLink(SocialStartRequest $request, string $provider, SocialFlow $flow): JsonResponse
    {
        $provider = $this->webProvider($provider);
        $this->refuseWhileImpersonating();

        return response()->json(['url' => $flow->authorizeUrl($provider, 'portal', 'link', $request->user()->id, (string) $request->string('bind'), $this->locale($request))]);
    }

    /** Link with the ticket the portal got back. */
    public function linkTicket(SocialTicketRequest $request, SocialFlow $flow, LinkSocialIdentity $link, SocialAccounts $accounts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->redeem($flow, $request, 'link');
        if ($ticket->user_id !== $user->id) {
            throw new ProblemException(409, 'social-ticket-refused', __('identity::social.errors.expired'));
        }
        $link->handle($user, $ticket->profile());

        return $this->list($user, $accounts);
    }

    /** Link the phone's Google or Apple account. */
    public function link(SocialTokenRequest $request, LinkSocialIdentity $link, IdTokens $idTokens, SocialAccounts $accounts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link->handle($user, $this->profile($request, $idTokens));

        return $this->list($user, $accounts);
    }

    /** Unlink Google or Apple. */
    public function unlink(Request $request, string $provider, UnlinkSocialIdentity $unlink, SocialAccounts $accounts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $unlink->handle($user, Provider::tryFrom($provider) ?? abort(404));

        return $this->list($user, $accounts);
    }

    private function list(User $user, SocialAccounts $accounts): JsonResponse
    {
        $rows = [];
        foreach ($accounts->of($user, web: false) as $row) {
            $rows[] = [
                'provider' => $row['provider']->value,
                'label' => $row['provider']->label(),
                'linked' => (bool) $row['linked'],
                /** The Google or Apple email it is linked as (Apple's may be a relay address). */
                'email' => $row['email'] === null ? null : (string) $row['email'],
                'linked_at' => $this->time->present($row['linked_at']),
                'last_used_at' => $this->time->present($row['last_used_at']),
            ];
        }

        return response()->json(['data' => $rows]);
    }

    /** What the phone's ID token says, for one of the apps' clients (or the web client, as Android's tokens are). */
    private function profile(SocialTokenRequest $request, IdTokens $idTokens): SocialProfile
    {
        $provider = Provider::from((string) $request->string('provider'));
        $audiences = array_values(array_filter([...$provider->appAudiences(), (string) ($provider->config()['client_id'] ?? '')]));
        if ($audiences === []) {
            throw new ProblemException(404, 'social-provider-off', __('identity::social.errors.off', ['provider' => $provider->label()]));
        }
        try {
            return $idTokens->verify($provider, (string) $request->string('id_token'), $audiences, $request->filled('nonce') ? (string) $request->string('nonce') : null);
        } catch (InvalidIdToken) {
            throw new ProblemException(401, 'social-token-invalid', __('identity::social.errors.failed', ['provider' => $provider->label()]));
        }
    }

    private function webProvider(string $provider): Provider
    {
        $provider = Provider::tryFrom($provider);

        return $provider?->onForWeb() ? $provider : throw new ProblemException(404, 'social-provider-off', __('identity::social.errors.off', ['provider' => $provider?->label() ?? '']));
    }

    private function redeem(SocialFlow $flow, SocialTicketRequest $request, string $intent): SocialSignIn
    {
        try {
            return $flow->redeem((string) $request->string('ticket'), (string) $request->string('bind'), $intent);
        } catch (SocialTicketRefused) {
            throw new ProblemException(409, 'social-ticket-refused', __('identity::social.errors.expired'));
        }
    }

    private function refuseWhileImpersonating(): void
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['provider' => __('identity::social.errors.impersonating')]);
        }
    }

    private function locale(Request $request): string
    {
        return in_array($request->input('locale'), ['ar', 'en'], true) ? (string) $request->input('locale') : app()->getLocale();
    }
}
