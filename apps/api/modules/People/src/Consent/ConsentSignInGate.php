<?php

namespace Modules\People\Consent;

use Modules\Identity\Contracts\DTOs\SignInRefusal;
use Modules\Identity\Contracts\SignInGate;
use Modules\People\Actions\Consent\RequestConsent;
use Modules\SharedKernel\Audit\AuditActor;

/**
 * Holds back a child whose account waits for a guardian's consent: no sign-in
 * on the panels or the apps, and an open family page stops at its next
 * request. The platform team signed in as someone for support is let through
 * (nothing is collected from the child). The first time the child tries, the
 * guardians are asked (a child brought to a free trial was not asked yet).
 */
final class ConsentSignInGate implements SignInGate
{
    public function __construct(private readonly ConsentRules $rules, private readonly RequestConsent $request) {}

    public function refusal(string $userId): ?SignInRefusal
    {
        if (AuditActor::current()->onBehalfOf !== null || ! $this->rules->state($userId)->waiting()) {
            return null;
        }

        // Each guardian hears of it once a day at most (the message's own key).
        $this->request->handle($userId);

        return new SignInRefusal('guardian-consent-required', __('people::consent.refusal'));
    }
}
