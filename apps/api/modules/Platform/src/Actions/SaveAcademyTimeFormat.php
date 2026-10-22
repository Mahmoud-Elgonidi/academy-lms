<?php

namespace Modules\Platform\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\TimeFormat;

/**
 * How the academy writes a clock time for everyone who has not chosen their
 * own (Settings › Branding): 12 hours (5:00 م) or 24 hours (17:00). Audited
 * when it changes; the rest of the request sees the new choice.
 */
final class SaveAcademyTimeFormat
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $format): TimeFormat
    {
        if (! Gate::allows(Ability::ManageBranding->value)) {
            throw new AuthorizationException;
        }
        $chosen = TimeFormat::of($format) ?? throw ValidationException::withMessages(['time_format' => __('platform::branding.errors.time_format')]);

        $academy = Academy::query()->findOrFail($this->tenants->require()->id);
        if ($academy->time_format === $chosen->value) {
            return $chosen;
        }
        $academy->fill(['time_format' => $chosen->value])->save();
        $this->audit->record('academy.time_format_saved', 'academy', $academy->id, ['clock' => $chosen->value]);
        $this->tenants->set($academy->toTenantRef());

        return $chosen;
    }
}
