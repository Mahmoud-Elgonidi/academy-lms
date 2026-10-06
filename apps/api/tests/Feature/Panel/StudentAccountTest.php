<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Billing\Contracts\BillingApi;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Resources\Enrollments\ViewEnrollment;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    $this->lessons = fn () => Session::query()->where('enrollment_id', $this->booked->enrollmentId)->where('type', 'regular')->orderBy('starts_at')->get();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function accountPage(): Testable
{
    return Livewire::test(ViewEnrollment::class, ['record' => test()->booked->enrollmentId]);
}

it('cancels a lesson under the policy from the student account', function () {
    $first = ($this->lessons)()->first();

    accountPage()->callAction('cancelSession', data: ['by' => 'student', 'reason' => 'illness', 'waive' => false], arguments: ['session' => $first->id])
        ->assertHasNoActionErrors();

    expect($first->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($first->decision)->toMatchArray(['by' => 'student', 'rule' => 2]);
});

it('moves a lesson to a free time the dialog offers', function () {
    $first = ($this->lessons)()->first();

    accountPage()->callAction('reschedule', data: ['by' => 'student', 'date' => '2026-10-06', 'starts_at' => '2026-10-06T16:30:00+00:00', 'waive' => false], arguments: ['session' => $first->id])
        ->assertHasNoActionErrors();

    expect($first->refresh()->starts_at->setTimezone('Africa/Cairo')->format('H:i'))->toBe('19:30');
});

it('books a makeup once the student is owed one', function () {
    app(BillingApi::class)->recordPayment($this->booked->invoice->id, 'cash', $this->booked->invoice->total, null, CarbonImmutable::now(), null);
    $first = ($this->lessons)()->first();
    app(CancelSession::class)->handle($first, 'teacher', 'illness', null, null);

    accountPage()->callAction('bookMakeup', data: ['date' => '2026-10-06', 'starts_at' => '2026-10-06T15:00:00+00:00'])
        ->assertHasNoActionErrors();

    expect(Session::query()->where('type', SessionType::Makeup->value)->value('makeup_for_session_id'))->toBe($first->id);
});

it('moves the student to another teacher, and opens that dialog when a shift review sends them here', function () {
    accountPage()->callAction('reassign', data: ['teacher' => $this->other->id, 'from' => '2026-10-13'])
        ->assertHasNoActionErrors();

    expect(($this->lessons)()->last()->teacher_id)->toBe($this->other->id);

    Livewire::withQueryParams(['reassign' => '2026-10-20'])->test(ViewEnrollment::class, ['record' => $this->booked->enrollmentId])
        ->assertSet('defaultAction', 'reassign');
});
