<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\Classroom\Filament\Pages\ClassroomSettingsPage;
use Modules\Classroom\Filament\Teacher\MyLessonsToday;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\MeetingLink;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

beforeEach(function () {
    Mail::fake();
    Http::fake();
    $this->enrolStudentOnPlan();
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();   // Tue 6 Oct, 15:00–15:30 UTC (18:00 in Cairo)
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('shows the teacher today\'s lessons on their clock, with the way in once the classroom opens', function () {
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 12:00', 'UTC'));
    Livewire::test(MyLessonsToday::class)
        ->assertSee('6:00 PM – 6:30 PM')->assertSee($this->student->name)
        ->assertSee(__('classroom::classroom.today.opens', ['time' => '5:50 PM']))
        ->assertDontSee(route('classroom.page', ['session' => $this->lesson->id]));

    // A teacher who reads 24 hours.
    $this->teacher->update(['time_format' => 'h24']);
    $this->actingAs($this->teacher->fresh());
    Livewire::test(MyLessonsToday::class)
        ->assertSee('18:00 – 18:30')->assertSee(__('classroom::classroom.today.opens', ['time' => '17:50']))
        ->assertDontSee('6:00 PM');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 14:52', 'UTC'));
    Livewire::test(MyLessonsToday::class)->assertSee(route('classroom.page', ['session' => $this->lesson->id]));

    app(CancelSession::class)->handle($this->lesson, 'teacher', 'illness', null, $this->admin->id);
    Livewire::test(MyLessonsToday::class)->assertSee(__('classroom::classroom.today.cancelled'))
        ->assertDontSee(route('classroom.page', ['session' => $this->lesson->id]));
});

it('lets the teacher keep an outside meeting link, and refuses one that is not https', function () {
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');

    Livewire::test(MyLessonsToday::class)->callAction('link', data: ['url' => 'http://zoom.us/j/1'])->assertHasActionErrors(['url']);
    Livewire::test(MyLessonsToday::class)->callAction('link', data: ['url' => 'https://zoom.us/j/123'])->assertHasNoActionErrors();

    expect(MeetingLink::query()->where('teacher_id', $this->teacher->id)->value('url'))->toBe('https://zoom.us/j/123');
});

it('lets the owner or an admin choose where lessons happen, and nobody else', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    Livewire::test(ClassroomSettingsPage::class)
        ->assertSet('data.provider', 'livekit')
        ->set('data.provider', 'external')->call('save')->assertHasNoErrors();
    expect(ClassroomSettings::current()->provider)->toBe('external');

    $this->actingAs(User::factory()->withRole(Role::Scheduler)->create());
    Livewire::test(ClassroomSettingsPage::class)->assertForbidden();
});

it('lets the supervisor watch a lesson from the live board and send the student their link again', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:04', 'UTC'));
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    $url = route('classroom.page', ['session' => $this->lesson->id]);

    Livewire::test(LiveBoard::class)
        ->assertActionHasUrl(TestAction::make('observe')->arguments(['session' => $this->lesson->id]), $url)
        ->assertActionShouldOpenUrlInNewTab(TestAction::make('observe')->arguments(['session' => $this->lesson->id]))
        ->callAction('resendLink', arguments: ['session' => $this->lesson->id])
        ->assertHasNoActionErrors();

    $sent = Notification::query()->where('template_key', 'scheduling.join_now')->get();
    expect($sent->pluck('user_id')->all())->toContain($this->student->id)
        ->and($sent->first()->body)->toContain('/class/'.$this->lesson->id);
});
