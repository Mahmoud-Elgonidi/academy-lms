<?php

namespace Modules\Billing\Discounts;

use Illuminate\Support\Facades\DB;

/**
 * Which child a student is for their payer (docs/architecture/02, section 3,
 * "خصم الإخوات"): the students on the payer's account with a course that
 * is still on (a paused one is; an ended one is not), in the order their
 * first course there began. The first pays in full; a student not on the
 * account yet would come after the others. A shared package counts while
 * any of its courses is on, the one it was opened for or a later one.
 */
final class SiblingRank
{
    public function of(string $billingAccountId, string $studentId): int
    {
        return self::place($this->students([$billingAccountId])[$billingAccountId] ?? [], $studentId);
    }

    /**
     * The same for many accounts' children at once, in one read.
     *
     * @param  list<array{0: string, 1: string}>  $pairs  [billing account, student]
     * @return array<string, int> "account|student" => place
     */
    public function ofMany(array $pairs): array
    {
        $students = $this->students(array_values(array_unique(array_column($pairs, 0))));
        $out = [];
        foreach ($pairs as [$account, $student]) {
            $out[$account.'|'.$student] = self::place($students[$account] ?? [], $student);
        }

        return $out;
    }

    /** For a payer who may not have an account in this currency yet. */
    public function forPayer(string $payerId, string $currency, string $studentId): int
    {
        $account = DB::table('billing_accounts')->where('payer_id', $payerId)->where('currency', $currency)->value('id');

        return $account === null ? 1 : $this->of((string) $account, $studentId);
    }

    /** @param  list<string>  $students  the account's, first child first */
    private static function place(array $students, string $studentId): int
    {
        $place = array_search($studentId, $students, true);

        return $place === false ? count($students) + 1 : $place + 1;
    }

    /**
     * @param  list<string>  $billingAccountIds
     * @return array<string, list<string>> each account's students, first child first
     */
    private function students(array $billingAccountIds): array
    {
        $out = [];
        DB::table('subscriptions as s')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')
                ->on(fn ($o) => $o->on('e.id', '=', 's.enrollment_id')->orOn('e.credit_account_id', '=', 's.credit_account_id')))
            ->whereIn('s.billing_account_id', $billingAccountIds)
            ->where('s.status', '<>', 'cancelled')
            ->where('e.status', '<>', 'ended')
            ->groupBy('s.billing_account_id', 'e.student_id')
            ->orderBy('s.billing_account_id')->orderByRaw('min(s.created_at)')->orderBy('e.student_id')
            ->get(['s.billing_account_id', 'e.student_id'])
            ->each(function (object $r) use (&$out) {
                $out[(string) $r->billing_account_id][] = (string) $r->student_id;
            });

        return $out;
    }
}
