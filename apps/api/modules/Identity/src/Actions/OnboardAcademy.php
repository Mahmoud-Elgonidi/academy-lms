<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\Validator;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * A new academy from the platform console (docs/architecture/01, section 1:
 * "إنشاء أكاديمية"): the academy and its address through Platform, then its
 * owner (owner and admin) with no password yet, and the owner's own welcome
 * link to choose one. The owner's details are checked before the academy is
 * made, so a bad email does not leave an academy without an owner.
 */
final class OnboardAcademy
{
    public function __construct(
        private readonly PlatformApi $platform,
        private readonly TenantContext $tenants,
        private readonly IdentityApi $identity,
    ) {}

    /** @return array{academy: TenantRef, owner_id: string, link: string} */
    public function handle(NewAcademy $academy, string $ownerName, string $ownerEmail, ?string $ownerPhone = null): array
    {
        $phone = $ownerPhone === null || trim($ownerPhone) === '' ? null : Phone::normalize($ownerPhone);
        Validator::make(['owner_name' => $ownerName, 'owner_email' => $ownerEmail, 'owner_phone' => $phone ?? $ownerPhone], [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email'],
            'owner_phone' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
        ], [
            'owner_email.*' => __('identity::onboarding.errors.owner_email'),
            'owner_phone.*' => __('identity::onboarding.errors.owner_phone'),
        ])->validate();

        $tenant = $this->platform->createAcademy($academy);

        return $this->tenants->run($tenant, function () use ($tenant, $ownerName, $ownerEmail, $phone) {
            $ownerId = $this->identity->createUser(new NewUser(
                name: trim($ownerName),
                roles: [Role::Owner, Role::Admin],
                email: mb_strtolower(trim($ownerEmail)),
                phoneE164: $phone,
                locale: $tenant->defaultLocale,
                timezone: $tenant->defaultTimezone,
            ));
            app(AuditTrail::class)->record('academy.created', 'academy', $tenant->id, ['slug' => $tenant->slug, 'owner' => trim($ownerName)]);

            return ['academy' => $tenant, 'owner_id' => $ownerId, 'link' => $this->identity->welcomeLink($ownerId)];
        });
    }
}
