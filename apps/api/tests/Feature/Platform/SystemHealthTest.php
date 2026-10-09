<?php

use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\Platform\Filament\Console\Pages\Health;
use Modules\Platform\Health\HealthAlert;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Health\WorkerBeat;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Health\Heartbeats;
use Modules\SharedKernel\Health\RecentErrors;

/*
 * The stack's health (docs/architecture/03, section 12): heartbeats from the
 * scheduler, the worker and the relay; the backups, the WAL archive and the
 * restore drills from platform_backups; errors counted by kind; the console
 * page, GET /health for an uptime monitor, and the alerts to the platform team.
 */

beforeEach(function () {
    $this->admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    $this->admin->saveAppAuthenticationSecret('LMSHEALTHTESTCONSOLE2FASECRET234');
    PlatformAdmin::query()->create(['name' => 'قديم', 'email' => 'old@platform.test', 'password' => 'a-long-password', 'is_active' => false]);
    RecentErrors::forget();
    app()->setLocale('en');
});

function backupRow(string $kind, string $status, string $finished, ?string $detail = null): void
{
    DB::connection('pgsql_platform')->table('platform_backups')->insert([
        'kind' => $kind, 'status' => $status, 'started_at' => now()->parse($finished)->subMinute(), 'finished_at' => now()->parse($finished),
        'size_bytes' => 4_200_000, 'name' => 'x', 'detail' => $detail,
    ]);
}

it('says when each part of the stack last spoke, and how late the worker is', function () {
    $checks = app(SystemHealth::class)->checks();
    expect($checks['scheduler']['status'])->toBe('fail')->and($checks['scheduler']['value'])->toBe('never yet')
        ->and($checks['worker']['status'])->toBe('fail')
        ->and($checks['database']['status'])->toBe('ok')
        ->and($checks['cache']['status'])->toBe('ok')
        ->and($checks['relay']['status'])->toBe('off')    // no Reverb in tests
        ->and($checks['reverb']['status'])->toBe('off');

    Heartbeats::beat('scheduler');
    (new WorkerBeat(now()->getTimestamp() - 5))->handle();
    $checks = app(SystemHealth::class)->checks();
    expect($checks['scheduler']['status'])->toBe('ok')
        ->and($checks['worker']['status'])->toBe('ok')
        ->and(Heartbeats::last('worker')['lag'])->toBeGreaterThanOrEqual(5);

    $this->travel(5)->minutes();
    expect(app(SystemHealth::class)->checks()['scheduler']['status'])->toBe('warn');
    $this->travel(10)->minutes();
    expect(app(SystemHealth::class)->checks()['scheduler'])->toMatchArray(['status' => 'fail'])
        ->and(app(SystemHealth::class)->checks()['scheduler']['hint'])->toContain('docker compose logs scheduler');

    (new WorkerBeat(now()->getTimestamp() - 300))->handle();
    expect(app(SystemHealth::class)->checks()['worker'])->toMatchArray(['status' => 'warn', 'value' => 'running, but the last task waited 300 s']);
});

it('checks the backups, the WAL archive and the drills only where the backup part runs', function () {
    $health = fn () => app(SystemHealth::class)->checks();
    expect($health()['backups']['status'])->toBe('off')->and($health()['archive']['status'])->toBe('off')->and($health()['drill']['status'])->toBe('off');

    config(['platform.health.backups' => true]);
    expect($health()['backups']['status'])->toBe('fail')
        ->and($health()['archive']['status'])->toBe('warn')   // this test database archives nothing
        ->and($health()['drill']['status'])->toBe('warn');

    backupRow('base', 'ok', now()->subHours(3));
    backupRow('drill', 'ok', now()->subDays(10), 'restored to …: 2 academies');
    expect($health()['backups']['status'])->toBe('ok')->and($health()['drill']['status'])->toBe('ok');

    backupRow('dump', 'failed', now()->subHour(), 'pg_dump: error');
    expect($health()['backups']['status'])->toBe('warn');   // part of the last one failed
});

it('calls a backup two days old a fault, and a drill three months old a reminder', function () {
    config(['platform.health.backups' => true]);
    backupRow('base', 'ok', now()->subHours(60));
    backupRow('drill', 'ok', now()->subDays(120));
    $checks = app(SystemHealth::class)->checks();

    expect($checks['backups']['status'])->toBe('fail')->and($checks['backups']['hint'])->toContain('./restore.sh list')
        ->and($checks['drill']['status'])->toBe('warn')->and($checks['drill']['hint'])->toContain('./restore.sh check');
});

it('counts errors by kind, redacted, and warns when they pile up', function () {
    foreach (range(1, 6) as $i) {
        RecentErrors::record(new RuntimeException("Mail to salma@example.com failed ({$i})"));
    }
    RecentErrors::record(new LogicException('another kind'));

    $errors = RecentErrors::all();
    $first = collect($errors)->firstWhere('class', RuntimeException::class);
    expect($errors)->toHaveCount(2)
        ->and($first['class'])->toBe(RuntimeException::class)
        ->and($first['count'])->toBe(6)
        ->and($first['message'])->toBe('Mail to ***@example.com failed (6)')
        ->and($first['place'])->toStartWith('tests/Feature/Platform/SystemHealthTest.php:')
        ->and(RecentErrors::countLast(1))->toBe(7)
        ->and(app(SystemHealth::class)->checks()['errors'])->toMatchArray(['status' => 'warn', 'value' => '7 in the last hour, 7 in 24 hours']);
});

