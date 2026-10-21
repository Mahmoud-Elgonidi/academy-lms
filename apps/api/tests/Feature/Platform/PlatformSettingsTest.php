<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Communication\Channels\Push\FcmPushGateway;
use Modules\Platform\Actions\SavePlatformSettings;
use Modules\Platform\Filament\Console\Pages\PlatformSettingsPage;
use Modules\Platform\Health\TestMail;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Settings\LastChecks;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Settings\SettingCatalog;
use Modules\Platform\Settings\SettingChecks;
use Modules\Platform\Settings\SettingDefinition;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/*
 * Platform settings (the console, owners only): the outside services' keys
 * and addresses set from a screen instead of the server's .env. Kept
 * encrypted where only the platform's connection reads them, put into the
 * running config at each request, job and command (never written out by
 * config:cache), winning over .env while set; a key is never shown again
 * nor written to the audit log, and each section can be tried.
 */

beforeEach(function () {
    // The screen asks the providers for their models: never the real ones from a test.
    Http::preventStrayRequests();
    $this->owner = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@company.example', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    $this->owner->forceFill(['is_owner' => true])->save();
    $this->owner->saveAppAuthenticationSecret('LMSSETTINGSTESTCONSOLE2FASECRET2');
    $this->member = PlatformAdmin::query()->create(['name' => 'نادر', 'email' => 'nader@company.example', 'password' => 'a-long-password']);
    $this->member->saveAppAuthenticationSecret('LMSSETTINGSTESTCONSOLE2FASECRET3');
    app()->setLocale('en');
});

afterEach(function () {
    Cache::forget(PlatformSettings::CACHE_KEY);
});

/** A value written straight to the table, as another process's save would leave it. */
function storeSetting(string $key, string $value): void
{
    DB::connection('pgsql_platform')->table('platform_settings')->upsert(['key' => $key, 'value' => Crypt::encryptString($value), 'updated_at' => now()], ['key']);
    Cache::forget(PlatformSettings::CACHE_KEY);
}

function serviceAccount(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    return ['type' => 'service_account', 'project_id' => 'academy-app-1', 'private_key_id' => 'k1', 'private_key' => $pem, 'client_email' => 'push@academy-app-1.iam.gserviceaccount.com'];
}

it('keeps every value encrypted where the academies cannot read it, and the audit names settings only', function () {
    $changed = app(SavePlatformSettings::class)->handle($this->owner, ['anthropic_key' => 'sk-ant-a-test-key-of-the-platform', 'mail_host' => 'smtp.mailer.example']);

    expect($changed)->toEqualCanonicalizing(['anthropic_key', 'mail_host']);
    $raw = DB::connection('pgsql_platform')->table('platform_settings')->pluck('value', 'key');
    expect($raw['anthropic_key'])->not->toContain('sk-ant')
        ->and(Crypt::decryptString($raw['anthropic_key']))->toBe('sk-ant-a-test-key-of-the-platform')
        ->and(DB::connection('pgsql_platform')->table('platform_settings')->value('updated_by'))->toBe($this->owner->id);
    expect(fn () => DB::connection('pgsql')->table('platform_settings')->count())->toThrow(QueryException::class);

    $audit = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.settings_saved')->sole();
    expect(json_decode($audit->data, true))->toEqual(['sections' => ['ai', 'mail'], 'keys' => ['anthropic_key', 'mail_host']])
        ->and($audit->data)->not->toContain('sk-ant')->not->toContain('smtp.mailer');

    // Saving the same again changes nothing and records nothing.
    expect(app(SavePlatformSettings::class)->handle($this->owner, ['anthropic_key' => '', 'mail_host' => 'smtp.mailer.example']))->toBe([])
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.settings_saved')->count())->toBe(1);
});

it('wins over the server .env while set: in a request, a job and a command, and goes back when cleared', function () {
    config(['platform.ai.key' => 'from-the-env', 'platform.games.designer.key' => 'from-the-env', 'mail.mailers.smtp.port' => 2525, 'platform.health.alert_to' => []]);
    storeSetting('anthropic_key', 'from-the-screen');
    storeSetting('mail_port', '587');
    storeSetting('health_alert_to', 'ops@company.example, cto@company.example');

    $this->get('/up')->assertOk();
    expect(config('platform.ai.key'))->toBe('from-the-screen')
        ->and(config('platform.games.designer.key'))->toBe('from-the-screen')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('platform.health.alert_to'))->toBe(['ops@company.example', 'cto@company.example']);

    // Another process cleared it: the next job here puts the server's own back.
    DB::connection('pgsql_platform')->table('platform_settings')->where('key', 'anthropic_key')->delete();
    Cache::forget(PlatformSettings::CACHE_KEY);
    dispatch(fn () => null);
    expect(config('platform.ai.key'))->toBe('from-the-env')
        ->and(config('mail.mailers.smtp.port'))->toBe(587);

    // A command takes them as it starts (Laravel raises CommandStarting outside tests); one that writes the config out never does.
    storeSetting('ai_daily_cap', '42');
    event(new CommandStarting('config:cache', new ArrayInput([]), new NullOutput));
    expect(config('platform.ai.daily_cap'))->not->toBe(42);
    event(new CommandStarting('platform:health', new ArrayInput([]), new NullOutput));
    expect(config('platform.ai.daily_cap'))->toBe(42);
});

