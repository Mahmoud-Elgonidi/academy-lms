<?php

namespace Modules\Safeguarding\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;
use Modules\SharedKernel\Audit\AuditTrail;

/** A lead takes a new concern: it is being looked into (the reporter sees that), and no reminder goes out. */
final class AcknowledgeConcern
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $concernId, string $actorId): Concern
    {
        return DB::transaction(function () use ($concernId, $actorId) {
            $concern = Concern::query()->whereKey($concernId)->lockForUpdate()->firstOrFail();
            if ($concern->status !== ConcernStatus::New) {
                return $concern;
            }
            $concern->update(['status' => ConcernStatus::Open, 'acknowledged_by' => $actorId, 'acknowledged_at' => CarbonImmutable::now()]);
            ConcernEntry::event($concern, 'acknowledged', $actorId);
            $this->audit->record('safeguarding.concern_acknowledged', 'concern', $concern->id, ['concern' => $concern->reference()]);

            return $concern;
        });
    }
}
