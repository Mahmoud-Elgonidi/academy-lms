<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Modules\Platform\Database\Seeders\DemoPlatformSeeder;
use Modules\Platform\Models\PlatformAdmin;

// What a server (infra/deploy) sets that a laptop does not.

afterEach(fn () => TrustProxies::flushState());

function probeScheme(string $from): string
{
    Route::get('/_scheme', fn (Request $request) => $request->getScheme().' '.url('/x'));

    return test()->withServerVariables(['REMOTE_ADDR' => $from])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443', 'X-Forwarded-Host' => 'demo.progma.store'])
        ->get('http://localhost/_scheme')->assertOk()->getContent();
}

it('keeps links on https behind the proxies the server names, and only those', function () {
    config(['app.trusted_proxies' => '127.0.0.1, 172.16.0.0/12']);
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(probeScheme('172.18.0.1'))->toBe('https https://demo.progma.store/x')
        ->and(probeScheme('203.0.113.9'))->toBe('http http://localhost/x');
});

it('trusts no proxy when the server names none', function () {
    config(['app.trusted_proxies' => null]);
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(probeScheme('172.18.0.1'))->toBe('http http://localhost/x');
});

it('gives the demo platform team the server\'s own password and authenticator secret', function () {
    config(['app.demo' => ['password' => 'kV8-not-password', 'platform_2fa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP']]);

    $this->seed(DemoPlatformSeeder::class);

    $admin = PlatformAdmin::query()->where('email', 'admin@platform.test')->sole();
    expect(Hash::check('kV8-not-password', $admin->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse()
        ->and($admin->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');
});

it('gives every table fresh statistics after the migrations, small ones too', function () {
    $this->artisan('db:analyze')->assertSuccessful();

    // A table never analysed reads -1; ANALYZE writes what it found, an empty table's 0 included.
    $never = DB::connection('pgsql_owner')->table('pg_class')
        ->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_class.relnamespace')
        ->where('pg_namespace.nspname', 'public')->where('pg_class.relkind', 'r')->where('pg_class.reltuples', '<', 0)
        ->pluck('relname');

    expect($never->all())->toBe([])
        ->and((string) file_get_contents(dirname(__DIR__, 5).'/infra/deploy/entrypoint.sh'))
        ->toContain('migrate --database=pgsql_owner --force && exec php artisan db:analyze');
});

it('lets go of a worker when the mail server stops answering', function () {
    expect(config('mail.mailers.smtp.timeout'))->toBe(15);
});
