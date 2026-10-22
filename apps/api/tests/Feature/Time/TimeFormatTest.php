<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\WeekdayTime;
use Modules\Communication\Queries\Recipients;
use Modules\Communication\Templates\Renderer;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\MySettings;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\SaveAcademyTimeFormat;
use Modules\Platform\Filament\Pages\BrandingSettings;
use Modules\Platform\Models\Academy;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\TimeFormat;
use Modules\SharedKernel\Time\ViewerClock;
use Modules\SharedKernel\Time\ViewerTimezone;

/*
 * How a clock time is written (docs/architecture/02, the time section): 12
 * hours (5:00 م, 5:00 PM) or 24 hours (17:00), the person's own choice, else
 * their academy's, else 12 hours; everywhere through Clock, and in each
 * notification for its recipient.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['email' => 'owner@example.test', 'locale' => 'ar', 'timezone' => 'Asia/Riyadh']);
    $this->parent = User::factory()->withRole(Role::Guardian)->create(['email' => 'khaled@example.test', 'timezone' => 'Asia/Riyadh']);
    $this->instant = CarbonImmutable::parse('2026-10-05 14:05:00', 'UTC');   // 17:05 in Riyadh
});

it('writes a clock in 12 or 24 hours, in both languages', function () {
    app()->setLocale('ar');
    expect(Clock::pattern('l j F · H:i', TimeFormat::Twelve))->toBe('l j F · g:i A')
        ->and(Clock::pattern('l j F · H:i', TimeFormat::TwentyFour))->toBe('l j F · H:i')
        ->and(Clock::pattern('H:i:s', TimeFormat::Twelve))->toBe('g:i:s A')
        ->and(Clock::wall('17:05', TimeFormat::Twelve))->toBe('5:05 م')
        ->and(Clock::wall('09:30', TimeFormat::Twelve))->toBe('9:30 ص')
        ->and(Clock::wall('00:00', TimeFormat::Twelve))->toBe('12:00 ص')
        ->and(Clock::wall('12:00', TimeFormat::Twelve))->toBe('12:00 م')
        ->and(Clock::wall('24:00', TimeFormat::Twelve))->toBe('12:00 ص')
        ->and(Clock::wall('9:30', TimeFormat::TwentyFour))->toBe('09:30')
        ->and(Clock::wall('24:00', TimeFormat::TwentyFour))->toBe('24:00')
        ->and(Clock::hour(17, TimeFormat::Twelve))->toBe('5 م')
        ->and(Clock::hour(17, TimeFormat::TwentyFour))->toBe('17:00')
        ->and(Clock::hourCycle(TimeFormat::Twelve))->toBe('h12');

    app()->setLocale('en');
    expect(Clock::wall('17:05', TimeFormat::Twelve))->toBe('5:05 PM')
        ->and(Clock::hour(9, TimeFormat::Twelve))->toBe('9 AM')
        ->and($this->instant->setTimezone('Asia/Riyadh')->locale('en')->translatedFormat(Clock::pattern('D j M · H:i', TimeFormat::Twelve)))->toBe('Mon 5 Oct · 5:05 PM');
});

it('reads the person\'s choice, else the academy\'s, else 12 hours', function () {
    $clock = app(ViewerClock::class);
    expect($clock->effective())->toBe(TimeFormat::Twelve);

    $this->actingAs($this->owner);
    app(SaveAcademyTimeFormat::class)->handle('h24');
    expect($clock->academy())->toBe(TimeFormat::TwentyFour)
        ->and($clock->effective())->toBe(TimeFormat::TwentyFour);

    $this->parent->update(['time_format' => 'h12']);
    $clock->reset();
    $clock->setUser($this->parent->fresh());
    expect($clock->own())->toBe(TimeFormat::Twelve)->and($clock->effective())->toBe(TimeFormat::Twelve);

    // A signed page that read the person's row itself.
    $clock->reset();
    $clock->choose(null);
    expect($clock->effective())->toBe(TimeFormat::TwentyFour);
});

it('writes the API\'s labels the reader\'s way, and lets each person choose theirs', function () {
    $this->clearTenant();
    $token = $this->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => 'khaled@example.test', 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');
    $api = fn () => $this->withToken($token)->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'ar']);

    $api()->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.time_format', 'h12')
        ->assertJsonPath('data.time_format_choice', null)
        ->assertJsonPath('data.academy.time_format', 'h12');
    app(ViewerTimezone::class)->setDevice('Asia/Riyadh');
    app()->setLocale('ar');
    expect(app(LocalTimePresenter::class)->present($this->instant)['label'])->toBe('الاثنين 5 أكتوبر · 5:05 م');

    $api()->patchJson('/api/v1/me', ['time_format' => 'h24'])->assertOk()
        ->assertJsonPath('data.time_format', 'h24')->assertJsonPath('data.time_format_choice', 'h24');
    $api()->getJson('/api/v1/notifications')->assertOk();   // any answer: the reader's clock is set per request
    app(ViewerClock::class)->setUser(User::query()->find($this->parent->id));
    expect(app(LocalTimePresenter::class)->present($this->instant)['label'])->toBe('الاثنين 5 أكتوبر · 17:05');

    // Back to the academy's, and nothing else is a choice.
    $api()->patchJson('/api/v1/me', ['time_format' => null])->assertOk()->assertJsonPath('data.time_format_choice', null);
    $api()->patchJson('/api/v1/me', ['time_format' => '24'])->assertUnprocessable()->assertJsonStructure(['errors' => ['time_format']]);

    // Before anyone signs in, the pages read the academy's choice.
    $this->getJson('/api/v1/branding', ['X-Academy' => 'noor'])->assertOk()->assertJsonPath('time_format', 'h12');
});

it('lets owners and admins set the academy\'s clock from Branding, audited, and nobody else', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');

    Livewire::test(BrandingSettings::class)
        ->assertSet('data.time_format', 'h12')
        ->set('data.time_format', 'h24')
        ->call('save')
        ->assertHasNoErrors();

    expect(Academy::query()->find($this->academy->id)->time_format)->toBe('h24');
    $audit = DB::table('audit_logs')->where('action', 'academy.time_format_saved')->sole();
    expect(json_decode($audit->data, true))->toBe(['clock' => 'h24']);

    $this->actingAs($this->parent);
    expect(fn () => app(SaveAcademyTimeFormat::class)->handle('h12'))->toThrow(AuthorizationException::class);
});

it('lets anyone choose their own clock in My settings, or follow the academy\'s', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');

    Livewire::test(MySettings::class)
        ->assertSet('data.time_format', 'academy')
        ->assertSee(__('identity::settings.time_format.academy', ['format' => __('identity::settings.time_format.h12')]))
        ->set('data.time_format', 'h24')
        ->call('save')
        ->assertHasNoErrors();
    expect($this->owner->fresh()->time_format)->toBe('h24');

    Livewire::test(MySettings::class)->assertSet('data.time_format', 'h24')->set('data.time_format', 'academy')->call('save');
    expect($this->owner->fresh()->time_format)->toBeNull();
});

it('writes each notification in its recipient\'s clock', function () {
    $this->parent->update(['time_format' => 'h24']);
    $recipients = app(Recipients::class)->find([$this->owner->id, $this->parent->id]);
    expect($recipients[$this->owner->id]->timeFormat)->toBe('h12')
        ->and($recipients[$this->parent->id]->timeFormat)->toBe('h24');

    app('translator')->addLines(['clock.title' => 'Moved', 'clock.body' => 'Now :when, every :weekly.'], 'en');
    $template = new TemplateDefinition(key: 'clock.test', category: 'lessons', title: 'clock.title', body: 'clock.body', audience: 'clock.audience', channels: ['push']);
    $data = ['when' => $this->instant, 'weekly' => new WeekdayTime($this->instant->setTimezone('Africa/Cairo'))];
    $renderer = app(Renderer::class);

    expect($renderer->render($template, 'en', $data, 'Asia/Riyadh', TimeFormat::Twelve)->body)->toBe('Now Mon 5 Oct · 5:05 PM, every Monday 5:05 PM.')
        ->and($renderer->render($template, 'en', $data, 'Asia/Riyadh', TimeFormat::TwentyFour)->body)->toBe('Now Mon 5 Oct · 17:05, every Monday 17:05.');
});

it('hands the live board\'s script the reader\'s clock, and writes its own the same way', function () {
    CarbonImmutable::setTestNow($this->instant);
    app()->setLocale('ar');
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');

    Livewire::test(LiveBoard::class)
        ->assertSeeHtml('data-hour-cycle="h12"')->assertSeeHtml('data-lb-locale="ar"')
        ->assertSee('5:05 م')->assertDontSee('17:05');

    $this->owner->update(['time_format' => 'h24']);
    $this->actingAs($this->owner->fresh());
    Livewire::test(LiveBoard::class)
        ->assertSeeHtml('data-hour-cycle="h23"')
        ->assertSee('17:05')->assertDontSee('5:05 م');

    CarbonImmutable::setTestNow();
});
