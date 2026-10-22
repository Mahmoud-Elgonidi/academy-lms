<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Actions\ReconcileRooms;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Models\WebhookInboxItem;
use Modules\Classroom\Video\LiveKit\Jwt;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\SendLessonReminders;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Domain\Models\AttendanceSegment;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

beforeEach(function () {
    Mail::fake();
    Http::fake(['*/twirp/livekit.RoomService/CreateRoom' => Http::response(['name' => 'r'])]);
    $this->enrolStudentOnPlan();
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();   // Tue 6 Oct, 15:00–15:30 UTC
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->stranger = User::factory()->withRole(Role::Student)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function ticketFor(User $user, string $time): array
{
    return app(JoinLesson::class)->handle(test()->lesson->id, $user->id, CarbonImmutable::parse("2026-10-06 {$time}", 'UTC'))->toArray();
}

function livekitPost(array $event): TestResponse
{
    $body = json_encode($event);
    $jwt = Jwt::encode(['iss' => 'devkey', 'exp' => CarbonImmutable::now()->getTimestamp() + 300, 'sha256' => base64_encode(hash('sha256', $body, true))], config('classroom.livekit.secret'));

    return test()->call('POST', '/webhooks/livekit', [], [], [], ['HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json'], $body);
}

it('opens the classroom ten minutes before the lesson and closes it ten minutes after', function () {
    expect(fn () => ticketFor($this->teacher, '14:49'))->toThrow(ValidationException::class);

    $ticket = ticketFor($this->teacher, '14:50');
    $room = Room::query()->sole();
    expect($ticket)->toMatchArray(['provider' => 'livekit', 'role' => 'teacher', 'identity' => $this->teacher->id, 'can_publish' => true, 'room' => $room->name])
        ->and(Jwt::decode($ticket['token'], config('classroom.livekit.secret'))['video'])->toMatchArray(['room' => $room->name, 'canPublish' => true, 'hidden' => false])
        ->and(Room::parseName($room->name))->toBe([$this->academy->id, $this->lesson->id]);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'CreateRoom') && $r['max_participants'] === 4);

    expect(fn () => ticketFor($this->teacher, '15:41'))->toThrow(ValidationException::class);
});

it('seats the student, a guardian at the student\'s device, and a hidden supervisor; nobody else', function () {
    expect(ticketFor($this->student, '15:00'))->toMatchArray(['role' => 'student', 'identity' => $this->student->id])
        ->and(ticketFor($this->guardian, '15:00'))->toMatchArray(['role' => 'student', 'identity' => $this->student->id])
        ->and($observer = ticketFor($this->admin, '15:00'))->toMatchArray(['role' => 'observer', 'identity' => "observer:{$this->admin->id}", 'can_publish' => false])
        ->and(Jwt::decode($observer['token'], config('classroom.livekit.secret'))['video'])->toMatchArray(['hidden' => true, 'canPublish' => false]);

    expect(fn () => ticketFor($this->stranger, '15:00'))->toThrow(ValidationException::class)
        ->and(fn () => ticketFor($this->other, '15:00'))->toThrow(ValidationException::class);   // another teacher
});

it('keeps a cancelled lesson shut', function () {
    app(CancelSession::class)->handle($this->lesson, 'academy', 'closure', null, $this->admin->id);

    expect(fn () => ticketFor($this->teacher, '15:00'))->toThrow(ValidationException::class);
});

it('turns signed LiveKit webhooks into attendance, once each, and ignores supervisors', function () {
    ticketFor($this->teacher, '14:55');
    $room = Room::query()->sole();
    $this->clearTenant();
    $at = CarbonImmutable::parse('2026-10-06 14:58', 'UTC')->getTimestamp();

    livekitPost(['event' => 'participant_joined', 'id' => 'EV_1', 'createdAt' => $at, 'room' => ['name' => $room->name], 'participant' => ['identity' => $this->teacher->id]])->assertNoContent();
    livekitPost(['event' => 'participant_joined', 'id' => 'EV_1', 'createdAt' => $at, 'room' => ['name' => $room->name], 'participant' => ['identity' => $this->teacher->id]])->assertNoContent();
    livekitPost(['event' => 'participant_joined', 'id' => 'EV_2', 'createdAt' => $at, 'room' => ['name' => $room->name], 'participant' => ['identity' => "observer:{$this->admin->id}"]])->assertNoContent();
    livekitPost(['event' => 'participant_joined', 'id' => 'EV_3', 'createdAt' => $at + 120, 'room' => ['name' => $room->name], 'participant' => ['identity' => $this->student->id]])->assertNoContent();
    livekitPost(['event' => 'room_finished', 'id' => 'EV_4', 'createdAt' => $at + 1800, 'room' => ['name' => $room->name]])->assertNoContent();
    $this->call('POST', '/webhooks/livekit', [], [], [], ['HTTP_AUTHORIZATION' => 'nope'], '{}')->assertUnauthorized();

    $this->actAsTenant($this->academy);
    expect(AttendanceSegment::query()->orderBy('joined_at')->get()->map(fn ($s) => [$s->role, $s->joined_at->format('H:i'), $s->left_at?->format('H:i')])->all())
        ->toBe([['teacher', '14:58', '15:28'], ['student', '15:00', '15:28']])
        ->and(WebhookInboxItem::query()->count())->toBe(4)
        ->and($this->lesson->refresh()->status->value)->toBe('in_progress')
        ->and(Room::query()->sole()->finished_at)->not->toBeNull();
});

