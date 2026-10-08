<?php

namespace Modules\Billing\Discounts;

use Illuminate\Support\Facades\DB;

/**
 * Which child a student is for their payer (docs/architecture/02, section 3,
 * "خصم الإخوات"): the students on the payer's account with a course that
 * is still on, in the order their first course there began. The first pays
 * in full; a student not on the account yet would come after the others.
 */
final class SiblingRank
{
    public function of(string $billingAccountId, string $studentId): int
    {
        $students = $this->students($billingAccountId);
        $place = array_search($studentId, $students, true);

        return $place === false ? count($students) + 1 : $place + 1;
    }

    /** For a payer who may not have an account in this currency yet. */
    public function forPayer(string $payerId, string $currency, string $studentId): int
    {
        $account = DB::table('billing_accounts')->where('payer_id', $payerId)->where('currency', $currency)->value('id');

        return $account === null ? 1 : $this->of((string) $account, $studentId);
    }

    /** @return list<string> the account's students, first child first */
    private function students(string $billingAccountId): array
    {
        return DB::table('subscriptions as s')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->where('s.billing_account_id', $billingAccountId)
            ->where('s.status', '<>', 'cancelled')
            ->where('e.status', '<>', 'ended')
            ->groupBy('e.student_id')
            ->orderByRaw('min(s.created_at)')->orderBy('e.student_id')
            ->pluck('e.student_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
