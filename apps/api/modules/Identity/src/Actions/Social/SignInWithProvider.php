<?php

namespace Modules\Identity\Actions\Social;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Identity\Models\SocialIdentity;
use Modules\Identity\Models\User;
use Modules\Identity\Social\SocialProfile;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Http\ProblemException;

/**
 * Who in this academy a Google or Apple sign-in is: the account linked to
 * that Google or Apple account, else the account whose email the provider
 * verified (linked now, so a later change of email at Google does not lose
 * it). Only accounts the academy made sign in this way: nobody is created.
 * The caller then issues the sign-in the usual way (IssueAccessToken::handle
 * or the panel's challenge), so two-step sign-in and a guardian's consent
 * still apply.
 */
final class SignInWithProvider
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** @param  ?string  $userId  one account in particular (an account discovery listed) */
    public function handle(SocialProfile $profile, ?string $userId = null): User
    {
        $linked = SocialIdentity::query()->with('user')
            ->where('provider', $profile->provider->value)->where('subject', $profile->subject)->first();
        $email = $profile->matchableEmail();
        $byEmail = $email === null ? null : User::query()->whereRaw('lower(email) = ?', [$email])->first();

        $user = $userId === null
            ? ($linked?->user ?? $byEmail)
            : collect([$linked?->user, $byEmail])->first(fn (?User $u) => $u?->id === $userId);
        if ($user === null || ! $user->isActive()) {
            throw new ProblemException(404, 'social-account-not-found', __('identity::social.errors.no_account', ['provider' => $profile->provider->label()]));
        }

        if ($linked?->user_id === $user->id) {
            $linked->forceFill(['last_used_at' => now(), 'email' => $profile->email ?? $linked->email])->save();

            return $user;
        }

        // Found by the verified email: link it, unless the account already has another account of this provider.
        if (SocialIdentity::query()->where('user_id', $user->id)->where('provider', $profile->provider->value)->exists()) {
            throw new ProblemException(409, 'social-other-account-linked', __('identity::social.errors.other_linked', ['provider' => $profile->provider->label()]));
        }
        try {
            SocialIdentity::query()->create([
                'user_id' => $user->id, 'provider' => $profile->provider, 'subject' => $profile->subject, 'email' => $profile->email,
                'via' => 'email', 'linked_at' => now(), 'last_used_at' => now(),
            ]);
            $this->audit->record('identity.social_linked', 'user', $user->id, ['name' => $user->name, 'provider' => $profile->provider->value, 'via' => 'email']);
        } catch (UniqueConstraintViolationException) {
            // Two sign-ins at once: the other one linked it.
        }

        return $user;
    }
}
