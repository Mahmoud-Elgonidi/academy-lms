<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Actions\Recordings\DeleteRecording;
use Modules\Classroom\Actions\Recordings\GiveRecordingConsent;
use Modules\Classroom\Actions\Recordings\HoldRecording;
use Modules\Classroom\Actions\Recordings\RecordRecordingConsent;
use Modules\Classroom\Actions\Recordings\ReleaseRecording;
use Modules\Classroom\Actions\Recordings\SaveRecordingSettings;
use Modules\Classroom\Actions\Recordings\StartRecording;
use Modules\Classroom\Actions\Recordings\StopRecording;
use Modules\Classroom\Actions\Recordings\SweepRecordings;
use Modules\Classroom\Actions\Recordings\WatchRecording;
use Modules\Classroom\Actions\Recordings\WithdrawRecordingConsent;
use Modules\Classroom\Contracts\Events\RecordingReady;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\RecordingAccessLog;
use Modules\Classroom\Models\RecordingConsent;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\LiveKit\Jwt;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\Support\Actions\OpenTicket;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Http::fake([
        '*/twirp/livekit.RoomService/*' => Http::response(['name' => 'r']),
        '*/twirp/livekit.Egress/StartRoomCompositeEgress' => Http::sequence()->push(['egress_id' => 'EG_one', 'status' => 'EGRESS_STARTING'])->push(['egress_id' => 'EG_two', 'status' => 'EGRESS_STARTING']),
        '*/twirp/livekit.Egress/StopEgress' => Http::response(['egress_id' => 'EG_one', 'status' => 'EGRESS_ENDING']),
    ]);
    $this->enrolStudentOnPlan();
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();   // Tue 6 Oct, 15:00–15:30 UTC
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:02', 'UTC'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function recordingSettings(string $mode, array $more = []): void
{
    app(SaveRecordingSettings::class)->handle([
        'recording' => $mode, 'recording_media' => 'by_age', 'recording_days' => 30, 'recording_family' => true, 'recording_teacher' => true, ...$more,
    ], test()->admin->id);
}

function everyoneAgrees(): void
{
    app(GiveRecordingConsent::class)->handle(test()->teacher->id, test()->teacher->id);
    app(GiveRecordingConsent::class)->handle(test()->student->id, test()->student->id);
}

function openRoom(?User $who = null): void
{
    app(JoinLesson::class)->handle(test()->lesson->id, ($who ?? test()->teacher)->id);
}

function egressPost(string $event, array $info): TestResponse
{
    $body = json_encode(['event' => $event, 'id' => 'EV_'.$event.'_'.($info['status'] ?? ''), 'createdAt' => (string) now()->getTimestamp(),
        'egressInfo' => ['roomName' => Room::query()->sole()->name, ...$info]]);
    $jwt = Jwt::encode(['iss' => 'devkey', 'exp' => time() + 300, 'sha256' => base64_encode(hash('sha256', $body, true))], config('classroom.livekit.secret'));

    return test()->call('POST', '/webhooks/livekit', [], [], [], ['HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json'], $body);
}

function participantPost(string $event, string $identity): TestResponse
{
    $body = json_encode(['event' => $event, 'id' => 'EV_'.$event.'_'.$identity, 'createdAt' => (string) now()->getTimestamp(),
        'room' => ['name' => Room::query()->sole()->name], 'participant' => ['identity' => $identity]]);
    $jwt = Jwt::encode(['iss' => 'devkey', 'exp' => time() + 300, 'sha256' => base64_encode(hash('sha256', $body, true))], config('classroom.livekit.secret'));

    return test()->call('POST', '/webhooks/livekit', [], [], [], ['HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json'], $body);
}

/** A recording the recorder finished: its file on the disk, ready. */
function finishedRecording(): Recording
{
    recordingSettings('manual');
    everyoneAgrees();
    openRoom();
    $recording = app(StartRecording::class)->handle(test()->lesson->id, test()->teacher->id);
    Storage::disk('local')->put($recording->path, str_repeat('v', 2048));
    egressPost('egress_ended', ['egressId' => 'EG_one', 'status' => 'EGRESS_COMPLETE', 'startedAt' => '1791298920000000000', 'endedAt' => '1791300600000000000',
        'fileResults' => [['filename' => $recording->path, 'size' => '2048', 'duration' => '1680000000000']]])->assertNoContent();

    return $recording->refresh();
}