it('never writes a value into the cached config', function () {
    app(SavePlatformSettings::class)->handle($this->owner, ['anthropic_key' => 'sk-ant-never-in-the-cache-file', 'stripe_platform_secret' => 'sk_test_never_in_the_cache']);
    expect(config('platform.ai.key'))->toBe('sk-ant-never-in-the-cache-file');   // in force in this process

    $path = sys_get_temp_dir().'/lms-config-'.bin2hex(random_bytes(4)).'.php';
    $default = base_path('bootstrap/cache/config.php');
    $hadDefault = is_file($default);
    $_SERVER['APP_CONFIG_CACHE'] = $_ENV['APP_CONFIG_CACHE'] = $path;
    putenv("APP_CONFIG_CACHE={$path}");
    try {
        $this->artisan('config:cache')->assertSuccessful();
        expect(is_file($path))->toBeTrue();
        $cached = (string) file_get_contents($path);
        expect($cached)->not->toContain('sk-ant-never-in-the-cache-file')->not->toContain('sk_test_never_in_the_cache');
    } finally {
        @unlink($path);
        if (! $hadDefault) {
            @unlink($default);
        }
        unset($_SERVER['APP_CONFIG_CACHE'], $_ENV['APP_CONFIG_CACHE']);
        putenv('APP_CONFIG_CACHE');
    }
});

it('keeps a key when its field is left empty, removes it when cleared, and a plain value when emptied', function () {
    $save = app(SavePlatformSettings::class);
    $save->handle($this->owner, ['twilio_token' => 'twilio-token-one', 'twilio_from' => '+15005550006']);

    $save->handle($this->owner, ['twilio_token' => '', 'twilio_from' => '+15005550006']);
    expect(app(PlatformSettings::class)->values())->toMatchArray(['twilio_token' => 'twilio-token-one']);

    $save->handle($this->owner, ['twilio_from' => ''], clear: ['twilio_token']);
    expect(app(PlatformSettings::class)->values())->not->toHaveKey('twilio_token')->not->toHaveKey('twilio_from');
});

it('checks each value by its setting and the Firebase file as a service account', function (array $values, string $field) {
    expect(fn () => app(SavePlatformSettings::class)->handle($this->owner, $values))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($field));
    expect(DB::connection('pgsql_platform')->table('platform_settings')->count())->toBe(0);
})->with([
    'a port that is not a number' => [['mail_port' => 'abc'], 'mail_port'],
    'a choice not offered' => [['mail_mailer' => 'sendmail'], 'mail_mailer'],
    'an address that is not an email' => [['mail_from_address' => 'not-an-email'], 'mail_from_address'],
    'one bad email in a list' => [['health_alert_to' => 'ops@company.example, nope'], 'health_alert_to.1'],
    'a model name with spaces' => [['ai_model' => 'claude opus'], 'ai_model'],
    'a Stripe key that is not one' => [['stripe_platform_secret' => 'pk_test_public'], 'stripe_platform_secret'],
    'a short LiveKit secret' => [['livekit_secret' => 'short'], 'livekit_secret'],
    'a JSON that is no service account' => [['fcm_credentials' => '{"type":"authorized_user"}'], 'fcm_credentials'],
    'not JSON at all' => [['fcm_credentials' => 'hello'], 'fcm_credentials'],
]);

it('takes the Firebase file in place of a file on the server, and pushes with it', function () {
    $account = serviceAccount();
    app(SavePlatformSettings::class)->handle($this->owner, ['push_driver' => 'fcm', 'fcm_credentials' => json_encode($account)]);

    expect(app(PlatformSettings::class)->values()['fcm_project_id'])->toBe('academy-app-1')   // from the file
        ->and(config('communication.push.fcm.credentials_json'))->toBe(json_encode($account))
        ->and(config('communication.push.fcm.project_id'))->toBe('academy-app-1');

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/academy-app-1/messages/1']),
    ]);
    $result = (new FcmPushGateway(config('communication.push.fcm')))->send(['device-token'], 'Title', 'Body', []);
    expect($result->messageIds)->toBe(['projects/academy-app-1/messages/1']);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'projects/academy-app-1/messages:send') && $r->hasHeader('Authorization', 'Bearer ya29.token'));
});