it('fixes attendance from the room API when a webhook was lost', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:05', 'UTC'));
    ticketFor($this->teacher, '15:00');
    Http::fake(['*/ListParticipants' => Http::sequence()
        ->push(['participants' => [['identity' => $this->teacher->id], ['identity' => "observer:{$this->admin->id}"]]])
        ->push(['participants' => []])]);

    expect(app(ReconcileRooms::class)->handle())->toBe(1)
        ->and(app(AttendanceApi::class)->inClassroom($this->lesson->id))->toBe([$this->teacher->id]);
    expect(app(ReconcileRooms::class)->handle(CarbonImmutable::parse('2026-10-06 15:10', 'UTC')))->toBe(1)
        ->and(app(AttendanceApi::class)->inClassroom($this->lesson->id))->toBe([])
        ->and(AttendanceSegment::query()->value('source'))->toBe('reconcile');
});

it('sends people to the teacher\'s own link when the academy uses outside links, and records the click', function () {
    app(SaveClassroomSettings::class)->handle('external');
    expect(fn () => ticketFor($this->student, '15:00'))->toThrow(ValidationException::class);   // no link yet

    app(SaveMeetingLink::class)->handle($this->teacher->id, 'https://meet.google.com/abc-defg-hij');
    expect(ticketFor($this->student, '15:00'))->toMatchArray(['provider' => 'external', 'url' => 'https://meet.google.com/abc-defg-hij']);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:01', 'UTC'));
    $link = app(NotificationLinks::class)->personal(['screen' => 'session', 'id' => $this->lesson->id], $this->student->id);
    $this->clearTenant();
    $this->get($link)->assertOk();
    $this->get("http://noor.lms.test/class/{$this->lesson->id}/go")->assertRedirect('https://meet.google.com/abc-defg-hij');

    $this->actAsTenant($this->academy);
    expect(AttendanceSegment::query()->sole()->only(['user_id', 'source']))->toBe(['user_id' => $this->student->id, 'source' => 'external_link']);
});

it('lets the student in by the signed link in their message, and nobody without it', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:03', 'UTC'));
    app(SendLessonReminders::class)->handle();      // three minutes in, the student is not there: "join now"
    $body = Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'scheduling.join_now')->value('body');
    preg_match('#(https?://\S+/class/\S+)#', (string) $body, $m);
    expect($m[1] ?? null)->not->toBeNull();
    $this->clearTenant();

    $this->get($m[1])->assertOk()->assertSee('قرآن')->assertSee(config('classroom.client_js'), false)   // in the guardian's language (the academy's: Arabic)
        ->assertSee('6 أكتوبر · 6:00 م');                                                                  // on their clock, in the academy's 12 hours
    // The page reads the guardian's own clock from their row: 24 hours once they chose it.
    $this->actAsTenant($this->academy);
    $this->guardian->update(['time_format' => 'h24']);
    $this->clearTenant();
    $this->get($m[1])->assertOk()->assertSee('6 أكتوبر · 18:00')->assertDontSee('6:00 م');
    $this->postJson("http://noor.lms.test/class/{$this->lesson->id}/ticket")->assertOk()->assertJsonPath('data.identity', $this->student->id);

    app('auth')->forgetGuards();
    $this->flushSession();
    $this->get("http://noor.lms.test/class/{$this->lesson->id}")->assertForbidden();
    $this->get($m[1].'x')->assertForbidden();
});

it('shows the teacher the class page with today\'s plan beside the video', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 14:55', 'UTC'));
    $this->clearTenant();

    $this->actingAs($this->teacher)->get("http://noor.lms.test/class/{$this->lesson->id}")
        ->assertOk()->assertSee(__('classroom::classroom.plan.title'))->assertSee(__('classroom::classroom.roles.teacher'));
});

it('gives the app a ticket through the API', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 14:56', 'UTC'));
    $this->clearTenant();
    $token = $this->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $this->student->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');

    $this->withToken($token)->postJson("/api/v1/lessons/{$this->lesson->id}/join")
        ->assertOk()->assertJsonPath('data.provider', 'livekit')->assertJsonPath('data.role', 'student')->assertJsonPath('data.url', config('classroom.livekit.url'));
});