it('lets a teacher and an adult student agree for themselves, and a guardian only for a child', function () {
    $give = app(GiveRecordingConsent::class);
    expect($give->handle($this->teacher->id, $this->teacher->id))->toMatchArray(['role' => 'teacher', 'via' => 'self'])
        ->and($give->handle($this->student->id, $this->student->id))->toMatchArray(['role' => 'student', 'via' => 'self']);
    // The student is an adult: their guardian does not agree for them; nobody agrees for someone else.
    expect(fn () => $give->handle($this->student->id, $this->guardian->id))->not->toThrow(Throwable::class);   // a consent already stands: kept as it is
    RecordingConsent::query()->where('person_id', $this->student->id)->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->student->id]);
    expect(fn () => $give->handle($this->student->id, $this->guardian->id))->toThrow(AuthorizationException::class)
        ->and(fn () => $give->handle($this->teacher->id, $this->admin->id))->toThrow(AuthorizationException::class)
        ->and(fn () => $give->handle($this->admin->id, $this->admin->id))->toThrow(AuthorizationException::class);   // staff are not recorded

    // A child: the guardian agrees, the child does not.
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2016-03-01'), null);
    expect(fn () => $give->handle($this->student->id, $this->student->id))->toThrow(AuthorizationException::class)
        ->and($give->handle($this->student->id, $this->guardian->id))->toMatchArray(['via' => 'guardian', 'given_by' => $this->guardian->id])
        ->and(fn () => $give->handle($this->student->id, $this->guardian->id, 99))->not->toThrow(Throwable::class);    // standing already
    expect(DB::table('audit_logs')->where('action', 'classroom.recording_consent_given')->count())->toBe(3);
});

it('keeps what was agreed: withdrawn once, never edited or deleted, and the office needs a note', function () {
    app(GiveRecordingConsent::class)->handle($this->teacher->id, $this->teacher->id);
    $consent = app(WithdrawRecordingConsent::class)->handle($this->teacher->id, $this->teacher->id);
    expect($consent->withdrawn_at)->not->toBeNull()
        ->and(fn () => DB::table('recording_consents')->where('id', $consent->id)->update(['via' => 'staff']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('recording_consents')->where('id', $consent->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => app(WithdrawRecordingConsent::class)->handle($this->teacher->id, $this->teacher->id))->toThrow(ValidationException::class);

    expect(fn () => app(RecordRecordingConsent::class)->handle($this->teacher->id, $this->admin->id, 'short'))->toThrow(ValidationException::class);
    $paper = app(RecordRecordingConsent::class)->handle($this->teacher->id, $this->admin->id, 'Recording clause signed in the contract, Sept 2026');
    expect($paper)->toMatchArray(['via' => 'staff', 'recorded_by' => $this->admin->id])->and($paper->given_by)->toBeNull()
        ->and(fn () => app(WithdrawRecordingConsent::class)->handle($this->teacher->id, $this->admin->id, asStaff: true))->toThrow(ValidationException::class);
});

it('records only when the academy does, the room is open, and everyone agreed', function () {
    $start = app(StartRecording::class);
    expect(fn () => $start->handle($this->lesson->id, $this->teacher->id))->toThrow(ValidationException::class, __('classroom::recordings.errors.off'));

    recordingSettings('manual');
    expect(fn () => $start->handle($this->lesson->id, $this->teacher->id))->toThrow(ValidationException::class, __('classroom::recordings.errors.no_room'));
    openRoom();
    app(GiveRecordingConsent::class)->handle($this->teacher->id, $this->teacher->id);
    expect(fn () => $start->handle($this->lesson->id, $this->teacher->id))->toThrow(ValidationException::class, $this->student->name);
    app(GiveRecordingConsent::class)->handle($this->student->id, $this->student->id);

    // Only whoever runs the lesson starts it: not the student, not another teacher.
    expect(fn () => $start->handle($this->lesson->id, $this->student->id))->toThrow(AuthorizationException::class)
        ->and(fn () => $start->handle($this->lesson->id, $this->other->id))->toThrow(AuthorizationException::class);

    $recording = $start->handle($this->lesson->id, $this->teacher->id);
    // An adult in the lesson: sound only, by age.
    expect($recording)->toMatchArray(['status' => 'starting', 'media' => 'audio', 'egress_id' => 'EG_one', 'started_by' => $this->teacher->id, 'mime' => 'audio/ogg'])
        ->and($recording->path)->toBe("academies/{$this->academy->id}/recordings/{$this->lesson->id}/{$recording->id}.ogg");
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'StartRoomCompositeEgress') && $r['audio_only'] === true
        && $r['file_outputs'][0]['file_type'] === 'OGG' && $r['file_outputs'][0]['filepath'] === Storage::disk('local')->path($recording->path)
        && Jwt::decode(str_replace('Bearer ', '', $r->header('Authorization')[0]), config('classroom.livekit.secret'))['video'] === ['roomRecord' => true]);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'UpdateRoomMetadata') && json_decode($r['metadata'], true) === ['recording' => true]);

    // Pressing again, or a supervisor pressing, finds the same one running.
    expect($start->handle($this->lesson->id, $this->supervisor->id)->id)->toBe($recording->id)
        ->and(Recording::query()->count())->toBe(1);
});

