<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\ShiftSchedule;

/**
 * Monday 5 October 2026, 10:00 in Cairo: a Quran student in London enrolled
 * twice a week (Tuesdays and Saturdays, 18:00 Cairo) with a Cairo teacher on
 * an unpaid 8 × 30 min plan; a second teacher (Riyadh) teaches the same
 * subject on the same days.
 */
trait EnrolledStudent
{
    protected function enrolStudentOnPlan(): EnrollmentBooked
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
        $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
        $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
        $this->admin = User::factory()->withRole(Role::Admin)->create();
        $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
        $this->other = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Asia/Riyadh']);
        $this->student = User::factory()->withRole(Role::Student)->create(['timezone' => 'Europe/London']);
        $guardian = User::factory()->withRole(Role::Guardian)->create();
        app(PeopleApi::class)->linkGuardian($guardian->id, $this->student->id);

        foreach ([[$this->teacher, 'Africa/Cairo', '17:00', 'female'], [$this->other, 'Asia/Riyadh', '18:00', 'male']] as [$t, $tz, $start, $gender]) {
            app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($t->id, $gender, ['ar'], [$this->quran]));
            $schedule = ShiftSchedule::query()->create(['teacher_id' => $t->id, 'version' => 1, 'timezone' => $tz, 'effective' => '[2026-09-01,)']);
            foreach ([6, 2] as $weekday) {
                $schedule->rules()->create(['teacher_id' => $t->id, 'weekday' => $weekday, 'local_start' => $start, 'duration_min' => 240]);
            }
        }

        $region = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
        $plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
        $plan->prices()->create(['price_region_id' => $region->id, 'amount_minor' => 40000, 'currency' => 'SAR']);

        $times = [];
        foreach (['2026-10-06', '2026-10-10'] as $first) {
            for ($i = 0; $i < 8; $i++) {
                $times[] = CarbonImmutable::parse("{$first} 18:00", 'Africa/Cairo')->addWeeks($i)->getTimestamp();
            }
        }
        $batch = app(PlaceBookingHold::class)->handle($this->teacher->id, $this->student->id, $times, 30, $this->admin->id);

        return $this->booked = app(EnrollStudent::class)->handle($batch, $this->student->id, $this->quran, new EnrollmentTerms($plan->id, $guardian->id, $region->id), $this->admin->id);
    }
}
