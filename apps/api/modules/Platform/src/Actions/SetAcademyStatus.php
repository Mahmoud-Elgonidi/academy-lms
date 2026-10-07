<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Models\Academy;

/**
 * Suspends an academy (its sites, panels and app stop answering: the
 * tenant resolver refuses it) or brings it back, with the reason kept in
 * the audit log of the platform and of the academy.
 */
final class SetAcademyStatus
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function suspend(string $academyId, string $reason): Academy
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => __('platform::console.errors.reason')]);
        }

        return $this->set($academyId, AcademyStatus::Suspended, 'academy.suspended', ['reason' => $reason]);
    }

    public function reactivate(string $academyId): Academy
    {
        return $this->set($academyId, AcademyStatus::Active, 'academy.reactivated', []);
    }

    /** @param  array<string, mixed>  $data */
    private function set(string $academyId, AcademyStatus $status, string $action, array $data): Academy
    {
        return DB::connection('pgsql_platform')->transaction(function () use ($academyId, $status, $action, $data) {
            $academy = Academy::query()->lockForUpdate()->findOrFail($academyId);
            if ($academy->status === $status) {
                return $academy;
            }
            $academy->status = $status;
            $academy->save();
            $this->audit->record($academy->id, $action, 'academy', $academy->id, $data + ['slug' => $academy->slug]);

            return $academy;
        });
    }
}
