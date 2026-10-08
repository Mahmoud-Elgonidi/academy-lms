<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Classroom\Filament\Teacher\MyLessonsToday;
use Modules\Communication\Actions\MarkNotificationsRead;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Filament\NotificationBell;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\LogContactAttempt;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Pages\LiveBoard;

/*
 * Live screens: a committed change raises "{topic}:{id}" in Postgres,
 * live:relay turns it into a broadcast on the right private channel, and
 * the open screens refresh on it instead of on a timer.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->student = User::factory()->withRole(Role::Student)->create();
    $this->admin = User::factory()->withRole(Role::Admin)->create();
    $this->subject = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function liveBook(string $start = '19:00'): Session
{
    return app(ScheduleSession::class)->handle(new NewSession(
        teacherId: test()->teacher->id, studentId: test()->student->id, subjectId: test()->subject,
        startsAt: CarbonImmutable::parse("2026-10-05 {$start}", 'UTC'), durationMin: 45, anchorTimezone: 'Africa/Cairo',
    ));
}

/** A second connection that waits for what the database raises, as live:relay does. */
function liveListen(): PDO
{
    config(['database.connections.live_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('live_listener')->getPdo();
    $pdo->exec('listen lms_live');

    return $pdo;
}

/** @return list<string> what was raised since the last call, sorted */
function liveRaised(PDO $pdo): array
{
    $raised = [];
    while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
        $raised[] = $note['payload'];
    }
    sort($raised);

    return $raised;
}

function useReverb(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
            'client' => ['host' => null, 'port' => 8080, 'scheme' => 'http'],
        ],
    ]);
    // The modules declared their channels on the broadcaster in use at boot (none in tests).
    foreach (Broadcast::driver('null')->getChannels() as $pattern => $callback) {
        Broadcast::driver('reverb')->channel($pattern, $callback);
    }
}

it('raises each committed change once, for the academy\'s board and the lessons of its teacher and student, and nothing for a refused one', function () {
    $listener = liveListen();
    $board = "board:{$this->academy->id}";
    $lessons = "lessons:{$this->teacher->id}";
    $booked = [$board, $lessons, "lessons:{$this->student->id}"];
    sort($booked);

    $session = liveBook();                                                   // the lesson, its two people and its log: one transaction
    expect(liveRaised($listener))->toBe($booked);

    app(LogContactAttempt::class)->handle($session, 'teacher', 'running_late', null, $this->admin->id);
    expect(liveRaised($listener))->toBe([$board]);                           // only the log: the teacher's day is the same

    expect(fn () => liveBook('19:15'))->toThrow(ValidationException::class);  // the teacher is booked: rolled back, nothing raised
    expect(liveRaised($listener))->toBe([]);

    app(CancelSession::class)->handle($session->refresh(), 'teacher', 'illness', null, $this->admin->id);
    expect(liveRaised($listener))->toContain($board, $lessons);
});

it('raises "inbox" for the person a message is for, and again when they read it', function () {
    app(NotificationsApi::class)->define(new TemplateDefinition(
        key: 'test.live', category: 'lessons', title: 'test.live_title', body: 'test.live_body', audience: 'test.audience', channels: [], sample: [],
    ));
    $listener = liveListen();

    app(NotificationsApi::class)->send(new Message('test.live', [$this->admin->id], [], ['screen' => 'live_board']));
    expect(liveRaised($listener))->toBe(["inbox:{$this->admin->id}"]);

    app(MarkNotificationsRead::class)->handle($this->admin->id);
    expect(liveRaised($listener))->toBe(["inbox:{$this->admin->id}"]);
});

it('passes each change on to the screens, through the relay, as one signal on the right private channel', function () {
    $relay = Process::env([
        'BROADCAST_CONNECTION' => 'log', 'LOG_CHANNEL' => 'stderr', 'LOG_LEVEL' => 'debug', 'CACHE_STORE' => 'array',
        'APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.pgsql.database'),
    ])->path(base_path())->start('php artisan live:relay --for=8');

    $until = microtime(true) + 6;
    while (! str_contains($relay->output(), 'listening') && $relay->running() && microtime(true) < $until) {
        usleep(100_000);
    }
    expect($relay->output())->toContain('listening');

    liveBook();
    $until = microtime(true) + 4;
    while (substr_count($relay->errorOutput(), 'Broadcasting [') < 3 && microtime(true) < $until) {
        usleep(100_000);
    }
    $relay->stop();
    $log = $relay->errorOutput();

    expect($log)->toContain('Broadcasting [resync] on channels [lms.live]')   // on start, every open screen catches up
        ->and(substr_count($log, "Broadcasting [changed] on channels [private-academy.{$this->academy->id}.board]"))->toBe(1)
        ->and(substr_count($log, "Broadcasting [lessons.changed] on channels [private-user.{$this->teacher->id}]"))->toBe(1);
});

it('lets only someone of the academy who may see the board listen to it, and each person to their own channel only', function () {
    useReverb();
    $this->clearTenant();
    $auth = fn (User $user, string $channel) => $this->actingAs($user)
        ->post('http://noor.lms.test/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-{$channel}"]);

    $auth($this->admin, "academy.{$this->academy->id}.board")->assertOk()->assertJsonStructure(['auth']);
    $auth($this->admin, 'academy.01a11111-1111-7111-8111-111111111111.board')->assertForbidden();   // another academy's board
    $auth($this->teacher, "academy.{$this->academy->id}.board")->assertForbidden();                 // teachers do not see the board

    $auth($this->teacher, "user.{$this->teacher->id}")->assertOk();
    $auth($this->teacher, "user.{$this->admin->id}")->assertForbidden();
});

it('loads Echo on every panel page when the app broadcasts, pointed at the academy\'s own host', function () {
    $this->clearTenant();
    $this->actingAs($this->admin)->get('http://noor.lms.test/admin')->assertOk()
        ->assertDontSee('window.EchoFactory(', false)
        ->assertSee('window.LmsLive', false);

    useReverb();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->get('http://noor.lms.test/admin/login')->assertOk()->assertDontSee('window.EchoFactory(', false);   // no socket before signing in
    $this->actingAs($this->admin)->get('http://noor.lms.test/admin')->assertOk()
        ->assertSee('window.Echo = new window.EchoFactory(', false)
        ->assertSee('\\u0022wsHost\\u0022:\\u0022noor.lms.test\\u0022', false)   // @js() encodes the quotes
        ->assertSee('data-live-channel="academy.'.$this->academy->id.'.board"', false)
        ->assertSee('data-live-channel="user.'.$this->admin->id.'"', false);   // the bell
});

it('puts no timer on the live board, the bell or today\'s lessons', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(LiveBoard::class)->assertDontSeeHtml('wire:poll')->assertSet('live', false);
    Livewire::test(NotificationBell::class)->assertDontSeeHtml('wire:poll');

    useReverb();
    Livewire::test(LiveBoard::class)->assertSet('live', true);

    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 12:00', 'UTC'));
    liveBook('19:00');
    $page = Livewire::test(MyLessonsToday::class)->assertDontSeeHtml('wire:poll');
    // The next moment the clock changes the page: the classroom opens at 18:50 UTC.
    expect($page->get('today')['next'] ?? $page->instance()->today['next'])
        ->toBe(CarbonImmutable::parse('2026-10-05 18:50', 'UTC')->getTimestampMs());
});
