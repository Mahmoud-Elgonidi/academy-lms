<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Actions\Recordings\GiveRecordingConsent;
use Modules\Classroom\Actions\Recordings\HandleEgressEvent;
use Modules\Classroom\Actions\Recordings\SaveRecordingSettings;
use Modules\Classroom\Actions\Recordings\StartRecording;
use Modules\Classroom\Filament\Family\FamilyRecordings;
use Modules\Classroom\Filament\Pages\ClassroomSettingsPage;
use Modules\Classroom\Filament\Pages\RecordingsPage;
use Modules\Classroom\Filament\Teacher\MyRecordings;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\RecordingAccessLog;
use Modules\Classroom\Models\RecordingConsent;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Http::fake([
        '*/twirp/livekit.RoomService/*' => Http::response(['name' => 'r']),
        '*/twirp/livekit.Egress/StartRoomCompositeEgress' => Http::sequence()->push(['egress_id' => 'EG_screen', 'status' => 'EGRESS_STARTING'])->push(['egress_id' => 'EG_second', 'status' => 'EGRESS_STARTING']),
        '*/twirp/livekit.Egress/StopEgress' => Http::response(['egress_id' => 'EG_screen']),
    ]);
    $this->enrolStudentOnPlan();
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();   // Tue 6 Oct, 15:00–15:30 UTC
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->owner = User::factory()->withRole(Role::Owner)->create();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:02', 'UTC'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function screensRecording(string $mode = 'manual'): void
{
    app(SaveRecordingSettings::class)->handle(['recording' => $mode, 'recording_media' => 'by_age', 'recording_days' => 30, 'recording_family' => true, 'recording_teacher' => true], test()->owner->id);
}

/** A ready recording of the lesson, its file on the disk. */
function readyRecording(): Recording
{
    screensRecording();
    app(GiveRecordingConsent::class)->handle(test()->teacher->id, test()->teacher->id);
    app(GiveRecordingConsent::class)->handle(test()->student->id, test()->student->id);
    app(JoinLesson::class)->handle(test()->lesson->id, test()->teacher->id);
    $recording = app(StartRecording::class)->handle(test()->lesson->id, test()->teacher->id);
    Storage::disk('local')->put($recording->path, 'sound');
    app(HandleEgressEvent::class)->handle(['egressId' => 'EG_screen', 'status' => 'EGRESS_COMPLETE', 'fileResults' => [['size' => '5', 'duration' => '1500000000000']]], CarbonImmutable::now());

    return $recording->refresh();
}

it('sets recording in the classroom settings', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');

    Livewire::test(ClassroomSettingsPage::class)
        ->assertFormSet(['recording' => 'off'])
        ->fillForm(['recording' => 'manual', 'recording_media' => 'audio', 'recording_days' => 3])
        ->call('save')->assertHasFormErrors(['recording_days'])
        ->fillForm(['recording_days' => 45, 'recording_family' => false])
        ->call('save')->assertHasNoFormErrors();

    expect(ClassroomSettings::current())->toMatchArray(['recording' => 'manual', 'recording_media' => 'audio', 'recording_days' => 45, 'recording_family' => false]);
});

it('lists recordings for the office, plays one (logged), and holds and removes with a reason', function () {
    $recording = readyRecording();
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');

    $page = Livewire::test(RecordingsPage::class)
        ->assertCanSeeTableRecords([$recording->id])
        ->assertSee($this->student->name)->assertSee('25:00')
        ->callTableAction('watch', $recording->id)
        ->assertSet('playing.id', $recording->id)
        ->assertSeeHtml('controlsList="nodownload');
    expect(RecordingAccessLog::query()->sole())->toMatchArray(['user_id' => $this->owner->id, 'as' => 'staff']);

    $page->callTableAction('hold', $recording->id, ['reason' => 'The family disputes the lesson'])->assertHasNoTableActionErrors();
    expect($recording->refresh()->hold_reason)->toBe('The family disputes the lesson');
    $page->assertTableActionHidden('remove', $recording->id)
        ->callTableAction('release', $recording->id)
        ->callTableAction('remove', $recording->id, ['reason' => 'The family asked for it'])->assertHasNoTableActionErrors();
    expect($recording->refresh()->status)->toBe('deleted')
        ->and(Storage::disk('local')->exists($recording->path))->toBeFalse();
});

it('lets a supervisor watch but not hold or remove, and keeps teachers out', function () {
    $recording = readyRecording();
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    $this->actingAs($supervisor);
    Filament::setCurrentPanel('academy');

    Livewire::test(RecordingsPage::class)
        ->assertTableActionVisible('watch', $recording->id)
        ->assertTableActionHidden('hold', $recording->id)
        ->assertTableActionHidden('remove', $recording->id);

    $this->actingAs($this->teacher);
    expect(RecordingsPage::canAccess())->toBeFalse();
});

