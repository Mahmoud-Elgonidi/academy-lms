<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\LiveKit\Jwt;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\AttendanceSegment;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The first academies teach through each teacher's own Meet or Zoom link
 * (docs/architecture/03, section 6: ExternalLink): a server without LiveKit
 * of its own offers nothing else and takes no LiveKit webhooks, and whoever
 * comes in, from the class page, the apps or the portal, counts as there.
 */

beforeEach(function () {
    Mail::fake();
    Http::fake(['*/twirp/livekit.RoomService/CreateRoom' => Http::response(['name' => 'r'])]);
    $this->enrolStudentOnPlan();
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();   // Tue 6 Oct, 15:00–15:30 UTC
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    app()->detectEnvironment(fn () => 'testing');
});

/** This server as a real one: production, with LiveKit's key and secret as given. */
function onRealServer(?string $secret): void
{
    app()->detectEnvironment(fn () => 'production');
    config(['classroom.livekit.secret' => $secret]);
}

function signedLiveKitPost(array $event, string $secret): TestResponse
{
    $body = json_encode($event);
    $jwt = Jwt::encode(['iss' => config('classroom.livekit.key'), 'exp' => CarbonImmutable::now()->getTimestamp() + 300, 'sha256' => base64_encode(hash('sha256', $body, true))], $secret);

    return test()->call('POST', '/webhooks/livekit', [], [], [], ['HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json'], $body);
}

function apiTokenFor(User $user): string
{
    return test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');
}

/** The join button in the app or the portal, as this person. */
function joinByApi(string $token, string $lessonId): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->postJson("/api/v1/lessons/{$lessonId}/join");
}

it('refuses LiveKit webhooks on a real server whose secret is empty or the development one anyone can read', function () {
    $dev = (string) config('classroom.livekit.secret');
    $room = Room::query()->create(['session_id' => $this->lesson->id, 'provider' => 'livekit', 'name' => Room::nameFor($this->academy->id, $this->lesson->id)]);
    $joined = fn (string $id) => ['event' => 'participant_joined', 'id' => $id, 'createdAt' => CarbonImmutable::parse('2026-10-06 15:00 UTC')->getTimestamp(), 'room' => ['name' => $room->name], 'participant' => ['identity' => $this->teacher->id]];
    $this->clearTenant();

    onRealServer($dev);
    signedLiveKitPost($joined('EV_dev'), $dev)->assertUnauthorized();
    onRealServer('');
    signedLiveKitPost($joined('EV_empty'), '')->assertUnauthorized();

    $own = Str::random(48);     // the server's own secret
    onRealServer($own);
    signedLiveKitPost($joined('EV_own'), $own)->assertNoContent();

    $this->actAsTenant($this->academy);
    expect(AttendanceSegment::query()->pluck('user_id')->all())->toBe([$this->teacher->id]);
});

it('lets an academy choose LiveKit only on a server that has its own, and otherwise uses the teacher\'s link', function () {
    app(SaveClassroomSettings::class)->handle('livekit');          // a development server: LiveKit in docker
    app(SaveMeetingLink::class)->handle($this->teacher->id, 'https://meet.google.com/abc-defg-hij');

    onRealServer((string) config('classroom.livekit.secret'));
    expect(fn () => app(SaveClassroomSettings::class)->handle('livekit'))->toThrow(ValidationException::class)
        ->and(ClassroomSettings::current()->provider)->toBe('external')
        ->and(app(JoinLesson::class)->handle($this->lesson->id, $this->student->id, CarbonImmutable::parse('2026-10-06 15:00 UTC'))->toArray())
        ->toMatchArray(['provider' => 'external', 'url' => 'https://meet.google.com/abc-defg-hij', 'token' => null])
        ->and(Room::query()->sole()->provider)->toBe('external');

    // The academy's choice itself is kept for a server that gets LiveKit later.
    expect(ClassroomSettings::query()->value('provider'))->toBe('livekit');
});

it('counts whoever comes in by the teacher\'s link from the apps and the portal, and closes the lesson as held', function () {
    app(SaveClassroomSettings::class)->handle('external');
    app(SaveMeetingLink::class)->handle($this->teacher->id, 'https://meet.google.com/abc-defg-hij');
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    $tokens = ['teacher' => apiTokenFor($this->teacher), 'student' => apiTokenFor($this->student), 'supervisor' => apiTokenFor($supervisor)];
    $this->clearTenant();

    CarbonImmutable::setTestNow('2026-10-06 14:58 UTC');
    joinByApi($tokens['teacher'], $this->lesson->id)
        ->assertOk()->assertJsonPath('data.provider', 'external')->assertJsonPath('data.url', 'https://meet.google.com/abc-defg-hij');
    CarbonImmutable::setTestNow('2026-10-06 15:02 UTC');
    joinByApi($tokens['student'], $this->lesson->id)->assertOk()->assertJsonPath('data.role', 'student');
    joinByApi($tokens['supervisor'], $this->lesson->id)->assertOk()->assertJsonPath('data.role', 'observer');
    // Tapping the button again does not open a second stretch.
    joinByApi($tokens['student'], $this->lesson->id)->assertOk();

    $this->actAsTenant($this->academy);
    expect(AttendanceSegment::query()->orderBy('joined_at')->get()->map(fn ($s) => [$s->user_id, $s->joined_at->format('H:i'), $s->source])->all())
        ->toBe([[$this->teacher->id, '14:58', 'external_link'], [$this->student->id, '15:02', 'external_link']])
        ->and($this->lesson->refresh()->status)->toBe(SessionStatus::InProgress);

    // Ten minutes after the end, nobody is marked absent: the lesson was held.
    expect(app(ApplyAttendanceDeadlines::class)->handle(CarbonImmutable::parse('2026-10-06 15:40 UTC')))->toBe(1)
        ->and($this->lesson->refresh()->status)->toBe(SessionStatus::Completed);
});
