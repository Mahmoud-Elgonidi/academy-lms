<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\LogContactAttempt;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Pages\LiveBoard;

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->student = User::factory()->withRole(Role::Student)->create();
    $this->admin = User::factory()->withRole(Role::Admin)->create();
    $this->subject = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
});

function lbBook(string $start = '19:00'): Session
{
    return app(ScheduleSession::class)->handle(new NewSession(
        teacherId: test()->teacher->id, studentId: test()->student->id, subjectId: test()->subject,
        startsAt: CarbonImmutable::parse("2026-10-05 {$start}", 'UTC'), durationMin: 45, anchorTimezone: 'Africa/Cairo',
    ));
}

/** A second connection that waits for the board's signals, as `scheduling:live` does. */
function lbListen(): PDO
{
    config(['database.connections.board_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('board_listener')->getPdo();
    $pdo->exec('listen lms_board');

    return $pdo;
}

/** @return list<string> the academy ids signalled since the last call */
function lbSignals(PDO $pdo): array
{
    $ids = [];
    while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
        $ids[] = $note['payload'];
    }

    return $ids;
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

it('signals each committed change once, for its own academy, and nothing for a change that was refused', function () {
    $listener = lbListen();

    $session = lbBook();                                                  // the lesson, its two people and its log: one transaction
    expect(lbSignals($listener))->toBe([$this->academy->id]);

    app(LogContactAttempt::class)->handle($session, 'teacher', 'running_late', null, $this->admin->id);
    expect(lbSignals($listener))->toBe([$this->academy->id]);

    expect(fn () => lbBook('19:15'))->toThrow(ValidationException::class); // the teacher is booked: rolled back, nothing sent
    expect(lbSignals($listener))->toBe([]);

    $other = $this->actAsTenant($this->createAcademy(['slug' => 'other']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->student = User::factory()->withRole(Role::Student)->create();
    $this->subject = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    lbBook();
    expect(lbSignals($listener))->toBe([$other->id]);
});

it('passes the change on to the browsers, through the relay, as one signal on the academy\'s private channel', function () {
    $relay = Process::env([
        'BROADCAST_CONNECTION' => 'log', 'LOG_CHANNEL' => 'stderr', 'LOG_LEVEL' => 'debug', 'CACHE_STORE' => 'array',
        'APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.pgsql.database'),
    ])->path(base_path())->start('php artisan scheduling:live --for=8');

    $until = microtime(true) + 6;
    while (! str_contains($relay->output(), 'listening') && $relay->running() && microtime(true) < $until) {
        usleep(100_000);
    }
    expect($relay->output())->toContain('listening');

    lbBook();
    $until = microtime(true) + 4;
    while (! str_contains($relay->errorOutput(), 'academy.') && microtime(true) < $until) {
        usleep(100_000);
    }
    $relay->stop();
    $log = $relay->errorOutput();

    expect(substr_count($log, "Broadcasting [changed] on channels [private-academy.{$this->academy->id}.board]"))->toBe(1)
        ->and($log)->toContain('Broadcasting [resync] on channels [lms.live]');   // on start, every open board catches up
});

it('lets only someone of the academy who may see the board listen to it', function () {
    useReverb();
    $this->clearTenant();
    $auth = fn (User $user, string $academyId) => $this->actingAs($user)
        ->post('http://noor.lms.test/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-academy.{$academyId}.board"]);

    $auth($this->admin, $this->academy->id)->assertOk()->assertJsonStructure(['auth']);
    $auth($this->admin, '01a11111-1111-7111-8111-111111111111')->assertForbidden();   // another academy's board
    $auth($this->teacher, $this->academy->id)->assertForbidden();                       // teachers do not see the board
});

it('opens the board in live mode only when the app broadcasts, with no timer on the page', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(LiveBoard::class)->assertSet('live', false);

    useReverb();
    Livewire::test(LiveBoard::class)->assertSet('live', true)
        ->assertSeeHtml('data-lb-channel="academy.'.$this->academy->id.'.board"')
        ->assertDontSeeHtml('wire:poll');
    expect(config('filament.broadcasting.echo'))->toMatchArray(['broadcaster' => 'reverb', 'key' => 'test-key', 'wsPort' => 8080, 'forceTLS' => false]);
});
