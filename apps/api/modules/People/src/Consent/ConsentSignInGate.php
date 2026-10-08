<?php

namespace Modules\People\Consent;

use Modules\Identity\Contracts\DTOs\SignInRefusal;
use Modules\Identity\Contracts\SignInGate;
use Modules\SharedKernel\Audit\AuditActor;

/**
 * Holds back a child whose account waits for a guardian's consent: no sign-in
 * on the panels or the apps, and an open family page stops at its next
 * request. The platform team signed in as someone for support is let through
 * (nothing is collected from the child).
 */
final class ConsentSignInGate implements SignInGate
{
    public function __construct(private readonly ConsentRules $rules) {}

    public function refusal(string $userId): ?SignInRefusal
    {
        if (AuditActor::current()->onBehalfOf !== null || ! $this->rules->state($userId)->waiting()) {
            return null;
        }

        return new SignInRefusal('guardian-consent-required', __('people::consent.refusal'));
    }
}