it('records a child\'s lesson on video, an adult\'s as sound, unless the academy chose one', function () {
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2016-03-01'), null);
    recordingSettings('manual');
    app(GiveRecordingConsent::class)->handle($this->teacher->id, $this->teacher->id);
    app(GiveRecordingConsent::class)->handle($this->student->id, $this->guardian->id);
    openRoom();

    $recording = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);
    expect($recording)->toMatchArray(['media' => 'video', 'mime' => 'video/mp4']);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'StartRoomCompositeEgress') && $r['audio_only'] === false && $r['file_outputs'][0]['file_type'] === 'MP4'
        && $r['advanced']['width'] === 640 && $r['advanced']['video_bitrate'] === 500);
});

it('follows the recorder: running, then ready with its days, and says so once', function () {
    Event::fake([RecordingReady::class]);
    recordingSettings('manual', ['recording_days' => 14]);
    everyoneAgrees();
    openRoom();
    $recording = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);

    egressPost('egress_started', ['egressId' => 'EG_one', 'status' => 'EGRESS_ACTIVE', 'startedAt' => '1791298920000000000'])->assertNoContent();
    expect($recording->refresh())->toMatchArray(['status' => 'recording'])
        ->and($recording->started_at->toIso8601ZuluString())->toBe('2026-10-06T15:02:00Z');

    $ended = egressPost('egress_ended', ['egressId' => 'EG_one', 'status' => 'EGRESS_COMPLETE', 'endedAt' => '1791300600000000000',
        'fileResults' => [['filename' => $recording->path, 'size' => '5242880', 'duration' => '1680000000000']]]);
    $ended->assertNoContent();
    expect($recording->refresh())->toMatchArray(['status' => 'ready', 'size' => 5242880, 'duration_sec' => 1680])
        ->and($recording->expires_at->toIso8601ZuluString())->toBe('2026-10-20T15:30:00Z');
    Event::assertDispatchedTimes(RecordingReady::class, 1);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'UpdateRoomMetadata') && json_decode($r['metadata'], true) === ['recording' => false]);

    // A late or repeated word changes nothing.
    egressPost('egress_updated', ['egressId' => 'EG_one', 'status' => 'EGRESS_ACTIVE']);
    expect($recording->refresh()->status)->toBe('ready');
});

it('keeps a recording stopped at once ending when the word that it started comes late', function () {
    recordingSettings('manual');
    everyoneAgrees();
    openRoom();
    $recording = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);
    app(StopRecording::class)->handle($recording, $this->teacher->id);

    egressPost('egress_started', ['egressId' => 'EG_one', 'status' => 'EGRESS_ACTIVE', 'startedAt' => '1791298920000000000']);
    expect($recording->refresh()->status)->toBe('ending')
        ->and($recording->started_at)->not->toBeNull();
});

