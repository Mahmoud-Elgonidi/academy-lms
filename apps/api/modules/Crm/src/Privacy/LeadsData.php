<?php

namespace Modules\Crm\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The enquiry a person came through (a lead whose learner or contact they
 * became): what they told the academy then, and the reports of its free
 * trials as the family read them (the level, strengths, what to work on, the
 * teacher's words, the offer's end). Erasing blanks the contact details, the
 * learner's name, the notes on it and what the trials' assessments said;
 * the lead and its trials stay as numbers in the academy's sales figures.
 */
final class LeadsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'enquiries';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([
            'enquiries' => $this->leads($userId)->orderBy('created_at')
                ->get(['contact_name', 'phone_e164', 'email', 'country', 'learner_name', 'learner_birth_year', 'preferred_days', 'preferred_from', 'preferred_to', 'teacher_gender', 'source', 'stage', 'created_at'])
                ->map(fn (object $l) => ['preferred_days' => json_decode((string) $l->preferred_days, true)] + (array) $l)->all(),
            'trial_reports' => DB::table('lead_trials as t')->joinSub($this->leads($userId)->select('id'), 'l', 'l.id', '=', 't.lead_id')
                ->orderBy('t.starts_at')
                ->get(['t.starts_at', 't.status', 't.kind', 't.level', 't.strengths', 't.to_work_on', 't.family_note', 't.report_sent_at', 't.offer_ends_at'])
                ->map(fn (object $t) => (array) $t)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        $ids = $this->leads($userId)->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        DB::table('lead_activities')->whereIn('lead_id', $ids)->whereNotNull('body')->update(['body' => null]);
        DB::table('lead_trials')->whereIn('lead_id', $ids)->update([
            'level' => null, 'strengths' => null, 'to_work_on' => null, 'family_note' => null, 'assessment' => null, 'updated_at' => now(),
        ]);

        return ['enquiries' => DB::table('leads')->whereIn('id', $ids)->update([
            'contact_name' => '—', 'phone_e164' => null, 'email' => null, 'learner_name' => null, 'learner_birth_year' => null,
            'notes' => null, 'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'updated_at' => now(),
        ])];
    }

    private function leads(string $userId)
    {
        return DB::table('leads')->where(fn ($q) => $q->where('learner_id', $userId)->orWhere('contact_id', $userId));
    }
}
