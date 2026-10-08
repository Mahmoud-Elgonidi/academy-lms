<?php

namespace Modules\Identity\Actions\Social;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\SocialIdentity;
use Modules\Identity\Models\User;
use Modules\Identity\Social\Provider;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The person takes their Google or Apple account off. Not when it is their
 * only way in (no password, email, number or other link), and never while
 * the platform team is signed in as them.
 */
final class UnlinkSocialIdentity
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(User $user, Provider $provider): void
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['provider' => __('identity::social.errors.impersonating')]);
        }
        $identity = SocialIdentity::query()->where('user_id', $user->id)->where('provider', $provider->value)->first()
            ?? throw ValidationException::withMessages(['provider' => __('identity::social.errors.not_linked', ['provider' => $provider->label()])]);
        $otherWays = filled($user->getAuthPassword()) || filled($user->email) || filled($user->phone_e164)
            || SocialIdentity::query()->where('user_id', $user->id)->where('provider', '<>', $provider->value)->exists();
        if (! $otherWays) {
            throw ValidationException::withMessages(['provider' => __('identity::social.errors.last_way', ['provider' => $provider->label()])]);
        }

        $identity->delete();
        $this->audit->record('identity.social_unlinked', 'user', $user->id, ['name' => $user->name, 'provider' => $provider->value]);
    }
}
