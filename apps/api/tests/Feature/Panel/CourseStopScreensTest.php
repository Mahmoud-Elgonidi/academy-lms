<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Filament\Family\BalanceWidget;
use Modules\Billing\Models\Invoice;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Filament\Family\FamilyProgress;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Resources\Enrollments\ListEnrollments;
use Modules\Scheduling\Filament\Resources\Enrollments\ViewEnrollment;
use Modules\Scheduling\Queries\Models\EnrolledStudent as EnrolledRow;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Pausing, resuming and ending a course from the student account in the back
 * office (docs/architecture/02, section 3, "إيقاف الكورس وإنهاؤه"): each says
 * what it will do before anyone confirms; the course list filters by status.
 * Monday 5 October 2026 in Cairo; lessons Tuesdays and Saturdays 18:00.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    app()->setLocale('en');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function coursePage(): Testable
{
    return Livewire::test(ViewEnrollment::class, ['record' => test()->booked->enrollmentId]);
}

function paidCourse(): void
{
    app(BillingApi::class)->recordPayment(test()->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null);
}

it('pauses a course, saying first what it will do, and resumes it', function () {
    paidCourse();

    coursePage()->mountAction('pauseCourse')
        ->fillForm(['from' => '2026-10-13', 'until' => '2026-10-24'])
        ->assertMountedActionModalSee(['4 booked lessons are cancelled (120 paid minutes back in the balance).', 'The credit lasts 12 days longer.', 'The course comes back by itself on Sunday 25 October.']);

    coursePage()->callAction('pauseCourse', data: ['from' => '2026-10-13', 'until' => '2026-10-24', 'reason' => 'Family travel'])
        ->assertHasNoActionErrors();
    expect(Enrollment::query()->find($this->booked->enrollmentId)->paused_until->toDateString())->toBe('2026-10-24');

    // The office reads the pause and its reason; the pause can be made longer, or called off.
    coursePage()->assertSee('A pause from Tuesday 13 October until Saturday 24 October is set — Family travel')
        ->assertActionVisible('resumeCourse')
        ->callAction('pauseCourse', data: ['until' => '2026-10-31', 'reason' => 'Family travel, a week more'])
        ->assertHasNoActionErrors();
    expect(Enrollment::query()->find($this->booked->enrollmentId)->paused_until->toDateString())->toBe('2026-10-31');

    coursePage()->callAction('resumeCourse')->assertHasNoActionErrors();
    expect(Enrollment::query()->find($this->booked->enrollmentId)->paused_from)->toBeNull();
});

it('offers a course waiting for its first payment no pause, only an end, and keeps the dialog open on a refusal', function () {
    coursePage()->assertActionHidden('pauseCourse')->assertActionHidden('resumeCourse')->assertActionVisible('endCourse');

    // A day already gone, or no reason: refused, and nothing changes.
    coursePage()->callAction('endCourse', data: ['on' => '2026-10-04', 'reason' => 'x'])->assertHasActionErrors(['on']);
    coursePage()->callAction('endCourse', data: ['on' => '2026-10-05', 'reason' => ''])->assertHasActionErrors(['reason']);
    expect(Enrollment::query()->find($this->booked->enrollmentId)->ends_on)->toBeNull();
});

it('ends a course, saying first what happens to its lessons, its balance and its waiting invoice; then nothing more applies', function () {
    coursePage()->mountAction('endCourse')
        ->fillForm(['on' => '2026-10-05'])
        ->assertMountedActionModalSee(['16 booked lessons are cancelled', '0 minutes stay in the balance afterwards', 'The block invoice '.$this->booked->invoice->number.' waiting to be paid is voided.']);

    coursePage()->callAction('endCourse', data: ['on' => '2026-10-05', 'reason' => 'Moved abroad'])->assertHasNoActionErrors();

    expect(Enrollment::query()->find($this->booked->enrollmentId)->status)->toBe(EnrollmentStatus::Ended)
        ->and(Invoice::query()->find($this->booked->invoice->id)->status)->toBe('void');
    coursePage()->assertSee('Ended from Monday 5 October — Moved abroad')
        ->assertActionHidden('pauseCourse')->assertActionHidden('resumeCourse')->assertActionHidden('endCourse')
        ->assertActionHidden('reassign')->assertActionHidden('bookExtra')->assertActionHidden('coupon');
});

it('leaves pausing and ending to those who manage lessons', function () {
    $this->actingAs(User::factory()->withRole(Role::Accountant)->create());

    coursePage()->assertActionHidden('pauseCourse')->assertActionHidden('endCourse')->assertActionHidden('resumeCourse');
});

it('filters the course list by status, and says until when a course is paused', function () {
    paidCourse();
    $this->actingAs($this->admin);
    coursePage()->callAction('pauseCourse', data: ['from' => '2026-10-05', 'until' => '2026-10-24', 'reason' => 'Exams'])->assertHasNoActionErrors();
    $record = EnrolledRow::query()->findOrFail($this->booked->enrollmentId);

    Livewire::test(ListEnrollments::class)
        ->filterTable('status', 'paused')->assertCanSeeTableRecords([$record])->assertSee('until 24 Oct')
        ->filterTable('status', 'active')->assertCanNotSeeTableRecords([$record]);
});

it('tells the family in /my that a course is paused, until when, without the office\'s reason', function () {
    paidCourse();
    coursePage()->callAction('pauseCourse', data: ['from' => '2026-10-05', 'until' => '2026-10-24', 'reason' => 'Private office note'])->assertHasNoActionErrors();
    $guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->actingAs($guardian);
    Filament::setCurrentPanel('family');

    Livewire::test(FamilyLessons::class)->assertSee('Quran is paused until Saturday 24 October.')
        ->assertSee(__('scheduling::timeoff.family.why.enrollment_paused'))->assertDontSee('Private office note');
    Livewire::test(BalanceWidget::class)->assertSee('Paused until Saturday 24 October')->assertDontSee(__('billing::family.renew_soon'));
    Livewire::test(FamilyProgress::class)->assertSee('Paused until Saturday 24 October');
});
