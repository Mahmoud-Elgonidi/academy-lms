<?php

namespace Modules\Payroll\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Actions\AddPayAdjustment;
use Modules\Payroll\Actions\ClosePayrollMonth;
use Modules\Payroll\Actions\RecalculatePayrollMonth;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Payroll\Support\PayrollMonths;
use Modules\SharedKernel\Audit\AuditActor;

/**
 * Payroll in the demo, set up the way an academy would: a default card in
 * riyals (by the hour, Quran memorisation a little higher, trials a flat
 * amount) and a card in Egyptian pounds for the teachers paid in Egypt; the
 * lessons that took place priced from them; the months before closed in
 * order, last month with a bonus and a deduction (so its teachers were told
 * their totals), and a bonus in the month still open.
 */
final class DemoPayrollSeeder extends Seeder
{
    public function run(SaveRateCard $cards, RecalculatePayrollMonth $recalculate, AddPayAdjustment $adjust, ClosePayrollMonth $close, PayrollMonths $months): void
    {
        $accountant = DB::table('users')->where('email', 'accountant@demo.test')->value('id');
        // Payroll is the accountant's work: their name is on it in the activity log.
        $before = AuditActor::current();
        if ($accountant !== null) {
            AuditActor::use(new AuditActor('user', $accountant, (string) DB::table('users')->where('id', $accountant)->value('name')));
        }
        try {
            $this->seedPayroll($cards, $recalculate, $adjust, $close, $months, $accountant);
        } finally {
            AuditActor::use($before);
        }
    }

    private function seedPayroll(SaveRateCard $cards, RecalculatePayrollMonth $recalculate, AddPayAdjustment $adjust, ClosePayrollMonth $close, PayrollMonths $months, ?string $accountant): void
    {
        $teacher = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $quran = DB::table('subjects')->where('name_en', 'Quran memorisation')->value('id');

        $cards->handle(null, ['name' => 'الجدول الأساسي', 'currency' => 'SAR', 'is_default' => true, 'lines' => array_values(array_filter([
            ['unit' => 'hour', 'amount' => '60'],
            $quran ? ['subject_id' => $quran, 'unit' => 'hour', 'amount' => '70'] : null,
            ['session_type' => 'trial', 'unit' => 'lesson', 'amount' => '25'],
        ]))]);
        $cards->handle(null, ['name' => 'معلّمو مصر', 'currency' => 'EGP', 'lines' => [
            ['unit' => 'hour', 'amount' => '400'],
            ['session_type' => 'trial', 'unit' => 'lesson', 'amount' => '150'],
        ], 'teachers' => array_values(array_filter([$teacher('khaled@demo.test'), $teacher('nour@demo.test')]))]);

        // Every month with lessons, from the first (the demo's past months) to this one.
        $current = $months->current();
        $last = $current->subMonth();
        $first = DB::table('sessions')->whereIn('status', ['completed', 'student_absent', 'teacher_absent', 'cancelled'])->min('starts_at');
        $past = [];
        for ($month = $first ? $months->of(CarbonImmutable::parse($first))->min($current->subMonths(2)) : $current->subMonths(2); $month->lessThanOrEqualTo($current); $month = $month->addMonth()) {
            $recalculate->handle($month, reprice: false, actorId: $accountant);
            if ($month->lessThan($last)) {
                $past[] = $month;
            }
        }

        if ($accountant === null) {
            return;
        }
        if ($sara = $teacher('sara@demo.test')) {
            $adjust->handle($sara, 'bonus', '100', 'مكافأة التزام: لم تتأخر أي حصة والتقارير كلها في موعدها.', $accountant, $last);
        }
        if ($khaled = $teacher('khaled@demo.test')) {
            $adjust->handle($khaled, 'deduction', '200', 'غياب عن حصة من دون إبلاغ (القاعدة 9).', $accountant, $last);
        }
        if ($nour = $teacher('nour@demo.test')) {
            $adjust->handle($nour, 'bonus', '150', 'غطّت حصة بديلة لزميلة في إجازة.', $accountant, $current);
        }

        // Months close in order: the ones before last first, those that paid anything.
        foreach ($past as $month) {
            if (DB::table('teacher_earnings')->whereDate('period', $month->format('Y-m-d'))->exists()) {
                $close->handle($month, $accountant);
            }
        }
        $close->handle($last, $accountant);
    }
}
