<?php

namespace Modules\Safeguarding\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;
use Modules\SharedKernel\Audit\AuditTrail;

/** Something new came up: a lead opens a closed concern again, with why. In the activity log. */
final class ReopenConcern
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $concernId, string $reason, string $actorId): Concern
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 5000) {
            throw ValidationException::withMessages(['reason' => __('safeguarding::concerns.errors.reason')]);
        }

        return DB::transaction(function () use ($concernId, $reason, $actorId) {
            $concern = Concern::query()->whereKey($concernId)->lockForUpdate()->firstOrFail();
            if ($concern->status !== ConcernStatus::Closed) {
                throw ValidationException::withMessages(['reason' => __('safeguarding::concerns.errors.not_closed')]);
            }
            $concern->update(['status' => ConcernStatus::Open, 'outcome' => null, 'closed_by' => null, 'closed_at' => null]);
            ConcernEntry::query()->create(['concern_id' => $concern->id, 'author_id' => $actorId, 'kind' => 'note', 'body' => $reason]);
            ConcernEntry::event($concern, 'reopened', $actorId);
            $this->audit->record('safeguarding.concern_reopened', 'concern', $concern->id, ['concern' => $concern->reference()]);

            return $concern;
        });
    }
}
