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
use Modules\Platform\Health\TestMail;
use Modules\Platform\Health\WorkerBeat;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Health\Heartbeats;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Health\RecentErrors;

/*
 * The stack's health (docs/architecture/03, section 12): heartbeats from the
 * scheduler, the worker and the relay; the backups, the WAL archive, their
 * off-site copy and the restore drills from platform_backups; whether mail
 * goes out; errors counted by kind; the console page, GET /health for an
 * uptime monitor, and the alerts to the platform team.
 */

beforeEach(function () {
    $this->admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    $this->admin->saveAppAuthenticationSecret('LMSHEALTHTESTCONSOLE2FASECRET234');
    PlatformAdmin::query()->create(['name' => 'قديم', 'email' => 'old@platform.test', 'password' => 'a-long-password', 'is_active' => false]);
    RecentErrors::forget();
    Cache::forget('health:mail');
    app()->setLocale('en');
});

function backupRow(string $kind, string $status, string $finished, ?string $detail = null, string $name = 'x'): void
{
    DB::connection('pgsql_platform')->table('platform_backups')->insert([
        'kind' => $kind, 'status' => $status, 'started_at' => now()->parse($finished)->subMinute(), 'finished_at' => now()->parse($finished),
        'size_bytes' => 4_200_000, 'name' => $name, 'detail' => $detail,
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

it('says whether the backups also reach the off-site copy, and since when they do not', function () {
    $offsite = fn () => app(SystemHealth::class)->checks()['offsite'];
    expect($offsite()['status'])->toBe('off');   // no backup part here

    config(['platform.health.backups' => true]);
    expect($offsite())->toMatchArray(['status' => 'warn', 'value' => 'not set up: the backups are on this server only'])
        ->and($offsite()['hint'])->toContain('OFFSITE_AGE_RECIPIENT');

    config(['platform.health.offsite' => true]);
    expect($offsite())->toMatchArray(['status' => 'warn', 'value' => 'never yet']);

    backupRow('offsite', 'ok', now()->subMinutes(20), '12 segment(s) sent in the last hour', 'wal');
    backupRow('offsite', 'ok', now()->subHours(5), 'its WAL from 000000010000000000000003', 'base/20261009T053000Z');
    expect($offsite()['status'])->toBe('ok')->and($offsite()['value'])->toBe('changes sent 20 minutes ago, the last full backup sent 5 hours ago');

    // The bucket stops answering: a warning at first, a fault after an hour.
    backupRow('offsite', 'failed', now()->subMinutes(10), 'the bucket does not answer: dial tcp: no such host', 'wal');
    expect($offsite()['status'])->toBe('warn')->and($offsite()['value'])->toContain('not sending since 10 minutes ago: the bucket does not answer');
    $this->travel(65)->minutes();
    backupRow('offsite', 'failed', now(), 'the bucket does not answer', 'wal');   // still not, an hour on
    expect($offsite()['status'])->toBe('fail')->and($offsite()['value'])->toContain('not sending since 1 hour ago')
        ->and($offsite()['hint'])->toContain('docker compose logs offsite');

    // Sending again: well, and a failed piece is not the backup part's fault.
    backupRow('offsite', 'ok', now(), '3 segment(s) sent in the last hour', 'wal');
    backupRow('offsite', 'failed', now(), 'dumps/lms-x.dump: timeout', 'dumps/lms-20261009T053000Z.dump');
    backupRow('base', 'ok', now()->subHours(3));
    expect($offsite()['status'])->toBe('warn')   // the piece that failed
        ->and(app(SystemHealth::class)->checks()['backups']['status'])->toBe('ok');

    // The hourly "the WAL is going" lines stay out of the list of runs; failures and pieces stay in.
    $runs = collect(app(SystemHealth::class)->backupRuns(20));
    expect($runs->where('kind', 'offsite')->where('name', 'wal')->pluck('status')->unique()->values()->all())->toBe(['failed'])
        ->and($runs->contains(fn ($r) => $r->name === 'base/20261009T053000Z'))->toBeTrue();
});

it('calls an off-site copy that stopped a day ago a fault', function () {
    config(['platform.health.backups' => true, 'platform.health.offsite' => true]);
    backupRow('offsite', 'ok', now()->subHours(4), null, 'wal');
    backupRow('offsite', 'ok', now()->subHours(20), null, 'base/20261008T053000Z');
    expect(app(SystemHealth::class)->checks()['offsite']['status'])->toBe('warn');   // the WAL is late

    $this->travel(40)->hours();
    backupRow('offsite', 'ok', now()->subMinutes(5), null, 'wal');
    expect(app(SystemHealth::class)->checks()['offsite']['status'])->toBe('fail');   // no full backup in two days
});

it('says whether mail goes out, and why not', function () {
    $mail = fn () => app(SystemHealth::class)->checks()['mail'];
    expect($mail()['status'])->toBe('off');   // tests keep mail in an array

    app()->detectEnvironment(fn () => 'production');
    config(['mail.default' => 'log']);
    expect($mail())->toMatchArray(['status' => 'warn', 'value' => 'messages go to the log only, nobody receives them'])
        ->and($mail()['hint'])->toContain('MAIL_MAILER=smtp');
    app()->detectEnvironment(fn () => 'testing');

    config(['mail.default' => 'smtp']);
    expect($mail())->toMatchArray(['status' => 'ok', 'value' => 'set up (smtp), no message sent yet'])->and($mail()['hint'])->toContain('test');

    MailHealth::failed(new RuntimeException('Expected response code "250" but got code "550", with message "550 <salma@example.com> rejected".'));
    expect($mail()['status'])->toBe('fail')->and($mail()['value'])->toContain('the last message did not go out')
        ->toContain('***@example.com')->not->toContain('salma@');

    $this->travel(1)->minutes();
    MailHealth::sent();
    expect($mail()['status'])->toBe('ok')->and($mail()['value'])->toStartWith('the last message went out');

    PlatformAdmin::query()->update(['is_active' => false]);
    expect($mail()['status'])->toBe('warn')->and($mail()['value'])->toBe('nobody to send the alerts to');
});

it('notes every message that leaves, and sends the platform team a test', function () {
    Mail::to('someone@example.com')->send(new TestMail);   // the array mailer: Laravel says it was sent
    expect(MailHealth::last())->toHaveKey('sent')->and(MailHealth::failing())->toBeFalse();

    Mail::fake();
    config(['platform.health.alert_to' => ['ops@example.com']]);
    $this->artisan('platform:mail-test')->expectsOutputToContain('Sent through array to 2 address(es)')->assertExitCode(0);
    Mail::assertSent(TestMail::class, fn (TestMail $m) => $m->hasTo('salma@platform.test') && $m->hasTo('ops@example.com'));
    $this->artisan('platform:mail-test', ['to' => ['me@example.com']])->assertExitCode(0);
    Mail::assertSent(TestMail::class, fn (TestMail $m) => $m->hasTo('me@example.com') && ! $m->hasTo('salma@platform.test'));
    expect((new TestMail)->render())->toContain('رسالة تجربة')->toContain('A test message from the server');
});

it('says so when mail does not go out, and tries the alert again at the next check', function () {
    // An SMTP server that is not there: the message cannot leave.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
    $this->artisan('platform:mail-test')->expectsOutputToContain('Not sent:')->assertExitCode(1);
    expect(MailHealth::failing())->toBeTrue()->and(app(SystemHealth::class)->checks()['mail']['status'])->toBe('fail');

    $this->mock(SystemHealth::class, fn ($m) => $m->shouldReceive('checks')->andReturn([
        'scheduler' => ['status' => 'fail', 'value' => 'never yet', 'hint' => null],
    ]));
    $this->artisan('platform:health --alert');
    $this->artisan('platform:health --alert')->expectsOutputToContain('alert not sent: mail fails');
    expect(Cache::get('health:alerted'))->toBeNull();   // not marked as told: the next check tries again
    Cache::flush();
});

it('sends a test email to whoever is signed in to the console', function () {
    Mail::fake();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');

    Livewire::test(Health::class)->assertSee('Send a test email')->assertSee('Off-site copy')->assertSee('Email')
        ->callAction('testMail')->assertNotified('The message went out to salma@platform.test. Check the inbox (and the spam folder).');
    Mail::assertSent(TestMail::class, fn (TestMail $m) => $m->hasTo('salma@platform.test'));
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
    backupRow('offsite', 'ok', now()->subMinutes(30), 'its WAL from 000000010000000000000003', 'base/20261009T053000Z');
    RecentErrors::record(new RuntimeException('Gateway timed out'));

    Livewire::test(Health::class)
        ->assertSee('System health')->assertSee('Scheduled tasks')->assertSee('Last backup')->assertSee('Change archive (WAL)')
        ->assertSee('Full backup')->assertSee('Restore drill')->assertSee('the restore did not come up')
        ->assertSee('Sent off-site')->assertSee('base/20261009T053000Z')
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
