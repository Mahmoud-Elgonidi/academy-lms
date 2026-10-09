<?php

namespace Modules\Safeguarding\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Safeguarding\Enums\ConcernOutcome;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Events\ConcernClosed;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A lead closes a concern with its outcome (for the academy's record) and,
 * if they want, a reply the reporter reads (never the notes or the outcome).
 * In the activity log by number and outcome.
 */
final class CloseConcern
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $concernId, string $outcome, ?string $reply, ?string $note, string $actorId): Concern
    {
        $result = ConcernOutcome::tryFrom($outcome) ?? throw ValidationException::withMessages(['outcome' => __('safeguarding::concerns.errors.outcome')]);
        $reply = trim((string) $reply) ?: null;
        $note = trim((string) $note) ?: null;
        if ($reply !== null && mb_strlen($reply) > 2000) {
            throw ValidationException::withMessages(['reply' => __('validation.max.string', ['attribute' => __('safeguarding::concerns.fields.reply'), 'max' => 2000])]);
        }
        if ($note === null || mb_strlen($note) < 5 || mb_strlen($note) > 5000) {
            throw ValidationException::withMessages(['note' => __('safeguarding::concerns.errors.close_note')]);
        }

        return DB::transaction(function () use ($concernId, $result, $reply, $note, $actorId) {
            $concern = Concern::query()->whereKey($concernId)->lockForUpdate()->firstOrFail();
            if ($concern->status === ConcernStatus::Closed) {
                throw ValidationException::withMessages(['outcome' => __('safeguarding::concerns.errors.closed')]);
            }
            $now = CarbonImmutable::now();
            $concern->update([
                'status' => ConcernStatus::Closed, 'outcome' => $result, 'reporter_reply' => $reply, 'closed_by' => $actorId, 'closed_at' => $now,
                'acknowledged_by' => $concern->acknowledged_by ?? $actorId, 'acknowledged_at' => $concern->acknowledged_at ?? $now,
            ]);
            ConcernEntry::query()->create(['concern_id' => $concern->id, 'author_id' => $actorId, 'kind' => 'note', 'body' => $note]);
            ConcernEntry::event($concern, 'closed', $actorId, ['outcome' => $result->value, 'replied' => $reply !== null]);
            $this->audit->record('safeguarding.concern_closed', 'concern', $concern->id, ['concern' => $concern->reference(), 'concern_outcome' => $result->value]);
            DB::afterCommit(fn () => event(new ConcernClosed($concern->academy_id, $concern->id)));

            return $concern;
        });
    }
}