it('lets the next mail go through the new server', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'old.mailer.example', 'mail.mailers.smtp.port' => 2525]);
    expect(app('mail.manager')->mailer('smtp')->getSymfonyTransport()->getStream()->getHost())->toBe('old.mailer.example');

    app(SavePlatformSettings::class)->handle($this->owner, ['mail_host' => 'smtp.new-mailer.example']);

    expect(app('mail.manager')->mailer('smtp')->getSymfonyTransport()->getStream()->getHost())->toBe('smtp.new-mailer.example');
});

it('is for owners alone', function () {
    Filament::setCurrentPanel('console');

    $this->actingAs($this->member, 'platform')->get('/console/settings')->assertForbidden();
    expect(fn () => app(SavePlatformSettings::class)->handle($this->member, ['mail_host' => 'x.example']))->toThrow(AuthorizationException::class);

    $this->flushSession();
    $this->actingAs($this->owner, 'platform')->get('/console/settings')->assertOk()->assertSee('إعدادات المنصة')->assertSee('مفتاح Anthropic');
});

it('saves from the screen, shows where each value comes from, and never shows a key again', function () {
    config(['mail.mailers.smtp.host' => 'env.mailer.example', 'platform.ai.key' => null, 'platform.games.designer.key' => null]);
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    Livewire::test(PlatformSettingsPage::class)
        ->assertSee('From the server')->assertSee('Not set')->assertSee('Server: env.mailer.example')
        ->fillForm(['anthropic_key' => 'sk-ant-typed-on-the-screen', 'mail_port' => '587', 'mail_mailer' => 'smtp'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('3 settings saved; they are in use from the next request.')
        ->assertDontSee('sk-ant-typed-on-the-screen')
        ->assertSee('From this screen')->assertSee('Saved by', false)
        ->assertFormSet(['anthropic_key' => null, 'mail_port' => '587'])
        ->fillForm(['mail_port' => 'abc'])->call('save')->assertHasFormErrors(['mail_port']);

    expect(app(PlatformSettings::class)->values())->toMatchArray(['anthropic_key' => 'sk-ant-typed-on-the-screen', 'mail_port' => '587', 'mail_mailer' => 'smtp'])
        ->and(app(PlatformSettings::class)->saved()['anthropic_key']['by'])->toBe($this->owner->id);

    Livewire::test(PlatformSettingsPage::class)
        ->assertSee('Remove the saved Anthropic API key')
        ->fillForm(['clear' => ['anthropic_key' => true]])->call('save')->assertNotified('One setting saved; it is in use from the next request.');
    expect(app(PlatformSettings::class)->values())->not->toHaveKey('anthropic_key');
});

it('tries each service with the keys in force, in its own words', function () {
    Mail::fake();
    app(SavePlatformSettings::class)->handle($this->owner, [
        'anthropic_key' => 'sk-ant-try', 'ai_model' => 'claude-opus-5-5', 'ai_fast_model' => 'claude-haiku-5-5', 'elevenlabs_key' => 'eleven-try',
        'stripe_platform_secret' => 'sk_test_try_platform', 'twilio_sid' => 'AC'.str_repeat('a', 32), 'twilio_token' => 'twilio-try',
        'whatsapp_token' => 'meta-try', 'whatsapp_phone_number_id' => '1234567890',
        'livekit_key' => 'APIkey', 'livekit_secret' => str_repeat('s', 40), 'livekit_api_url' => 'https://livekit.company.example',
        'mail_mailer' => 'smtp',
    ]);
    config(['platform.ai.driver' => 'claude', 'platform.games.designer.driver' => 'claude', 'platform.games.designer.model' => null]);
    Http::fake([
        'api.anthropic.com/v1/messages' => fn (Request $r) => $r['model'] === 'claude-opus-5-5'
            ? Http::response(['content' => [['type' => 'text', 'text' => '{"ok":true}']], 'usage' => ['input_tokens' => 9, 'output_tokens' => 5]])
            : Http::response(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'model: '.$r['model']]], 404),
        'api.elevenlabs.io/v1/user/subscription' => Http::response(['detail' => ['status' => 'missing_permissions']], 401),
        'api.stripe.com/v1/account' => Http::response(['error' => ['message' => 'Invalid API Key provided: sk_test_****form']], 401),
        'api.twilio.com/*/Balance.json' => Http::response(['balance' => '42.10', 'currency' => 'USD']),
        'api.twilio.com/*' => Http::response(['status' => 'active', 'type' => 'Full']),
        'graph.facebook.com/*' => Http::response(['display_phone_number' => '+20 100 000 0000', 'quality_rating' => 'GREEN']),
        'livekit.company.example/*' => Http::response(['rooms' => []]),
    ]);
    $checks = app(SettingChecks::class);

    // The AI cards are tried together, each with its own lines.
    $parts = $checks->runParts('ai', 'salma@company.example');
    $ai = $parts['ai'];
    expect(array_keys($parts))->toBe(['ai', 'designer', 'speech'])
        ->and($ai->level())->toBe('fail')
        ->and($ai->says())->toContain('Claude (Anthropic) answered with claude-opus-5-5.')
        ->toContain('Claude (Anthropic) does not offer claude-haiku-5-5: choose another model. (not_found_error)')
        ->not->toContain('ElevenLabs')
        ->and($parts['speech']->says())->toBe('ElevenLabs took the key; it may not read the plan, so what is left is not shown.')
        ->and($parts['designer']->says())->toBe('No model is chosen for the games designer yet: the Game studio designs nothing until one is.')
        ->and(ServiceHealth::problem('anthropic'))->toBe(ServiceProblem::Model)
        ->and(ServiceHealth::last('anthropic')['scope'])->toBe('claude-haiku-5-5');
    config(['platform.ai.models.fast' => 'claude-opus-5-5']);
    expect($checks->run('ai', 'salma@company.example')->ok())->toBeTrue()
        ->and(ServiceHealth::failing('anthropic'))->toBeFalse()
        ->and(ServiceHealth::last('anthropic')['checked'])->toBeInt();

    $stripe = $checks->run('stripe', 'salma@company.example');
    expect($stripe->level())->toBe('fail')
        ->and($stripe->lines[0]->text())->toBe('Stripe refused the key: it is wrong, revoked or expired. (HTTP 401)')
        ->and($stripe->lines[0]->todoText())->toBe('Make a new key at Stripe, put it in Platform settings, then try it.')
        ->and($stripe->lines[0]->html())->toBe('<bdi>Stripe</bdi> refused the key: it is wrong, revoked or expired. <bdi dir="ltr">(HTTP 401)</bdi>')
        ->and($stripe->says())->not->toContain('sk_test')->not->toContain('Invalid API Key');
    expect($checks->run('sms', 'salma@company.example')->says())->toBe('Twilio took the account; 42.10 USD left.')
        ->and($checks->run('whatsapp', 'salma@company.example')->says())->toBe('WhatsApp (Meta) took the token; the number is +20 100 000 0000.')
        ->and($checks->run('classroom', 'salma@company.example')->ok())->toBeTrue()
        ->and($checks->run('push', 'salma@company.example')->says())->toBe('Firebase (push) is not set up yet: its key or address is missing.')
        ->and($checks->run('mail', 'salma@company.example')->says())->toBe('A test email went to salma@company.example: check that it arrived.');
    Mail::assertSent(TestMail::class);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.anthropic.com/v1/messages' && $r->hasHeader('x-api-key', 'sk-ant-try') && $r['max_tokens'] === 256
        && $r['output_config'] === ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false]]]);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'twirp/livekit.RoomService/ListRooms') && str_starts_with($r->header('Authorization')[0], 'Bearer ey'));

    // From the screen: a notification per section, never a key in it, and the result kept under the section.
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');
    Livewire::test(PlatformSettingsPage::class)->call('check', 'stripe')
        ->assertNotified('Stripe Connect')
        ->assertSee('Stripe refused the key: it is wrong, revoked or expired.')
        ->assertSee('just now by سلمى')
        ->assertDontSee('sk_test_try_platform');
    expect(LastChecks::get('stripe')['result']->level())->toBe('fail');
    Livewire::test(PlatformSettingsPage::class)->call('check', 'social')->assertForbidden();
});

it('lists every setting with its wording in both languages', function () {
    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);
        foreach (SettingCatalog::all() as $key => $setting) {
            expect(trans()->has("platform::settings.keys.{$key}.label"))->toBeTrue("{$locale}: {$key}")
                ->and(trans()->has("platform::settings.groups.{$setting->group}.title"))->toBeTrue();
            foreach ($setting->options as $option) {
                expect(trans()->has("platform::settings.options.{$key}.{$option}"))->toBeTrue("{$locale}: {$key}.{$option}");
            }
        }
    }
});

it('shows every setting in one of the tabs\' cards', function () {
    $page = new ReflectionClass(PlatformSettingsPage::class);
    $cards = $page->getConstant('CARD_SETTINGS');
    $shown = collect($page->getConstant('TABS'))->flatMap(fn (array $tab) => $tab['cards'])
        ->flatMap(fn (string $card) => $cards[$card] ?? array_map(fn (SettingDefinition $s) => $s->key, SettingCatalog::inGroup($card)))
        ->unique()->sort()->values()->all();

    expect($shown)->toBe(collect(array_keys(SettingCatalog::all()))->sort()->values()->all());
});
