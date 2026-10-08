<?php

namespace Modules\Identity\Actions\Social;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\SocialIdentity;
use Modules\Identity\Models\User;
use Modules\Identity\Social\SocialProfile;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The signed-in person links their Google or Apple account (from their
 * settings), so it signs them in whatever its email (an Apple address that
 * hides the real one, an email the academy does not have). One per provider;
 * a Google or Apple account signs in to one account per academy. Never while
 * the platform team is signed in as them.
 */
final class LinkSocialIdentity
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(User $user, SocialProfile $profile): SocialIdentity
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['provider' => __('identity::social.errors.impersonating')]);
        }
        $taken = SocialIdentity::query()->where('provider', $profile->provider->value)->where('subject', $profile->subject)->first();
        if ($taken !== null && $taken->user_id !== $user->id) {
            throw ValidationException::withMessages(['provider' => __('identity::social.errors.taken', ['provider' => $profile->provider->label()])]);
        }
        if ($taken !== null) {
            $taken->forceFill(['email' => $profile->email ?? $taken->email])->save();

            return $taken;
        }
        if (SocialIdentity::query()->where('user_id', $user->id)->where('provider', $profile->provider->value)->exists()) {
            throw ValidationException::withMessages(['provider' => __('identity::social.errors.other_linked', ['provider' => $profile->provider->label()])]);
        }

        $identity = SocialIdentity::query()->create([
            'user_id' => $user->id, 'provider' => $profile->provider, 'subject' => $profile->subject, 'email' => $profile->email,
            'via' => 'settings', 'linked_at' => now(),
        ]);
        $this->audit->record('identity.social_linked', 'user', $user->id, ['name' => $user->name, 'provider' => $profile->provider->value, 'via' => 'settings']);

        return $identity;
    }
}
