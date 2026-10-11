<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Actions\RescheduleSession;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Money\Money;

/**
 * Demo enrolments through the real actions: hold, enrol (invoice, weekly
 * times, sessions), then each payment state an academy sees: paid, part
 * paid, due, overdue. Ziad, Yousef and Maryam started 26 days ago (one per
 * student look: middle school, a child, secondary school): about seven
 * lessons each, Ziad's teacher missed one (a makeup lot) and Maryam missed
 * one herself; renewal invoices went out on their own. They came in a few
 * minutes late here and there, once later still, and left once before the
 * end, so the journey's lesson stars differ (docs/architecture/02, section 18).
 */
class DemoEnrollmentsSeeder extends Seeder
{
    public function run(
        PlaceBookingHold $hold,
        EnrollStudent $enroll,
        BillingApi $billing,
        RecordParticipantJoin $join,
        RecordParticipantLeave $leave,
        ApplyAttendanceDeadlines $deadlines,
        MaterializeSessions $materialize,
        FundSessions $fund,
        CancelSession $cancel,
        RescheduleSession $reschedule,
    ): void {
        $user = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $subject = fn (string $en) => DB::table('subjects')->where('name_en', $en)->value('id');
        $admin = $user('owner');
        $accountant = $user('accountant') ?? $admin;
        $regions = collect($billing->regions())->keyBy(fn (RegionOption $r) => $r->currency);
        $realNow = CarbonImmutable::now();

        $enrol = function (string $teacher, string $student, string $subjectName, array $times, int $minutes, string $currency, CarbonImmutable $from) use ($user, $subject, $hold, $enroll, $billing, $regions, $admin, $realNow) {
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
            // Keep clear of the live board's lessons around the real now.
            $occurrences = array_values(array_filter($occurrences, fn (int $ts) => abs($ts - $realNow->getTimestamp()) > 3 * 3600));
            $plan = collect($billing->planOptions($minutes))->first(fn (PlanOption $p) => $p->sessionMinutes === $minutes && $p->sessionsPerWeek === count($times));
            $payer = DB::table('users')->where('email', "{$student}.guardian@demo.test")->value('id') ?? $user($student);
            $batch = $hold->handle($teacherId, $user($student), $occurrences, $minutes, $admin);

            return $enroll->handle($batch, $user($student), $subject($subjectName), new EnrollmentTerms($plan->id, $payer, $regions[$currency]->id), $admin);
        };

        // A month of history (the shift schedules have been in force for a month): enrolled 26 days ago,
        // paid, then each past lesson as it happened; a teacher who never came, or a student who did not.
        $history = function (string $teacher, string $student, string $subjectName, array $times, int $minutes, string $currency, string $reference, ?int $teacherMissed = null, ?int $studentMissed = null) use ($enrol, $user, $billing, $accountant, $join, $leave, $deadlines, $materialize, $fund, $realNow) {
            CarbonImmutable::setTestNow($realNow->subDays(26));
            $booked = $enrol($teacher, $student, $subjectName, $times, $minutes, $currency, CarbonImmutable::now()->addDay());
            $billing->recordPayment($booked->invoice->id, $currency === 'SAR' ? 'bank_transfer' : 'card_terminal', $booked->invoice->total, $reference, CarbonImmutable::now(), $accountant);

            $past = Session::query()->where('enrollment_id', $booked->enrollmentId)->where('ends_at', '<', $realNow->subHours(6))->orderBy('starts_at')->with('participants')->get()->take(7);
            // Minutes late to each lesson (within the share of the lesson that still completes it), and the one left early.
            $late = [1, 6, 1, 3, 1, $minutes > 30 ? 11 : 7, 1];
            foreach ($past->values() as $i => $session) {
                CarbonImmutable::setTestNow($session->starts_at->addMinute());
                if ($i !== $teacherMissed) {
                    $join->handle($session, $session->teacher_id);
                }
                if ($i !== $studentMissed) {
                    CarbonImmutable::setTestNow($session->starts_at->addMinutes($late[$i] ?? 1));
                    $join->handle($session->refresh(), $user($student));
                    if ($i === 4 && $i !== $teacherMissed) {
                        $leave->handle($session->refresh(), $user($student), $session->ends_at->subMinutes(6));
                    }
                }
                CarbonImmutable::setTestNow($i === $teacherMissed ? $session->starts_at->addMinutes(10) : $session->ends_at->addMinutes(11));
                $deadlines->handle();
            }
            CarbonImmutable::setTestNow();
            $materialize->handle([$booked->enrollmentId]);
            $fund->handle($booked->enrollmentId);
        };
        $history('khaled', 'ziad', 'Arabic grammar', [[7, '18:00'], [3, '18:00']], 45, 'SAR', 'SA-88213', teacherMissed: 2);
        $history('nour', 'yousef', 'Quran memorisation', [[6, '18:00'], [4, '19:00']], 30, 'GBP', 'TRX-40417');
        $history('sara', 'maryam', 'Tajweed', [[2, '17:00'], [7, '19:00']], 45, 'SAR', 'SA-90542', studentMissed: 3);

        // Everyone else starts the day after tomorrow, once a week.
        $from = $realNow->addDays(2);
        $series = [
            ['sara', 'adam', 'Arabic', 2, '19:00', 45, 'GBP', 'paid'],
            ['sara', 'hamza', 'Quran memorisation', 7, '20:00', 30, 'AED', 'overdue'],
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

        // Policy at work: Maryam cancels a lesson in good time (credit back, next lesson funded),
        // Adam cancels one too late (lost, teacher paid half), Layan moves her first lesson an hour later.
        $lesson = fn (string $student, int $nth) => Session::query()->where('type', 'regular')->where('status', 'scheduled')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user($student))->where('role', 'student'))
            ->orderBy('starts_at')->skip($nth)->first();
        $cancel->handle($lesson('maryam', 1), 'student', 'travel', 'مسافرة مع الأسرة', $admin);
        $late = $lesson('adam', 0);
        $cancel->handle($late, 'student', 'illness', null, $admin, at: $late->starts_at->subHours(3));
        $first = $lesson('layan', 0);
        $reschedule->handle($first, $first->starts_at->addHour(), 'student', $admin);
    }
}
