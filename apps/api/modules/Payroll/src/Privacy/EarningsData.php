<?php

namespace Modules\Payroll\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * A teacher's pay, line by line (what each lesson earned and every
 * correction). Nothing is erased here: pay is kept as labour and tax law
 * ask, and the lines never change anyway.
 */
final class EarningsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'earnings';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([
            'earnings' => DB::table('teacher_earnings')->where('teacher_id', $userId)->orderBy('period')->orderBy('created_at')
                ->get(['period', 'kind', 'lesson_on', 'outcome', 'session_type', 'booked_min', 'pay_pct', 'unit', 'rate_minor', 'amount_minor', 'currency', 'note', 'created_at'])
                ->map(fn (object $e) => (array) $e)->all(),
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
