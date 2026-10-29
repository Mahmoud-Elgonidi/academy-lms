<?php

namespace Modules\Identity\TwoFactor;

use Modules\Identity\Models\IdentitySettings;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether the academy on this request requires two-step sign-in of its
 * back office (identity_settings.require_two_factor; yes with no row, and
 * yes with no academy). Read once per request and academy (bound scoped);
 * SaveSecuritySettings forgets it when it changes.
 */
final class AcademyTwoFactorRule
{
    /** @var array<string, bool> academy id => required */
    private array $known = [];

    public function __construct(private readonly TenantContext $tenant) {}

    public function required(): bool
    {
        $academy = $this->tenant->id();
        if ($academy === null) {
            return true;
        }

        return $this->known[$academy] ??= IdentitySettings::current()->require_two_factor;
    }

    public function forget(): void
    {
        $this->known = [];
    }
}
