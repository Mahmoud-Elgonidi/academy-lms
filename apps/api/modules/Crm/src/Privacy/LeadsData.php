<?php

namespace Modules\Crm\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The enquiry a person came through (a lead whose learner or contact they
 * became): what they told the academy then. Erasing blanks the contact
 * details, the learner's name and the notes on it; the lead stays as a
 * number in the academy's sales figures.
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
                ->get(['contact_name', 'phone_e164', 'email', 'country', 'learner_name', 'learner_birth_year', 'preferred_days', 'source', 'stage', 'created_at'])
                ->map(fn (object $l) => ['preferred_days' => json_decode((string) $l->preferred_days, true)] + (array) $l)->all(),
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
