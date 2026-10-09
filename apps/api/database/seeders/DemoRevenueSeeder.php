<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\Session;

/**
 * Five months of the demo academy's money, so the revenue screen has a
 * past: five students who started between five months and twelve weeks ago
 * (a child's Quran with Mahmoud, an adult's Arabic with Khaled, a teenager's
 * Arabic once a week with Mona, who also bought two extra lessons, a child's
 * Tajweed with Nour, an adult's English with Ahmed), spread so their
 * renewals fall all through the month. Every lesson as it happened (teacher
 * and student on time, closed by the sweeper), and each renewal paid a
 * little after it went out: by bank transfer, online with the demo card, by
 * STC Pay or in cash. Their courses carry on into the weeks ahead like any
 * other. Runs after the teachers' schedules and before the other enrolments,
 * so invoice numbers follow the calendar.
 */
final class DemoRevenueSeeder extends Seeder
{
    /** @var array<string, array{at: CarbonImmutable, course: string}> invoice => when it gets paid, and by which course's payer */
    private array $queue = [];

    /** @var array<string, true> */
    private array $seen = [];

    /** @var array<string, array<string, mixed>> */
    private array $courses = [];

    private int $receipt = 4100;

    public function run(
        IdentityApi $identity,
        PeopleApi $people,
        GrantConsent $consent,
        PlaceBookingHold $hold,
        EnrollStudent $enroll,
        BillingApi $billing,
        RecordParticipantJoin $join,
        ApplyAttendanceDeadlines $deadlines,
        MaterializeSessions $materialize,
        FundSessions $fund,
        GatewayManager $gateways,
        SaveGatewayAccount $saveGateway,
        StartCheckout $start,
        SettleCheckout $settle,
    ): void {
        $user = fn (string $key) => (string) DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $owner = $user('owner');
        $accountant = $user('accountant') ?: $owner;
        $password = (string) config('app.demo.password');
        $tz = 'Asia/Riyadh';
        $realNow = CarbonImmutable::now();
        $cutoff = $realNow->subHours(6);
        $sar = collect($billing->regions())->first(fn (RegionOption $r) => $r->currency === 'SAR');
        $plan = fn (int $minutes, int $perWeek) => collect($billing->planOptions($minutes))
            ->first(fn (PlanOption $p) => ! $p->shared && $p->sessionMinutes === $minutes && $p->sessionsPerWeek === $perWeek);
        if ($sar === null || $plan(45, 2) === null || $plan(30, 2) === null || $plan(30, 1) === null) {
            return;
        }
        // The demo card, for the online payments (the same account DemoPaymentsSeeder shows later).
        $card = $gateways->demoAllowed()
            ? (GatewayAccount::query()->where('gateway', 'demo')->first() ?? $saveGateway->handle(null, ['gateway' => 'demo', 'label' => 'كارت أو مدى (تجريبي)', 'mode' => 'test'], $owner))
            : null;

        // Who, with whom, when (teacher's clock, ISO weekday), how they pay and how soon after the invoice.
        $specs = [
            'fahd' => ['name' => 'فهد العتيبي', 'age' => 11, 'guardian' => ['abdullah.otaibi', 'عبدالله العتيبي', 'father'], 'teacher' => 'mahmoud', 'subject' => 'Quran memorisation',
                'times' => [[1, '21:00'], [6, '21:00']], 'minutes' => 45, 'days' => 164, 'method' => 'bank_transfer', 'after' => 20],
            'reem' => ['name' => 'ريم القحطاني', 'age' => 27, 'guardian' => null, 'teacher' => 'khaled', 'subject' => 'Arabic',
                'times' => [[7, '15:00'], [2, '15:00']], 'minutes' => 30, 'days' => 151, 'method' => 'online', 'after' => 3],
            'lulwa' => ['name' => 'لولوة المطيري', 'age' => 16, 'guardian' => ['nawal.mutairi', 'نوال المطيري', 'mother'], 'teacher' => 'mona', 'subject' => 'Arabic',
                'times' => [[4, '21:00']], 'minutes' => 30, 'days' => 112, 'method' => 'cash', 'after' => 40, 'extra' => 50],
            'tamim' => ['name' => 'تميم الدوسري', 'age' => 9, 'guardian' => ['munira.dosari', 'منيرة الدوسري', 'mother'], 'teacher' => 'nour', 'subject' => 'Tajweed',
                'times' => [[2, '21:00'], [6, '21:00']], 'minutes' => 30, 'days' => 119, 'method' => 'wallet', 'after' => 26],
            'faisal' => ['name' => 'فيصل الزهراني', 'age' => 31, 'guardian' => null, 'teacher' => 'ahmed', 'subject' => 'English',
                'times' => [[2, '22:00'], [4, '22:00']], 'minutes' => 30, 'days' => 84, 'method' => 'online', 'after' => 1],
        ];

        foreach ($specs as $key => $spec) {
            $student = $identity->createUser(new NewUser(name: $spec['name'], roles: [Role::Student], email: "{$key}@demo.test", password: $password, locale: 'ar', timezone: $tz));
            $people->saveStudentProfile($student, $realNow->subYears($spec['age'])->subMonths(4)->startOfDay(), null);
            $payer = $student;
            if ($spec['guardian'] !== null) {
                [$email, $name, $relation] = $spec['guardian'];
                $payer = $identity->createUser(new NewUser(name: $name, roles: [Role::Guardian], email: "{$email}@demo.test", password: $password, locale: 'ar', timezone: $tz));
                $people->linkGuardian($payer, $student, $relation);
                $consent->handle($student, $payer, 'family', '203.0.113.52', 'Demo');
            }
            $this->courses[$key] = $spec + ['student' => $student, 'payer' => $payer, 'starts' => CarbonImmutable::parse($realNow->subDays($spec['days'])->setTimezone($tz)->toDateString().' 10:00', $tz)];
        }

        $pay = function (string $invoiceId, string $course) use ($billing, $accountant, $card, $start, $settle): void {
            $invoice = Invoice::query()->find($invoiceId);
            if ($invoice === null || $invoice->status !== 'issued') {
                return;
            }
            $c = $this->courses[$course];
            if ($c['method'] === 'online' && $card !== null) {
                $checkout = $start->handle($invoice, $card, $c['payer'], 'family');
                DemoGateway::decide((string) $checkout->provider_ref, pay: true);
                $settle->handle($checkout);

                return;
            }
            $method = $c['method'] === 'online' ? 'card_terminal' : $c['method'];
            $reference = match ($method) {
                'bank_transfer' => 'SA-'.(70000 + $this->receipt++),
                'wallet' => 'STC-'.(880000 + $this->receipt++),
                default => 'R-'.$this->receipt++,
            };
            $billing->recordPayment($invoice->id, $method, $invoice->outstanding(), $reference, CarbonImmutable::now(), $accountant);
        };
        // Invoices that went out since last looked at are paid by their payer's habit.
        $notice = function () {
            foreach ($this->courses as $key => $c) {
                if (! isset($c['enrollment'])) {
                    continue;
                }
                $issued = Invoice::query()->where('status', 'issued')
                    ->whereIn('billing_account_id', DB::table('billing_accounts')->where('payer_id', $c['payer'])->select('id'))
                    ->orderBy('issued_at')->get(['id', 'issued_at']);
                foreach ($issued as $invoice) {
                    if (! isset($this->seen[$invoice->id])) {
                        $this->seen[$invoice->id] = true;
                        $at = CarbonImmutable::now()->addHours($c['after'])->setTimezone('Asia/Riyadh');
                        $this->queue[$invoice->id] = ['at' => $at->hour < 9 ? $at->setTime(9, $at->minute) : $at, 'course' => $key];   // nobody pays at night
                    }
                }
            }
        };
        $payDue = function (CarbonImmutable $until) use ($pay) {
            uasort($this->queue, fn (array $a, array $b) => $a['at'] <=> $b['at']);
            foreach ($this->queue as $invoiceId => $due) {
                if ($due['at']->greaterThan($until)) {
                    break;
                }
                CarbonImmutable::setTestNow($due['at']);
                $pay($invoiceId, $due['course']);
                unset($this->queue[$invoiceId]);
            }
        };
        $keepAhead = function () use ($materialize, $fund) {
            $ids = array_values(array_filter(array_map(fn (array $c) => $c['enrollment'] ?? null, $this->courses)));
            if ($ids !== []) {
                $materialize->handle($ids);
                foreach ($ids as $id) {
                    $fund->handle($id);
                }
            }
        };

        $enrol = function (string $key) use ($user, $hold, $enroll, $plan, $sar, $owner, $notice) {
            // Enrolled from Find a teacher: the first eight weeks held, the invoice out, paid by the payer's habit.
            $c = $this->courses[$key];
            $teacherId = $user($c['teacher']);
            $teacherTz = (string) DB::table('users')->where('id', $teacherId)->value('timezone');
            $occurrences = [];
            foreach ($c['times'] as [$weekday, $clock]) {
                $first = $c['starts']->addDay()->setTimezone($teacherTz)->startOfDay();
                $first = $first->addDays(($weekday - $first->dayOfWeekIso + 7) % 7);
                for ($i = 0; $i < 8; $i++) {
                    $occurrences[] = CarbonImmutable::parse($first->addWeeks($i)->toDateString().' '.$clock, $teacherTz)->getTimestamp();
                }
            }
            $subject = (string) DB::table('subjects')->where('name_en', $c['subject'])->value('id');
            $batch = $hold->handle($teacherId, $c['student'], $occurrences, $c['minutes'], $owner);
            $booked = $enroll->handle($batch, $c['student'], $subject, new EnrollmentTerms($plan($c['minutes'], count($c['times']))->id, $c['payer'], $sar->id), $owner);
            $this->courses[$key]['enrollment'] = $booked->enrollmentId;
            $notice();
        };

        $day = min(array_map(fn (array $c) => $c['starts'], $this->courses))->setTimezone($tz)->startOfDay();
        while ($day->lessThan($cutoff)) {
            $next = $day->addDay();

            // The morning: lessons generated ahead on Saturdays, new students, a purchase; what was paid before each.
            $events = [];
            if ($day->dayOfWeekIso === 6) {
                $events[] = [$day->addHours(8), $keepAhead];
            }
            foreach ($this->courses as $key => $c) {
                if (! isset($c['enrollment']) && $c['starts']->lessThan($next)) {
                    $events[] = [$c['starts'], fn () => $enrol($key)];
                }
                $extra = isset($c['extra']) ? $c['starts']->addDays($c['extra'])->setTime(13, 0) : null;
                if ($extra !== null && $extra->greaterThanOrEqualTo($day) && $extra->lessThan($next)) {
                    // Mona's student bought two lessons more one afternoon; her mother paid them with the next cash.
                    $events[] = [$extra, function () use ($billing, $key, $owner, $notice) {
                        $billing->sellExtraLessons((string) $this->courses[$key]['enrollment'], 2, $owner);
                        $notice();
                    }];
                }
            }
            usort($events, fn (array $a, array $b) => $a[0] <=> $b[0]);
            foreach ($events as [$at, $do]) {
                $payDue($at);
                CarbonImmutable::setTestNow($at);
                $do();
            }

            // The day's lessons in order: teacher and student on time, closed by the sweeper.
            $ids = array_values(array_filter(array_map(fn (array $c) => $c['enrollment'] ?? null, $this->courses)));
            $students = array_column(array_filter($this->courses, fn (array $c) => isset($c['enrollment'])), 'student', 'enrollment');
            $lessons = Session::query()->whereIn('enrollment_id', $ids)->where('status', 'scheduled')
                ->where('starts_at', '>=', $day->utc())->where('starts_at', '<', $next->utc())->where('ends_at', '<', $cutoff)
                ->orderBy('starts_at')->get();
            foreach ($lessons as $session) {
                $payDue($session->starts_at->subMinutes(5));
                CarbonImmutable::setTestNow($session->starts_at->addMinute());
                $join->handle($session, $session->teacher_id);
                $join->handle($session->refresh(), $students[$session->enrollment_id]);
                CarbonImmutable::setTestNow($session->ends_at->addMinutes(11));
                $deadlines->handle();
                $notice();
            }
            $payDue($next->min($cutoff));
            $day = $next;
        }

        CarbonImmutable::setTestNow();
        $keepAhead();
    }
}