it('lists who lessons record, and records a paper consent with a note', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');

    Livewire::test(RecordingsPage::class)->set('tab', 'missing')
        ->assertCanSeeTableRecords([$this->teacher->id, $this->student->id])
        ->callTableAction('paper', $this->teacher->id, ['note' => 'short'])->assertHasTableActionErrors(['note']);
    $page = Livewire::test(RecordingsPage::class)->set('tab', 'missing')
        ->callTableAction('paper', $this->teacher->id, ['note' => 'Recording clause in the contract signed in September'])->assertHasNoTableActionErrors();
    expect(RecordingConsent::liveFor($this->teacher->id))->toMatchArray(['via' => 'staff', 'recorded_by' => $this->owner->id]);

    $page->set('tab', 'agreed')->assertCanSeeTableRecords([$this->teacher->id])->assertCanNotSeeTableRecords([$this->student->id])
        ->callTableAction('withdraw', $this->teacher->id, ['note' => 'He asked on the phone on 6 October'])->assertHasNoTableActionErrors();
    expect(RecordingConsent::liveFor($this->teacher->id))->toBeNull();
});

it('shows everyone in the lesson when it records, and lets its teacher start and stop it', function () {
    screensRecording();
    app(GiveRecordingConsent::class)->handle($this->teacher->id, $this->teacher->id);
    app(JoinLesson::class)->handle($this->lesson->id, $this->teacher->id);

    // Someone has not agreed: the button waits and says who.
    $this->actingAs($this->teacher)->get("http://noor.lms.test/class/{$this->lesson->id}")
        ->assertOk()->assertSee(__('classroom::recordings.room.start'))->assertSee($this->student->name.'.', false);
    $this->postJson("http://noor.lms.test/class/{$this->lesson->id}/recording")->assertStatus(422)
        ->assertJsonPath('message', __('classroom::recordings.errors.consent_missing', ['names' => $this->student->name]));

    $this->actAsTenant($this->academy);     // the requests above ended theirs
    app(GiveRecordingConsent::class)->handle($this->student->id, $this->student->id);
    $this->actingAs($this->teacher)->postJson("http://noor.lms.test/class/{$this->lesson->id}/recording")->assertOk()->assertJsonPath('data.running', true);
    $this->actingAs($this->teacher)->get("http://noor.lms.test/class/{$this->lesson->id}")->assertSee('id="record" class="record" aria-pressed="true"', false);

    // The student sees the sign, and no button.
    $this->actingAs($this->student)->get("http://noor.lms.test/class/{$this->lesson->id}")
        ->assertOk()->assertDontSee('id="record"', false)->assertSee('id="rec" role="status" >', false);
    $this->postJson("http://noor.lms.test/class/{$this->lesson->id}/recording/stop")->assertForbidden();

    $this->actingAs($this->teacher)->postJson("http://noor.lms.test/class/{$this->lesson->id}/recording/stop")->assertOk()->assertJsonPath('data.running', false);
    $this->actAsTenant($this->academy);
    expect(Recording::query()->sole()->status)->toBe('ending');
});

it('lets an adult student agree and watch in /my, while their guardian sees who decides', function () {
    $recording = readyRecording();
    RecordingConsent::query()->where('person_id', $this->student->id)->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->student->id]);
    Filament::setCurrentPanel('family');

    $this->actingAs($this->guardian);
    Livewire::test(FamilyRecordings::class)->assertSee(__('classroom::recordings.consent.decides_self', ['name' => $this->student->name]))
        ->call('play', $recording->id)->assertSet('playing.id', $recording->id);

    $this->actingAs($this->student);
    Livewire::test(FamilyRecordings::class)
        ->call('agree', $this->student->id)->assertHasErrors(['agreed.'.$this->student->id])
        ->set('agreed.'.$this->student->id, true)->call('agree', $this->student->id)->assertHasNoErrors()
        ->assertSee(__('classroom::recordings.consent.given', ['date' => '6 October 2026']));
    expect(RecordingConsent::liveFor($this->student->id))->toMatchArray(['via' => 'self'])
        ->and(RecordingAccessLog::query()->pluck('as')->all())->toBe(['family']);

    // The academy keeps recordings from families: none listed, none played.
    app(SaveRecordingSettings::class)->handle(['recording' => 'manual', 'recording_media' => 'by_age', 'recording_days' => 30, 'recording_family' => false, 'recording_teacher' => true], $this->owner->id);
    Livewire::test(FamilyRecordings::class)->assertDontSee(__('classroom::recordings.office.watch'))
        ->call('play', $recording->id)->assertSet('playing', null);
});

