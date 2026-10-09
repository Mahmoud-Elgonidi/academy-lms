<?php

namespace Modules\Classroom\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The person's agreement to lesson recordings (given or withdrawn, by whom)
 * and the recordings they opened. Both stay when erasing: a consent is the
 * academy's evidence, the openings its record of who watched; the
 * recordings themselves leave after the academy's days anyway.
 */
final class RecordingsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'recordings';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([
            'recording_consents' => DB::table('recording_consents')->where(fn ($q) => $q->where('person_id', $userId)->orWhere('given_by', $userId))
                ->orderBy('granted_at')->get(['role', 'via', 'version', 'granted_at', 'withdrawn_at'])->map(fn (object $r) => (array) $r)->all(),
            'recordings_watched' => DB::table('recording_access_logs')->where('user_id', $userId)->orderBy('opened_at')
                ->get(['recording_id', 'as', 'opened_at'])->map(fn (object $r) => (array) $r)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return [];
    }
}
