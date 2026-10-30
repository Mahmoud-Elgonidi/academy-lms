<?php

namespace Modules\Platform\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;

/**
 * The academy's own zone (Settings › Branding, the owner alone): times for
 * accounts with no zone of their own, and the academy's calendar days (its
 * holidays, its months). An IANA name, never an offset. Audited when it
 * changes; the rest of the request sees the new zone.
 */
final class SaveAcademyTimezone
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $timezone): string
    {
        if (! Gate::allows(Ability::ManageAcademyTimezone->value)) {
            throw new AuthorizationException;
        }
        if (! Timezones::isValid($timezone)) {
            throw ValidationException::withMessages(['default_timezone' => __('platform::branding.errors.timezone')]);
        }

        $academy = Academy::query()->findOrFail($this->tenants->require()->id);
        if ($academy->default_timezone === $timezone) {
            return $timezone;
        }
        $from = $academy->default_timezone;
        $academy->fill(['default_timezone' => $timezone])->save();
        $this->audit->record('academy.timezone_saved', 'academy', $academy->id, ['from' => $from, 'to' => $timezone]);
        $this->tenants->set($academy->toTenantRef());

        return $timezone;
    }
}
