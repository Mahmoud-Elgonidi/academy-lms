<?php

namespace Modules\Platform\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Tenancy\AcademyAudience;
use Modules\SharedKernel\Tenancy\Audience;
use Modules\SharedKernel\Tenancy\TenantAudience;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whom an academy teaches (docs/architecture/02, "أكاديميات الكبار
 * والمهنيين"): families or adults only. The one way it changes once the
 * academy exists (it is chosen at its birth through NewAcademy): by an active
 * owner of the platform console, with a reason, never while signed in as
 * someone, audited in the platform's log and the academy's. The academy's
 * words and screens follow it from its next request; this request sees it
 * at once when it acts as that academy.
 */
final class SetAcademyAudience
{
    public const REASON_MIN = 5;

    public function __construct(
        private readonly PlatformAudit $audit,
        private readonly TenantContext $tenants,
        private readonly AcademyAudience $audiences,
    ) {}

    public function handle(PlatformAdmin $by, string $academyId, Audience $audience, string $reason): Academy
    {
        if (! $by->is_owner || ! $by->is_active) {
            throw new AuthorizationException(__('platform::console.audience.owners_only'));
        }
        if (AuditActor::current()->onBehalfOf !== null) {
            throw new AuthorizationException(__('platform::console.audience.impersonating'));
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > 300) {
            throw ValidationException::withMessages(['reason' => __('platform::console.errors.reason')]);
        }

        $academy = DB::connection('pgsql_platform')->transaction(function () use ($academyId, $audience, $reason): Academy {
            $academy = Academy::query()->lockForUpdate()->findOrFail($academyId);
            $before = $academy->audience ?? Audience::DEFAULT;
            if ($before === $audience) {
                return $academy;
            }
            $academy->audience = $audience;
            $academy->save();
            $this->audit->record($academy->id, 'academy.audience_changed', 'academy', $academy->id, [
                'academy_audience' => $audience->value, 'previous_audience' => $before->value, 'reason' => $reason,
            ]);

            return $academy;
        });

        if ($this->tenants->id() === $academy->id) {
            $this->tenants->set($academy->toTenantRef());
        }
        if ($this->audiences instanceof TenantAudience) {
            $this->audiences->forget();
        }

        return $academy;
    }
}
