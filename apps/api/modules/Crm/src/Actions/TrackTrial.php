<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Contracts\Events\TrialCompleted;
use Modules\Crm\Contracts\Events\TrialMissed;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;

/**
 * A lead's trial lesson as Scheduling lives it (docs/architecture/02, "الحصة
 * التجريبية"): booked for their learner, moved, cancelled, then attended
 * (the teacher is asked to assess it) or not (sales follow up). Each step
 * goes on the timeline and moves the lead. A trial booked for a student who
 * is no open lead's learner is not the CRM's.
 */
final class TrackTrial
{
    public function booked(string $sessionId, string $studentId, string $teacherId, CarbonImmutable $startsAt, ?string $actorId): ?LeadTrial
    {
        $lead = Lead::query()->where('learner_id', $studentId)
            ->whereIn('stage', array_map(fn (LeadStage $s) => $s->value, LeadStage::OPEN))->latest('created_at')->first();
        if ($lead === null) {
            return null;
        }

        return DB::transaction(function () use ($lead, $sessionId, $teacherId, $startsAt, $actorId) {
            $trial = LeadTrial::query()->firstOrCreate(['session_id' => $sessionId], ['lead_id' => $lead->id, 'teacher_id' => $teacherId, 'starts_at' => $startsAt]);
            LeadActivity::record($lead, 'trial_booked', null, ['teacher' => $teacherId, 'at' => $startsAt->toIso8601ZuluString()], $actorId);
            $lead->moveTo(LeadStage::TrialBooked, $actorId, log: false);

            return $trial;
        });
    }

    public function moved(string $sessionId, ?string $actorId): void
    {
        $trial = $this->open($sessionId);
        $session = DB::table('sessions')->where('id', $sessionId)->first(['starts_at', 'teacher_id']);
        if ($trial === null || $session === null) {
            return;
        }
        $to = CarbonImmutable::parse($session->starts_at)->utc();
        DB::transaction(function () use ($trial, $to, $session, $actorId) {
            $from = $trial->starts_at;
            $trial->forceFill(['starts_at' => $to, 'teacher_id' => $session->teacher_id])->save();
            LeadActivity::record($trial->lead, 'trial_moved', null, ['from' => $from->toIso8601ZuluString(), 'at' => $to->toIso8601ZuluString()], $actorId);
        });
    }

    public function cancelled(string $sessionId, string $reason, ?string $actorId): void
    {
        $trial = $this->open($sessionId);
        if ($trial === null) {
            return;
        }
        DB::transaction(function () use ($trial, $reason, $actorId) {
            $trial->forceFill(['status' => 'cancelled'])->save();
            LeadActivity::record($trial->lead, 'trial_cancelled', null, ['reason' => $reason], $actorId);
            $this->backToContacted($trial->lead, $actorId);
        });
    }

    /** The lesson closed: attended, or not (absent, the teacher away). */
    public function finished(string $sessionId, string $outcome): void
    {
        $trial = $this->open($sessionId);
        if ($trial === null) {
            return;
        }
        $lead = $trial->lead;
        $academyId = $lead->academy_id;
        DB::transaction(function () use ($trial, $lead, $outcome, $academyId) {
            if ($outcome === 'completed') {
                $trial->forceFill(['status' => 'attended'])->save();
                LeadActivity::record($lead, 'trial_attended', null, ['teacher' => $trial->teacher_id], null);
                if ($lead->stage === LeadStage::TrialBooked) {
                    $lead->moveTo(LeadStage::Attended, null, log: false);
                }
                DB::afterCommit(fn () => event(new TrialCompleted($academyId, $trial->id, $lead->id, $trial->teacher_id)));

                return;
            }
            $trial->forceFill(['status' => 'missed'])->save();
            LeadActivity::record($lead, 'trial_missed', null, ['outcome' => $outcome], null);
            $this->backToContacted($lead, null);
            DB::afterCommit(fn () => event(new TrialMissed($academyId, $trial->id, $lead->id, $outcome)));
        });
    }

    private function open(string $sessionId): ?LeadTrial
    {
        return LeadTrial::query()->with('lead')->where('session_id', $sessionId)->where('status', 'booked')->first();
    }

    /** No trial ahead any more: the lead waits for the next step with sales. */
    private function backToContacted(Lead $lead, ?string $actorId): void
    {
        $ahead = LeadTrial::query()->where('lead_id', $lead->id)->where('status', 'booked')->exists();
        if (! $ahead && $lead->stage === LeadStage::TrialBooked) {
            $lead->moveTo(LeadStage::Contacted, $actorId);
        }
    }
}
