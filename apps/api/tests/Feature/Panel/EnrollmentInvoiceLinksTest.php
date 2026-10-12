<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Filament\Resources\Enrollments\ViewEnrollment;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The student account lists the course's invoices for everyone who may see
 * the course, and links them only for those who may open an invoice
 * (billing.view): a scheduler or a supervisor reads the number, never a
 * link to a 403.
 */
beforeEach(function () {
    $this->enrolStudentOnPlan();
    Filament::setCurrentPanel('academy');
    $this->invoiceUrl = route('filament.academy.resources.invoices.view', ['record' => $this->booked->invoice->id]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('links the course\'s invoices for staff who may open them', function () {
    $this->actingAs($this->admin);

    Livewire::test(ViewEnrollment::class, ['record' => $this->booked->enrollmentId])
        ->assertSee($this->booked->invoice->number)
        ->assertSeeHtml('href="'.$this->invoiceUrl.'"');
});

it('shows a scheduler and a supervisor the invoice\'s number without a link they could not open', function (Role $role) {
    $staff = User::factory()->withRole($role)->create();
    $this->actingAs($staff);

    Livewire::test(ViewEnrollment::class, ['record' => $this->booked->enrollmentId])
        ->assertSee($this->booked->invoice->number)
        ->assertDontSeeHtml('/admin/invoices/'.$this->booked->invoice->id);
    // The invoice itself is not theirs to open.
    $this->get('http://noor.lms.test/admin/invoices/'.$this->booked->invoice->id)->assertForbidden();
})->with([Role::Scheduler, Role::Supervisor]);
