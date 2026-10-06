<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Money\Money;

/**
 * Demo enrolments through the real actions: hold, enrol (invoice, weekly
 * times, sessions), then each payment state an academy sees: paid, part
 * paid, due, overdue. Ziad started 26 days ago: six lessons taught, one
 * teacher no-show (a makeup lot), and his renewal invoice went out on its own.
 */
class DemoEnrollmentsSeeder extends Seeder
{
    public function run(
        PlaceBookingHold $hold,
        EnrollStudent $enroll,
        BillingApi $billing,
        RecordParticipantJoin $join,
        ApplyAttendanceDeadlines $deadlines,
        MaterializeSessions $materialize,
        FundSessions $fund,
    ): void {
        $user = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $subject = fn (string $en) => DB::table('subjects')->where('name_en', $en)->value('id');
        $admin = $user('owner');
        $accountant = $user('accountant') ?? $admin;
        $regions = collect($billing->regions())->keyBy(fn (RegionOption $r) => $r->currency);
        $realNow = CarbonImmutable::now();

        $enrol = function (string $teacher, string $student, string $subjectName, array $times, int $minutes, string $currency, CarbonImmutable $from) use ($user, $subject, $hold, $enroll, $billing, $regions, $admin) {
            $teacherId = $user($teacher);
            $tz = DB::table('users')->where('id', $teacherId)->value('timezone');
            $occurrences = [];
            foreach ($times as [$weekday, $clock]) {
                $day = $from->setTimezone($tz)->startOfDay();
                $day = $day->addDays(($weekday - $day->dayOfWeekIso + 7) % 7);
                for ($i = 0; $i < 8; $i++, $day = $day->addWeek()) {
                    $occurrences[] = CarbonImmutable::parse($day->toDateString().' '.$clock, $tz)->getTimestamp();
                }
            }
            $plan = collect($billing->planOptions($minutes))->first(fn (PlanOption $p) => $p->sessionMinutes === $minutes && $p->sessionsPerWeek === count($times));
            $payer = DB::table('users')->where('email', "{$student}.guardian@demo.test")->value('id') ?? $user($student);
            $batch = $hold->handle($teacherId, $user($student), $occurrences, $minutes, $admin);

            return $enroll->handle($batch, $user($student), $subject($subjectName), new EnrollmentTerms($plan->id, $payer, $regions[$currency]->id), $admin);
        };

        // Ziad, from 26 days ago (the shift schedules have been in force for a month).
        CarbonImmutable::setTestNow($realNow->subDays(26));
        $ziad = $enrol('khaled', 'ziad', 'Arabic grammar', [[7, '18:00'], [3, '18:00']], 45, 'SAR', CarbonImmutable::now()->addDay());
        $billing->recordPayment($ziad->invoice->id, 'bank_transfer', $ziad->invoice->total, 'SA-88213', CarbonImmutable::now(), $accountant);

        $past = Session::query()->where('enrollment_id', $ziad->enrollmentId)->where('ends_at', '<', $realNow->subHours(6))->orderBy('starts_at')->with('participants')->get()->take(7);
        foreach ($past->values() as $i => $session) {
            CarbonImmutable::setTestNow($session->starts_at->addMinute());
            if ($i !== 2) {
                $join->handle($session, $session->teacher_id);
            }
            $join->handle($session->refresh(), $user('ziad'));
            CarbonImmutable::setTestNow($i === 2 ? $session->starts_at->addMinutes(10) : $session->ends_at->addMinutes(11));
            $deadlines->handle();
        }
        CarbonImmutable::setTestNow();
        $materialize->handle([$ziad->enrollmentId]);
        $fund->handle($ziad->enrollmentId);

        // Everyone else starts the day after tomorrow, once a week.
        $from = $realNow->addDays(2);
        $series = [
            ['sara', 'maryam', 'Tajweed', 2, '17:00', 45, 'SAR', 'paid'],
            ['sara', 'adam', 'Arabic', 2, '19:00', 45, 'GBP', 'paid'],
            ['sara', 'hamza', 'Quran memorisation', 7, '20:00', 30, 'AED', 'overdue'],
            ['nour', 'yousef', 'Quran memorisation', 6, '18:00', 30, 'GBP', 'paid'],
            ['mahmoud', 'omar', 'Tajweed', 1, '19:00', 45, 'SAR', 'due'],
            ['mona', 'salma', 'Arabic', 7, '19:00', 45, 'USD', 'partial'],
            ['ahmed', 'layan', 'English', 2, '20:00', 30, 'SAR', 'paid'],
        ];
        foreach ($series as [$teacher, $student, $subjectName, $weekday, $clock, $minutes, $currency, $state]) {
            $booked = $enrol($teacher, $student, $subjectName, [[$weekday, $clock]], $minutes, $currency, $from);
            $total = $booked->invoice->total;
            $issued = $state === 'overdue' ? $realNow->subDays(6) : $realNow->subDays(2);
            DB::table('invoices')->where('id', $booked->invoice->id)->update(['issued_at' => $issued, 'due_at' => $issued->addDays(3)]);

            match ($state) {
                'paid' => $billing->recordPayment($booked->invoice->id, $currency === 'SAR' ? 'bank_transfer' : 'card_terminal', $total, 'TRX-'.random_int(10000, 99999), $realNow->subHours(random_int(2, 30)), $accountant),
                'partial' => $billing->recordPayment($booked->invoice->id, 'wallet', Money::of(intdiv($total->minor, 2), $total->currency), null, $realNow->subHours(3), $accountant),
                default => null,
            };
        }
    }
}
