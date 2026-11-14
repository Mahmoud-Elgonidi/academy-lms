<?php

namespace Modules\Platform\Recharging;

use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Raises a SharedKernel event inside the academy's context, so whoever listens (Communication) reaches its people. */
final class InAcademy
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function raise(string $academyId, object $event): void
    {
        if ($this->tenants->id() === $academyId) {
            event($event);

            return;
        }
        $academy = Academy::query()->find($academyId);
        if ($academy !== null) {
            $this->tenants->run($academy->toTenantRef(), fn () => event($event));
        }
    }
}
