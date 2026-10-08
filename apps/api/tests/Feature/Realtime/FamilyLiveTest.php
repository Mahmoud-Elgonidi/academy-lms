<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Live\HomeworkChanged;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Live\LessonsChanged;
use Modules\SharedKernel\Realtime\LiveSignals;
use Tests\Support\EnrolledStudent;

/*
 * Live updates for families and the apps (the portal, then the phone): a
 * lesson or homework change reaches the student and their guardians, not
 * only the teacher, on each person's own channel; the apps learn where to
 * listen and get their subscription signed with their token.
 */

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A second connection that hears what the database raises, as live:relay does. */
function familyListen(): PDO
{
    config(['database.connections.family_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('family_listener')->getPdo();
    $pdo->exec('listen lms_live');

    return $pdo;
}

/** @return list<string> what was raised since the last call */
function familyRaised(PDO $pdo): array
{
    $raised = [];
    while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
        $raised[] = $note['payload'];
    }

    return $raised;
}

function familyReverb(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
            'client' => ['host' => null, 'port' => 443, 'scheme' => 'https'],
        ],
    ]);
    foreach (Broadcast::driver('null')->getChannels() as $pattern => $callback) {
        Broadcast::driver('reverb')->channel($pattern, $callback);
    }
}

/** The app or the portal, signed in as this person with a bearer token. */
function liveApp(User $user): string
{
    $token = $user->createToken('portal')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();

    return $token;
}

it('tells the student and their guardians, not only the teacher, when one of their lessons changes', function () {
    $lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $listener = familyListen();

    app(CancelSession::class)->handle($lesson, 'teacher', 'illness', null, $this->admin->id);

    expect(familyRaised($listener))->toContain("lessons:{$this->teacher->id}", "lessons:{$this->student->id}", "lessons:{$this->guardian->id}");
});

it('tells the family when a student is booked into a lesson, but not on every attendance tick', function () {
    $lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $listener = familyListen();

    DB::table('session_participants')->where('session_id', $lesson->id)->where('user_id', $this->student->id)->update(['attended_min' => 12, 'first_join_at' => now()]);
    expect(familyRaised($listener))->not->toContain("lessons:{$this->student->id}");

    DB::table('session_participants')->where('session_id', $lesson->id)->where('user_id', $this->student->id)->update(['is_active' => false]);
    expect(familyRaised($listener))->toContain("lessons:{$this->student->id}", "lessons:{$this->guardian->id}");
});

it('tells the teacher, the student and the guardians when homework is given, every time it is handed in, and when it is marked', function () {
    $listener = familyListen();
    $everyone = ["homework:{$this->teacher->id}", "homework:{$this->student->id}", "homework:{$this->guardian->id}"];

    $homework = app(AssignHomework::class)->handle([
        'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id, 'title' => 'سورة الملك',
        'accepts' => 'any', 'due_at' => CarbonImmutable::now()->addDays(3),
    ]);
    expect(familyRaised($listener))->toContain(...$everyone);

    app(SubmitHomework::class)->handle($homework, $this->student->id, 'حفظت أول خمس آيات', null);
    expect(familyRaised($listener))->toContain(...$everyone);

    // A second hand-in changes nothing on the homework row itself; the submission still tells everyone.
    app(SubmitHomework::class)->handle($homework->refresh(), $this->guardian->id, null, UploadedFile::fake()->create('page.pdf', 20, 'application/pdf'));
    expect(familyRaised($listener))->toContain(...$everyone);

    app(ReviewHomework::class)->handle($homework->refresh(), $this->teacher->id, 5, 'ممتاز');
    expect(familyRaised($listener))->toContain(...$everyone);
});

it('turns lesson and homework signals into a data-less broadcast on the person\'s own channel', function () {
    $signals = app(LiveSignals::class);

    $lessons = $signals->event("lessons:{$this->guardian->id}");
    expect($lessons)->toBeInstanceOf(LessonsChanged::class)
        ->and($lessons->broadcastOn()[0]->name)->toBe("private-user.{$this->guardian->id}")
        ->and($lessons->broadcastAs())->toBe('lessons.changed')
        ->and($lessons->broadcastWith())->toBe([]);

    $homework = $signals->event("homework:{$this->student->id}");
    expect($homework)->toBeInstanceOf(HomeworkChanged::class)
        ->and($homework->broadcastOn()[0]->name)->toBe("private-user.{$this->student->id}")
        ->and($homework->broadcastAs())->toBe('homework.changed');
});

it('tells the apps where to listen, and that there is nothing to listen to when the server does not broadcast', function () {
    $token = liveApp($this->guardian);

    $this->withToken($token)->getJson('/api/v1/realtime')->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.key', null)
        ->assertJsonPath('data.channel', "user.{$this->guardian->id}");
    $this->withToken($token)->postJson('/api/v1/realtime/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-user.{$this->guardian->id}"])
        ->assertNotFound();

    familyReverb();
    $this->withToken($token)->getJson('/api/v1/realtime')->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.key', 'test-key')
        ->assertJsonPath('data.host', null)        // the page's own host
        ->assertJsonPath('data.port', 443)
        ->assertJsonPath('data.tls', true);

    app('auth')->forgetGuards();
    $this->withoutToken()->getJson('http://noor.lms.test/api/v1/realtime')->assertUnauthorized();
});

it('signs a subscription to the person\'s own channel only, with the app\'s token', function () {
    familyReverb();
    $token = liveApp($this->student);
    $auth = fn (string $channel, string $socket = '1234.5678') => $this->withToken($token)
        ->postJson('/api/v1/realtime/auth', ['socket_id' => $socket, 'channel_name' => $channel]);

    $signed = $auth("private-user.{$this->student->id}")->assertOk()->json('auth');
    expect($signed)->toStartWith('test-key:')
        ->and(explode(':', $signed)[1])->toBe(hash_hmac('sha256', "1234.5678:private-user.{$this->student->id}", 'test-secret'));

    $auth("private-user.{$this->guardian->id}")->assertForbidden();                  // someone else's
    $auth("private-academy.{$this->academy->id}.board")->assertForbidden();          // students do not see the board
    $auth("private-user.{$this->student->id}", 'not-a-socket')->assertUnprocessable();
    $auth("presence-user.{$this->student->id}")->assertUnprocessable();
});