it('marks a recording that made no file as failed', function () {
    recordingSettings('manual');
    everyoneAgrees();
    openRoom();
    $recording = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);
    egressPost('egress_ended', ['egressId' => 'EG_one', 'status' => 'EGRESS_FAILED', 'error' => 'could not connect', 'fileResults' => []]);

    expect($recording->refresh())->toMatchArray(['status' => 'failed', 'error' => 'could not connect']);
});

it('stops when whoever runs the lesson asks, and when a consent is taken back', function () {
    recordingSettings('manual');
    everyoneAgrees();
    openRoom();
    $recording = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);
    expect(app(StopRecording::class)->handle($recording, $this->teacher->id))->toMatchArray(['status' => 'ending', 'stopped_by' => $this->teacher->id]);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'StopEgress') && $r['egress_id'] === 'EG_one');
    egressPost('egress_ended', ['egressId' => 'EG_one', 'status' => 'EGRESS_COMPLETE', 'fileResults' => [['size' => '100', 'duration' => '60000000000']]]);

    // A second recording; the student takes their consent back in the middle of it.
    $second = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);
    expect($second->egress_id)->toBe('EG_two');
    app(WithdrawRecordingConsent::class)->handle($this->student->id, $this->student->id);
    expect($second->refresh()->status)->toBe('ending')
        ->and(fn () => app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id))->toThrow(ValidationException::class);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'StopEgress') && $r['egress_id'] === 'EG_two');
});

it('records every lesson by itself once the teacher and the student are both in, and not again after someone stopped it', function () {
    recordingSettings('auto');
    everyoneAgrees();
    openRoom();

    participantPost('participant_joined', $this->teacher->id)->assertNoContent();
    expect(Recording::query()->count())->toBe(0);
    participantPost('participant_joined', $this->student->id)->assertNoContent();
    $recording = Recording::query()->sole();
    expect($recording)->toMatchArray(['started_by' => null, 'status' => 'starting']);

    app(StopRecording::class)->handle($recording, $this->teacher->id);
    participantPost('participant_left', $this->student->id);
    participantPost('participant_joined', $this->student->id.'');
    expect(Recording::query()->count())->toBe(1);
    Http::assertSentCount(1 + 1 + 1 + 1 + 1);   // CreateRoom, start, metadata on, stop, metadata off
});

it('does not record by itself when someone has not agreed', function () {
    recordingSettings('auto');
    app(GiveRecordingConsent::class)->handle($this->teacher->id, $this->teacher->id);
    openRoom();
    participantPost('participant_joined', $this->teacher->id);
    participantPost('participant_joined', $this->student->id);

    expect(Recording::query()->count())->toBe(0);
});