it('lists the latest errors from the server\'s command line, redacted', function () {
    Artisan::call('platform:health', ['--errors' => true]);
    expect(Artisan::output())->toContain('no errors reported this week');

    RecentErrors::record(new RuntimeException('Mail to salma@example.com failed'));
    Artisan::call('platform:health', ['--errors' => true]);
    expect(Artisan::output())->toContain('RuntimeException: Mail to ***@example.com failed')
        ->toContain('tests/Feature/Platform/SystemHealthTest.php:')
        ->not->toContain('salma@');
});

it('shows the platform team every check, the backups and the latest errors', function () {
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');
    config(['platform.health.backups' => true]);
    backupRow('base', 'ok', now()->subHours(2));
    backupRow('drill', 'failed', now()->subHour(), 'the restore did not come up');
    RecentErrors::record(new RuntimeException('Gateway timed out'));

    Livewire::test(Health::class)
        ->assertSee('System health')->assertSee('Scheduled tasks')->assertSee('Last backup')->assertSee('Change archive (WAL)')
        ->assertSee('Full backup')->assertSee('Restore drill')->assertSee('the restore did not come up')
        ->assertSee('RuntimeException')->assertSee('Gateway timed out')
        ->call('check')->assertOk();

    $this->get('/console/health')->assertOk();
});

it('answers an uptime monitor with statuses only, and only with the token', function () {
    $this->get('/health')->assertNotFound();   // no token configured: no such page
    config(['platform.health.token' => 'monitor-token']);
    $this->get('/health')->assertNotFound();
    $this->get('/health?token=wrong')->assertNotFound();

    $this->mock(SystemHealth::class, fn ($m) => $m->shouldReceive('checks')->andReturn([
        'database' => ['status' => 'ok', 'value' => 'answers, 25 MB (3 ms)', 'hint' => null],
        'backups' => ['status' => 'warn', 'value' => '2 days ago', 'hint' => 'look at the backups'],
    ]));
    $this->withToken('monitor-token')->getJson('/health')->assertOk()
        ->assertJson(['status' => 'warn', 'checks' => ['database' => 'ok', 'backups' => 'warn']])
        ->assertJsonMissingPath('checks.backups.value')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->mock(SystemHealth::class, fn ($m) => $m->shouldReceive('checks')->andReturn([
        'scheduler' => ['status' => 'fail', 'value' => 'never yet', 'hint' => 'down'],
    ]));
    $this->getJson('/health?token=monitor-token')->assertStatus(503)->assertJson(['status' => 'fail']);
});

it('tells the platform team when a check starts failing, reminds them, and says when it is well again', function () {
    Mail::fake();
    $state = ['scheduler' => ['status' => 'fail', 'value' => 'never yet', 'hint' => 'docker compose logs scheduler'], 'database' => ['status' => 'ok', 'value' => 'answers', 'hint' => null]];
    $this->mock(SystemHealth::class, function ($m) use (&$state) {
        $m->shouldReceive('checks')->andReturnUsing(function () use (&$state) {
            return $state;
        });
    });
    config(['platform.health.alert_to' => ['ops@example.com']]);

    $this->artisan('platform:health --alert')->assertExitCode(1);
    Mail::assertNothingSent();   // once could be a restart: wait for the next check

    $this->artisan('platform:health --alert')->assertExitCode(1);
    Mail::assertSent(HealthAlert::class, fn (HealthAlert $m) => $m->hasTo('salma@platform.test') && $m->hasTo('ops@example.com') && ! $m->hasTo('old@platform.test')
        && array_keys($m->failing) === ['scheduler'] && ! $m->reminder);
    expect(Mail::sent(HealthAlert::class))->toHaveCount(1);

    $this->artisan('platform:health --alert');   // still failing: nothing new to say
    expect(Mail::sent(HealthAlert::class))->toHaveCount(1);

    $this->travel(7)->hours();
    $this->artisan('platform:health --alert');   // a reminder after six hours
    expect(Mail::sent(HealthAlert::class))->toHaveCount(2)->and(Mail::sent(HealthAlert::class)->last()->reminder)->toBeTrue();

    $state['database'] = ['status' => 'fail', 'value' => 'does not answer', 'hint' => null];   // a blip that passes
    $this->artisan('platform:health --alert');
    $state['database'] = ['status' => 'ok', 'value' => 'answers', 'hint' => null];
    expect(Mail::sent(HealthAlert::class))->toHaveCount(2);

    $state['scheduler'] = ['status' => 'ok', 'value' => 'a minute ago', 'hint' => null];
    $this->artisan('platform:health --alert')->assertExitCode(0);
    $well = Mail::sent(HealthAlert::class)->last();
    expect(Mail::sent(HealthAlert::class))->toHaveCount(3)->and($well->failing)->toBe([])->and($well->recovered)->toBe(['scheduler']);
    expect($well->render())->toContain('رجع يشتغل')->toContain('working again')->toContain('/console/health');
    Cache::flush();
});

it('beats each minute and checks for alerts every five', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($e) => $e->description === 'health:scheduler'))->toBeTrue()
        ->and($events->contains(fn ($e) => $e->description === 'health:worker'))->toBeTrue()
        ->and($events->contains(fn ($e) => str_contains((string) $e->command, 'platform:health --alert') && $e->expression === '*/5 * * * *'))->toBeTrue();
});

it('keeps backups in a table only the platform reads, and the app never changes', function () {
    backupRow('base', 'ok', now());
    $this->actAsTenant($this->createAcademy());

    expect(fn () => DB::table('platform_backups')->count())->toThrow(Exception::class)
        ->and(fn () => DB::connection('pgsql_platform')->table('platform_backups')->update(['status' => 'failed']))->toThrow(Exception::class)
        ->and(fn () => DB::connection('pgsql_platform')->table('platform_backups')->delete())->toThrow(Exception::class)
        ->and(DB::connection('pgsql_platform')->table('platform_backups')->count())->toBe(1);
});