it('lets a teacher agree, take it back, and watch their own lessons in /teach', function () {
    $recording = readyRecording();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');

    Livewire::test(MyRecordings::class)
        ->assertSee($this->student->name)
        ->call('play', $recording->id)->assertSet('playing.id', $recording->id)
        ->call('withdraw', $this->teacher->id)
        ->assertSee(__('classroom::recordings.consent.agree'));
    expect(RecordingConsent::liveFor($this->teacher->id))->toBeNull()
        ->and(RecordingAccessLog::query()->sole()->as)->toBe('teacher');

    // Another teacher does not see this one.
    $this->actingAs($this->other);
    Livewire::test(MyRecordings::class)->assertDontSee($this->student->name)
        ->call('play', $recording->id)->assertSet('playing', null);
});

it('gives the apps the same consent, recordings, watching, and starting and stopping in a lesson', function () {
    $recording = readyRecording();
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en']);

    $api($this->student)->getJson('/api/v1/recordings/consents')->assertOk()
        ->assertJsonPath('recording', 'manual')
        ->assertJsonPath('people.0.person.id', $this->student->id)
        ->assertJsonPath('people.0.state', 'agreed')
        ->assertJsonPath('people.0.can_change', true)
        ->assertJsonPath('people.0.text.version', RecordingConsent::VERSION);
    $api($this->guardian)->getJson('/api/v1/recordings/consents')->assertOk()
        ->assertJsonPath('people.0.person.id', $this->student->id)
        ->assertJsonPath('people.0.can_change', false)
        ->assertJsonPath('people.0.decides', __('classroom::recordings.consent.decides_self', ['name' => $this->student->name], 'en'));

    $api($this->student)->postJson("/api/v1/recordings/consents/{$this->student->id}/withdraw")->assertOk()->assertJsonPath('state', 'none');
    $api($this->student)->postJson("/api/v1/recordings/consents/{$this->student->id}", ['agree' => true, 'version' => 99])->assertStatus(422);
    $api($this->guardian)->postJson("/api/v1/recordings/consents/{$this->student->id}", ['agree' => true, 'version' => RecordingConsent::VERSION])->assertForbidden();
    $api($this->student)->postJson("/api/v1/recordings/consents/{$this->student->id}", ['agree' => true, 'version' => RecordingConsent::VERSION])->assertOk()->assertJsonPath('state', 'agreed');

    $api($this->guardian)->getJson("/api/v1/family/recordings?learner_id={$this->student->id}")->assertOk()
        ->assertJsonPath('shared', true)->assertJsonPath('data.0.id', $recording->id)->assertJsonPath('data.0.duration_sec', 1500)
        ->assertJsonPath('data.0.at.timezone', 'Africa/Cairo')->assertJsonPath('data.0.at.local', '2026-10-06T18:00:00+03:00');
    $api($this->teacher)->getJson('/api/v1/teacher/recordings')->assertOk()->assertJsonPath('data.0.student', $this->student->name);
    $api($this->other)->getJson('/api/v1/teacher/recordings')->assertOk()->assertJsonCount(0, 'data');

    $api($this->guardian)->postJson("/api/v1/recordings/{$recording->id}/watch")->assertOk()
        ->assertJsonPath('media', 'audio')->assertJson(fn ($json) => $json->where('url', fn ($url) => str_contains($url, "/recordings/{$recording->id}/media?expires="))->etc());
    $api($this->other)->postJson("/api/v1/recordings/{$recording->id}/watch")->assertForbidden();

    // In a lesson: the ticket says what the screen shows; its teacher starts and stops it, the student cannot.
    Storage::disk('local')->delete($recording->path);
    $api($this->student)->postJson("/api/v1/lessons/{$this->lesson->id}/join")->assertOk()
        ->assertJsonPath('recording', ['mode' => 'manual', 'controls' => false, 'running' => false, 'missing' => []]);
    $api($this->student)->postJson("/api/v1/lessons/{$this->lesson->id}/recording")->assertForbidden();
    $api($this->teacher)->postJson("/api/v1/lessons/{$this->lesson->id}/recording")->assertOk()->assertJsonPath('data.running', true);
    $api($this->teacher)->postJson("/api/v1/lessons/{$this->lesson->id}/join")->assertJsonPath('recording.running', true)->assertJsonPath('recording.controls', true);
    $api($this->student)->postJson("/api/v1/lessons/{$this->lesson->id}/recording/stop")->assertForbidden();
    $api($this->teacher)->postJson("/api/v1/lessons/{$this->lesson->id}/recording/stop")->assertOk()->assertJsonPath('data.running', false);
});
