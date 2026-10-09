<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
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
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\Session;

/**
 * Family billing in the demo academy: Hala pays for two children. Karim
 * takes Quran twice a week with Mahmoud; Nada takes English twice a week
 * and Arabic once a week with Ahmed on one shared package ("12 lessons in
 * any subject"), so her Arabic course joined it with no invoice of its own.
 * Both started 26 days ago and paid, and their lessons went as they
 * happened: when the first of them reached their renewal mark, the other's
 * renewal fell in the same week, so one family invoice went out for both
 * (Nada's block with the 10% sibling discount), waiting to be paid.
 * Runs after the discounts (the sibling rate), inside the demo academy.
 */
final class DemoFamilyBillingSeeder extends Seeder
{
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
    ): void {
        $user = fn (string $key) => (string) DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $owner = $user('owner');
        $accountant = $user('accountant') ?: $owner;
        $realNow = CarbonImmutable::now();
        $password = (string) config('app.demo.password');
        $tz = 'Asia/Riyadh';

        $hala = $identity->createUser(new NewUser(name: 'هالة الشريف', roles: [Role::Guardian], email: 'hala.guardian@demo.test', password: $password, locale: 'ar', timezone: $tz));
        $karim = $identity->createUser(new NewUser(name: 'كريم الشريف', roles: [Role::Student], email: 'karim@demo.test', password: $password, locale: 'ar', timezone: $tz));
        $nada = $identity->createUser(new NewUser(name: 'ندى الشريف', roles: [Role::Student], email: 'nada@demo.test', password: $password, locale: 'ar', timezone: $tz));
        $people->saveStudentProfile($karim, $realNow->subYears(11)->subMonths(3)->startOfDay(), null);
        $people->saveStudentProfile($nada, $realNow->subYears(14)->subMonths(5)->startOfDay(), null);
        foreach ([$karim, $nada] as $child) {
            $people->linkGuardian($hala, $child, 'mother');
            $consent->handle($child, $hala, 'family', '203.0.113.41', 'Demo');
        }

        $plans = collect($billing->planOptions(30));
        $twice = $plans->first(fn (PlanOption $p) => ! $p->shared && $p->sessionMinutes === 30 && $p->sessionsPerWeek === 2);
        $package = $plans->first(fn (PlanOption $p) => $p->shared && $p->sessionMinutes === 30);
        $sar = collect($billing->regions())->first(fn (RegionOption $r) => $r->currency === 'SAR');
        if ($twice === null || $package === null || $sar === null) {
            return;
        }

        // Enrolled 26 days ago, each course from the next day on, for eight weeks of its weekdays (teacher's clock).
        CarbonImmutable::setTestNow($realNow->subDays(26));
        $from = CarbonImmutable::now()->addDay();
        $course = function (string $teacher, string $student, string $subjectName, array $times, string $planId) use ($user, $hold, $enroll, $owner, $hala, $sar, $from, $realNow): EnrollmentBooked {
            $teacherId = $user($teacher);
            $teacherTz = (string) DB::table('users')->where('id', $teacherId)->value('timezone');
            $occurrences = [];
            foreach ($times as [$weekday, $clock]) {
                $day = $from->setTimezone($teacherTz)->startOfDay();
                $day = $day->addDays(($weekday - $day->dayOfWeekIso + 7) % 7);
                for ($i = 0; $i < 8; $i++, $day = $day->addWeek()) {
                    $occurrences[] = CarbonImmutable::parse($day->toDateString().' '.$clock, $teacherTz)->getTimestamp();
                }
            }
            // Keep clear of the live board's lessons around the real now.
            $occurrences = array_values(array_filter($occurrences, fn (int $ts) => abs($ts - $realNow->getTimestamp()) > 3 * 3600));
            $subject = (string) DB::table('subjects')->where('name_en', $subjectName)->value('id');
            $batch = $hold->handle($teacherId, $student, $occurrences, 30, $owner);

            return $enroll->handle($batch, $student, $subject, new EnrollmentTerms($planId, $hala, $sar->id), $owner);
        };

        $quran = $course('mahmoud', $karim, 'Quran memorisation', [[6, '17:00'], [3, '17:00']], $twice->id);
        $english = $course('ahmed', $nada, 'English', [[1, '18:00'], [3, '18:00']], $package->id);
        $arabic = $course('ahmed', $nada, 'Arabic', [[4, '18:00']], $package->id);      // joins Nada's package: no invoice
        foreach ([$quran, $english] as $i => $booked) {
            $billing->recordPayment($booked->invoice->id, 'bank_transfer', $booked->invoice->total, 'SA-7731'.$i, CarbonImmutable::now(), $accountant);
        }

        // Every lesson since, in the order they happened: teacher and student on time, closed by the sweeper.
        $courses = [$quran->enrollmentId, $english->enrollmentId, $arabic->enrollmentId];
        $student = [$quran->enrollmentId => $karim, $english->enrollmentId => $nada, $arabic->enrollmentId => $nada];
        $past = Session::query()->whereIn('enrollment_id', $courses)->where('ends_at', '<', $realNow->subHours(6))->orderBy('starts_at')->get();
        foreach ($past as $session) {
            CarbonImmutable::setTestNow($session->starts_at->addMinute());
            $join->handle($session, $session->teacher_id);
            $join->handle($session->refresh(), $student[$session->enrollment_id]);
            CarbonImmutable::setTestNow($session->ends_at->addMinutes(11));
            $deadlines->handle();
        }
        CarbonImmutable::setTestNow();
        $materialize->handle($courses);
        $fund->handle($quran->enrollmentId);
        $fund->handle($english->enrollmentId);
    }
}
