<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Models\SessionReport;

/** A supervisor reads a handed-in report and closes it, with a note for the teacher if needed. */
final class ReviewSessionReport
{
    public function handle(SessionReport $report, string $actorId, ?string $note): SessionReport
    {
        if ($report->status !== 'submitted') {
            throw ValidationException::withMessages(['report' => __('learning::reports.errors.not_submitted')]);
        }

        $report->fill(['status' => 'reviewed', 'reviewed_by' => $actorId, 'reviewed_at' => CarbonImmutable::now(), 'review_note' => $note ?: null])->save();

        return $report;
    }
}
