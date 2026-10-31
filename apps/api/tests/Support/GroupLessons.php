<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Groups\CreateGroup;
use Modules\Scheduling\Actions\Groups\JoinGroup;
use Modules\Scheduling\Contracts\DTOs\GroupJoin;
use Modules\Scheduling\Contracts\DTOs\GroupJoined;
use Modules\Scheduling\Contracts\DTOs\NewGroup;
use Modules\Scheduling\Domain\Models\LessonGroup;

/**
 * Monday 5 October 2026, 10:00 in Cairo: a Cairo teacher who takes groups
 * of up to four, a group plan (8 × 30 min, twice a week, 240 SAR: 30 a
 * lesson) and a private one of the same shape, and adult students whose
 * guardian pays. Groups meet on Tuesdays and Saturdays at 18:00 Cairo.
 */
trait GroupLessons
{
    protected function setUpGroups(int $teacherMax = 4): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
        $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
        $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
        $this->admin = User::factory()->withRole(Role::Admin)->create();
        $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
        $this->payer = User::factory()->withRole(Role::Guardian)->create();
        app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: $teacherMax));

        $this->region = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
        $this->groupPlan = Plan::query()->create(['name_ar' => 'مجموعة 8 حصص', 'name_en' => 'Group 8', 'session_minutes' => 30, 'sessions_per_block' => 8,
            'sessions_per_week' => 2, 'validity_days' => 42, 'lesson_format' => 'group']);
        $this->groupPlan->prices()->create(['price_region_id' => $this->region->id, 'amount_minor' => 24000, 'currency' => 'SAR']);
        $this->privatePlan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8,
            'sessions_per_week' => 2, 'validity_days' => 42]);
        $this->privatePlan->prices()->create(['price_region_id' => $this->region->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
    }

    protected function student(string $born = '2004-05-01', string $timezone = 'Europe/London'): User
    {
        $student = User::factory()->withRole(Role::Student)->create(['timezone' => $timezone]);
        app(PeopleApi::class)->saveStudentProfile($student->id, CarbonImmutable::parse($born), null);
        app(PeopleApi::class)->linkGuardian($this->payer->id, $student->id);

        return $student;
    }

    protected function group(?int $capacity = 3, ?int $minToStart = null, ?string $teacherId = null): LessonGroup
    {
        return app(CreateGroup::class)->handle(new NewGroup(
            teacherId: $teacherId ?? $this->teacher->id,
            subjectId: $this->quran,
            sessionMinutes: 30,
            times: [['weekday' => 2, 'start' => '18:00'], ['weekday' => 6, 'start' => '18:00']],
            capacity: $capacity,
            minToStart: $minToStart,
        ), $this->admin->id);
    }

    /** Another teacher's running group of the subject (Riyadh, Mondays at 19:00 there), with one adult in it. */
    protected function otherGroup(): LessonGroup
    {
        $teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Asia/Riyadh']);
        app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($teacher->id, 'male', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: 4));
        $group = app(CreateGroup::class)->handle(new NewGroup($teacher->id, $this->quran, 30, [['weekday' => 1, 'start' => '19:00'], ['weekday' => 4, 'start' => '19:00']], capacity: 3), $this->admin->id);
        $this->join($group, $this->student());

        return $group->refresh();
    }

    protected function join(LessonGroup $group, User $student, ?string $lateJoin = null, ?int $lessons = null, ?string $planId = null): GroupJoined
    {
        return app(JoinGroup::class)->handle($group->id, $student->id, new GroupJoin($planId ?? $this->groupPlan->id, $this->payer->id, $this->region->id,
            lateJoin: $lateJoin, lateJoinLessons: $lessons), $this->admin->id);
    }

    protected function pay(GroupJoined $joined): void
    {
        $invoice = Invoice::query()->findOrFail($joined->invoice->id);
        app(RecordPayment::class)->handle($invoice->id, 'bank_transfer', $invoice->outstanding(), 'T-'.$invoice->number, CarbonImmutable::now(), null, $this->admin->id);
    }
}
