<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Filament\Resources\Invoices\ViewInvoice;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['locale' => 'ar']);
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();

    $student = User::factory()->withRole(Role::Student)->create(['timezone' => 'Asia/Riyadh']);
    $teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $subject = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $region = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
    $plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $plan->prices()->create(['price_region_id' => $region->id, 'amount_minor' => 40000, 'currency' => 'SAR']);

    $this->enrollmentId = app(EnrollmentApi::class)->create(new NewEnrollment($student->id, $subject, $teacher->id, CarbonImmutable::now()));
    $account = app(CreditsApi::class)->openAccount($student->id, 'enrollment', $this->enrollmentId);
    app(EnrollmentApi::class)->attachCreditAccount($this->enrollmentId, $account);
    $this->invoiceId = app(BillingApi::class)->subscribe(new NewSubscription($this->enrollmentId, $student->id, $account, $plan->id, $student->id, $region->id, 'قرآن', 'Quran'))->id;
    $this->clearTenant();
});

it('shows the money screens to the owner', function (string $path) {
    $this->actingAs($this->owner)->get('http://noor.lms.test/admin'.$path)->assertOk();
})->with([
    'invoices' => '/invoices',
    'plans' => '/settings/plans',
    'enrollments' => '/enrollments',
]);

it('opens an invoice and a student account', function () {
    $this->actingAs($this->owner)->get("http://noor.lms.test/admin/invoices/{$this->invoiceId}")->assertOk()->assertSee('INV-000001');
    $this->actingAs($this->owner)->get("http://noor.lms.test/admin/enrollments/{$this->enrollmentId}")->assertOk();
});

it('gives each role only its share of billing', function (string $role, string $path, int $status) {
    $this->actingAs($this->{$role})->get('http://noor.lms.test/admin'.$path)->assertStatus($status);
})->with([
    'accountant sees invoices' => ['accountant', '/invoices', 200],
    'accountant sets prices' => ['accountant', '/settings/plans', 200],
    'scheduler sees enrolled students' => ['scheduler', '/enrollments', 200],
    'scheduler does not see invoices' => ['scheduler', '/invoices', 403],
    'scheduler does not set prices' => ['scheduler', '/settings/plans', 403],
]);

it('records a payment from the invoice page', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');

    Livewire::test(ViewInvoice::class, ['record' => $this->invoiceId])
        ->callAction('recordPayment', data: ['method' => 'bank_transfer', 'amount' => '400', 'received_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'), 'reference' => 'TRX-1'])
        ->assertHasNoActionErrors();

    expect(Invoice::query()->findOrFail($this->invoiceId)->status)->toBe('paid');
});