it('lets the office, the lesson\'s teacher and its family watch, logs each opening, and plays it in the page', function () {
    $recording = finishedRecording();
    $watch = app(WatchRecording::class);

    $link = $watch->handle($recording, $this->admin->id, '10.0.0.1', 'Test browser');
    expect($link->url)->toContain('noor.lms.test')->toContain('/recordings/'.$recording->id.'/media?expires=');
    $local = 'http://noor.lms.test'.parse_url($link->url, PHP_URL_PATH).'?'.parse_url($link->url, PHP_URL_QUERY);
    $watch->handle($recording, $this->teacher->id);
    $watch->handle($recording, $this->guardian->id);
    $watch->handle($recording, $this->student->id);
    expect(RecordingAccessLog::query()->orderBy('opened_at')->pluck('as')->all())->toBe(['staff', 'teacher', 'family', 'family'])
        ->and(RecordingAccessLog::query()->where('as', 'staff')->sole())->toMatchArray(['ip' => '10.0.0.1', 'user_agent' => 'Test browser']);

    // Another teacher may not; and when the academy says so, neither may the family.
    expect(fn () => $watch->handle($recording, $this->other->id))->toThrow(AuthorizationException::class);
    recordingSettings('manual', ['recording_family' => false]);
    expect(fn () => $watch->handle($recording, $this->guardian->id))->toThrow(AuthorizationException::class)
        ->and(RecordingAccessLog::query()->count())->toBe(4);

    // The log is kept as it happened.
    expect(fn () => DB::table('recording_access_logs')->update(['as' => 'family']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('recording_access_logs')->delete())->toThrow(QueryException::class);

    $this->get($local)->assertOk()->assertHeader('Content-Type', 'audio/ogg')->assertHeader('Content-Disposition', 'inline; filename=lesson-2026-10-06.ogg');
    $this->get($local.'x')->assertForbidden();
});

it('keeps a recording its days, longer when held or under an open complaint, and removes it after', function () {
    $recording = finishedRecording();
    $sweep = app(SweepRecordings::class);
    expect($recording->expires_at->toIso8601ZuluString())->toBe('2026-11-05T15:30:00Z');

    // Held by the office.
    app(HoldRecording::class)->handle($recording, $this->admin->id, 'The family disputes what was taught');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 00:00', 'UTC'));
    expect($sweep->handle()['removed'])->toBe(0)
        ->and(fn () => app(DeleteRecording::class)->handle($recording, $this->admin->id, 'The family asked for it'))->toThrow(ValidationException::class);

    // Released, but a complaint about the lesson is open.
    app(ReleaseRecording::class)->handle($recording->refresh());
    app(OpenTicket::class)->handle($this->guardian->id, 'family', 'complaint', 'The teacher was late', 'The lesson started a quarter of an hour late.', $this->student->id, $this->lesson->id);
    expect($sweep->handle()['removed'])->toBe(0)
        ->and(fn () => app(DeleteRecording::class)->handle($recording->refresh(), $this->admin->id, 'The family asked for it'))->toThrow(ValidationException::class, __('classroom::recordings.errors.complaint'));

    // Closed: its days are up, it goes; the row stays.
    DB::table('tickets')->update(['status' => 'closed', 'resolved_at' => now(), 'closed_at' => now(), 'complaint_status' => 'dismissed', 'complaint_decided_at' => now()]);
    expect($sweep->handle()['removed'])->toBe(1)
        ->and($recording->refresh())->toMatchArray(['status' => 'deleted', 'delete_reason' => 'expired', 'deleted_by' => null])
        ->and(Storage::disk('local')->exists($recording->path))->toBeFalse()
        ->and(DB::table('audit_logs')->whereIn('action', ['classroom.recording_held', 'classroom.recording_released'])->count())->toBe(2);
});

it('lets the office remove one early, saying why', function () {
    $recording = finishedRecording();
    expect(fn () => app(DeleteRecording::class)->handle($recording, $this->admin->id, 'no'))->toThrow(ValidationException::class);

    app(DeleteRecording::class)->handle($recording, $this->admin->id, 'The family asked for it to be removed');
    expect($recording->refresh())->toMatchArray(['status' => 'deleted', 'deleted_by' => $this->admin->id])
        ->and(Storage::disk('local')->exists($recording->path))->toBeFalse()
        ->and(fn () => app(WatchRecording::class)->handle($recording, $this->admin->id))->toThrow(ValidationException::class)
        ->and(DB::table('audit_logs')->where('action', 'classroom.recording_deleted')->value('data'))->toContain('The family asked for it to be removed');
});

it('stops recordings of lessons that are over, settles quiet ones, and counts what is kept in the plan\'s storage', function () {
    recordingSettings('manual');
    everyoneAgrees();
    openRoom();
    $recording = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:45', 'UTC'));    // the lesson ended at 15:30, the room closed at 15:40
    expect(app(SweepRecordings::class)->handle()['stopped'])->toBe(1)
        ->and($recording->refresh()->status)->toBe('ending');

    // The recorder never said it finished; the file is there.
    Storage::disk('local')->put($recording->path, str_repeat('a', 3 * 1048576));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
    expect(app(SweepRecordings::class)->handle()['settled'])->toBe(1)
        ->and($recording->refresh())->toMatchArray(['status' => 'ready', 'size' => 3 * 1048576])
        ->and(app(UsageMeters::class)->measure(Limit::StorageMb, UsagePeriod::monthOf(now()->toImmutable(), 'UTC')))->toBe(3);
});

it('stops what is running when the academy stops recording', function () {
    recordingSettings('manual');
    everyoneAgrees();
    openRoom();
    $recording = app(StartRecording::class)->handle($this->lesson->id, $this->teacher->id);

    recordingSettings('off');
    expect($recording->refresh()->status)->toBe('ending')
        ->and(DB::table('audit_logs')->where('action', 'classroom.recording_settings_saved')->count())->toBe(2);
});
