<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Announcements\Actions\SendAnnouncement;
use Modules\Announcements\Domain\Audience;
use Modules\Announcements\Filament\Pages\Announcements;
use Modules\Announcements\Models\Announcement;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * Announcements in an adults academy (docs/architecture/02, section 29):
 * no families, guardians, stages or a subject's families there, only every
 * trainee or the trainers, on the screen and in SendAnnouncement alike.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 08:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'hm-trust', 'audience' => 'adults', 'default_timezone' => 'Africa/Cairo']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Hani', 'locale' => 'ar']);
    $this->trainer = User::factory()->withRole(Role::Teacher)->create(['locale' => 'ar']);
    $this->trainees = User::factory()->withRole(Role::Student)->count(2)->create(['locale' => 'ar']);
    $this->field = app(CatalogApi::class)->createSubject('إدارة المشاريع', 'Project management');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('sends an adults academy\'s announcements to every trainee or the trainers only', function () {
    $this->actingAs($this->owner);
    $send = fn (Audience $audience) => app(SendAnnouncement::class)->handle($this->owner->id, 'موعد الاختبار', 'يوم الأحد.', $audience);

    foreach ([Audience::of('families'), Audience::of('guardians'), Audience::of('students', 'adult'), Audience::of('subject', null, $this->field)] as $audience) {
        expect(fn () => $send($audience))->toThrow(ValidationException::class, __('announcements::announcements.errors.adults_audience'));
    }
    expect(Announcement::query()->count())->toBe(0);

    expect($send(Audience::of('students'))->recipients_count)->toBe(2)
        ->and($send(Audience::of('teachers'))->recipients_count)->toBe(1);
});

it('offers an adults academy only the trainees and the trainers on the screen, with no stage', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    app()->setLocale('ar');

    Livewire::test(Announcements::class)
        ->assertFormSet(['audience' => 'students'])
        ->assertSeeHtml('data-count="2"')
        ->assertSee('المتدرّبون')->assertSee('المدرّبون')
        ->assertDontSee('كل الأسر')->assertDontSee('أولياء الأمور')
        ->assertFormFieldIsHidden('stage')
        ->fillForm(['audience' => 'teachers'])
        ->assertSeeHtml('data-count="1"');
});
