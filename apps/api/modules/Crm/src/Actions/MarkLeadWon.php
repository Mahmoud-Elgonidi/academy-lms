<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Crm\Contracts\Events\LeadConverted;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;

/** A lead's learner was enrolled (Enrollment's EnrollmentCreated): the lead is won, and the enrolment is kept on it. */
final class MarkLeadWon
{
    public function handle(string $studentId, string $enrollmentId, ?string $teacherId): ?Lead
    {
        $lead = Lead::query()->where('learner_id', $studentId)
            ->whereIn('stage', array_map(fn (LeadStage $s) => $s->value, LeadStage::OPEN))->latest('created_at')->first();
        if ($lead === null) {
            return null;
        }

        return DB::transaction(function () use ($lead, $enrollmentId, $teacherId) {
            $lead->forceFill(['enrollment_id' => $enrollmentId])->save();
            $lead->moveTo(LeadStage::Won, null, log: false);
            LeadActivity::record($lead, 'won', null, ['enrollment' => $enrollmentId, 'teacher' => $teacherId], null);
            $academyId = $lead->academy_id;
            DB::afterCommit(fn () => event(new LeadConverted($academyId, $lead->id, $enrollmentId, $teacherId)));

            return $lead;
        });
    }
}
