<?php

namespace Modules\Billing\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Actions\Discounts\ApplyCoupon;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Queries\AvailabilityQuery;

/**
 * Discounts in the demo academy: 10% off the second child and 15% from the
 * third; Ziad's father enrols his younger daughter Laila, whose first
 * invoice shows the sibling discount; coupons in each state (on, upcoming,
 * ended, off); Hamza's overdue invoice issued again with FALL10; and
 * Layan's father asks for two extra lessons, waiting to be paid.
 * Runs after the enrolments and payments, inside the demo academy.
 */
final class DemoDiscountsSeeder extends Seeder
{
    public function run(
        SaveSiblingDiscount $sibling,
        SaveCoupon $coupons,
        ApplyCoupon $apply,
        BuyExtraLessons $extra,
        BillingApi $billing,
        IdentityApi $identity,
        PeopleApi $people,
        GrantConsent $consent,
        PlaceBookingHold $hold,
        EnrollStudent $enroll,
    ): void {
        $user = fn (string $key) => (string) DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $owner = $user('owner');
        $accountant = $user('accountant') ?: $owner;
        $now = CarbonImmutable::now('Africa/Cairo');
        $locale = app()->getLocale();
        app()->setLocale('ar');     // what the office would see: the reissue's reason in Arabic

        $sibling->handle(10, 15);

        $coupons->handle(null, ['code' => 'WELCOME50', 'name' => 'أول اشتراك', 'kind' => 'amount', 'amount' => 50, 'currency' => 'SAR', 'blocks' => 1, 'max_uses' => 100], $owner);
        $coupons->handle(null, ['code' => 'FALL10', 'name' => 'عرض الخريف', 'kind' => 'percent', 'percent' => 10, 'blocks' => 2, 'ends_on' => $now->addMonths(2)->toDateString()], $owner);
        $coupons->handle(null, ['code' => 'RAMADAN15', 'name' => 'حملة رمضان', 'kind' => 'percent', 'percent' => 15, 'blocks' => 3, 'starts_on' => '2027-02-01', 'ends_on' => '2027-03-15', 'max_uses' => 200], $owner);
        $coupons->handle(null, ['code' => 'SUMMER20', 'name' => 'الصيف اللي فات', 'kind' => 'percent', 'percent' => 20, 'blocks' => 1, 'ends_on' => '2026-08-31'], $owner);
        $coupons->handle(null, ['code' => 'SCHOLAR100', 'name' => 'منحة كاملة (بموافقة المالك)', 'kind' => 'percent', 'percent' => 100, 'is_active' => false], $owner);

        // Ziad's father enrols his younger daughter: the second child, 10% off her invoices.
        $laila = $identity->createUser(new NewUser(name: 'ليلى عمرو', roles: [Role::Student], email: 'laila@demo.test', password: (string) config('app.demo.password'), locale: 'ar', timezone: 'Asia/Kuwait'));
        $people->saveStudentProfile($laila, $now->subYears(9)->subMonths(2)->startOfDay(), null);
        $father = $user('ziad.guardian');
        $people->linkGuardian($father, $laila, 'father');
        $consent->handle($laila, $father, 'family', '203.0.113.40', 'Demo');

        // Tuesdays with Nour, at the first hour free on most of the next eight weeks (her shift: 17:00–22:00 on her clock).
        $teacher = $user('nour');
        $tz = (string) DB::table('users')->where('id', $teacher)->value('timezone');
        $first = $now->setTimezone($tz)->addDays(2)->startOfDay();
        $first = $first->addDays((2 - $first->dayOfWeekIso + 7) % 7);
        $times = [];
        foreach (['18:30', '19:30', '20:30', '17:30', '21:00'] as $clock) {
            $candidates = array_map(fn (int $i) => CarbonImmutable::parse($first->addWeeks($i)->toDateString().' '.$clock, $tz), range(0, 7));
            $free = app(AvailabilityQuery::class)->forTeachers([$teacher], $candidates[0]->subHour(), end($candidates)->addHour())[$teacher]->free ?? null;
            $times = array_values(array_map(fn (CarbonImmutable $at) => $at->getTimestamp(),
                array_filter($candidates, fn (CarbonImmutable $at) => $free?->contains($at->getTimestamp(), $at->getTimestamp() + 1800) ?? false)));
            if (count($times) >= 6) {
                break;
            }
        }
        $plan = collect($billing->planOptions(30))->first(fn (PlanOption $p) => $p->sessionMinutes === 30 && $p->sessionsPerWeek === 1);
        $region = collect($billing->regions())->first(fn (RegionOption $r) => $r->currency === 'SAR');
        if ($plan !== null && $region !== null && count($times) >= 6) {
            $batch = $hold->handle($teacher, $laila, $times, 30, $owner);
            $quran = (string) DB::table('subjects')->where('name_en', 'Quran memorisation')->value('id');
            $enroll->handle($batch, $laila, $quran, new EnrollmentTerms($plan->id, $father, $region->id), $owner);
        }

        // Hamza's overdue invoice, issued again with a coupon the academy gave his mother.
        $hamza = DB::table('subscriptions as s')->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->where('e.student_id', $user('hamza'))->value('s.id');
        if ($hamza !== null) {
            $apply->handle((string) $hamza, 'FALL10', $accountant, reissue: true);
        }

        // Layan's father asks for two extra lessons before her exams; the invoice waits to be paid.
        $layan = DB::table('subscriptions as s')->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->where('e.student_id', $user('layan'))->where('e.status', 'active')->value('s.id');
        if ($layan !== null) {
            $extra->handle((string) $layan, 2, $user('layan.guardian'), 'family');
        }
        app()->setLocale($locale);
    }
}
