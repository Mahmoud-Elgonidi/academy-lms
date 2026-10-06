<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

afterEach(fn () => CarbonImmutable::setTestNow());

it('cancels from the live board with who asked, under the policy', function () {
    $this->enrolStudentOnPlan();
    $first = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    CarbonImmutable::setTestNow($first->starts_at->subMinutes(20));
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    Livewire::test(LiveBoard::class)
        ->callAction('cancel', data: ['by' => 'teacher', 'reason' => 'illness', 'waive' => false], arguments: ['session' => $first->id, 'by' => 'teacher', 'reason' => 'illness'])
        ->assertHasNoActionErrors();

    expect($first->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($first->decision)->toMatchArray(['by' => 'teacher', 'rule' => 8, 'credit' => 'makeup']);
});
