<?php

use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Modules\Platform\Actions\SavePlatformSettings;
use Modules\Platform\Filament\Console\Pages\PlatformSettingsPage;
use Modules\Platform\Health\OutsideServices;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Settings\LastChecks;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Settings\SettingChecks;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;

/*
 * "Try it" says why (docs/architecture/03, section 12): each section's try
 * tells in our words whether the service took the keys and, when not, what
 * went wrong (a key refused, the credit or plan run out, an account stopped,
 * a model not there) and what to do, from the provider's code alone; it warns
 * while a plan is nearly used. It tries what is typed before it is saved,
 * without marking the saved settings broken, keeps its result under the
 * section's title, and the services that need someone head the page.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    app()->setLocale('en');
    foreach (array_keys(OutsideServices::GROUPS) as $service) {
        ServiceHealth::forget($service);
    }
    $this->owner = PlatformAdmin::query()->create(['name' => 'Salma', 'email' => 'salma@company.example', 'password' => 'a-long-password']);
    $this->owner->forceFill(['is_owner' => true])->save();
    $this->owner->saveAppAuthenticationSecret('LMSCHECKSTESTCONSOLE2FASECRET234');
    config([
        'platform.ai.driver' => 'off', 'platform.ai.key' => null, 'platform.ai.speech.key' => null,
        'platform.games.designer.key' => null, 'platform.games.designer.model' => null,
    ]);
});

afterEach(function () {
    cache()->forget(PlatformSettings::CACHE_KEY);
});

function checksAnswering(array $answers): void
{
    Http::fake(function (Request $request) use ($answers) {
        foreach ($answers as $pattern => $answer) {
            if (str_contains($request->url(), $pattern)) {
                return $answer instanceof Closure ? $answer($request) : $answer;
            }
        }

        return Http::response([], 500);
    });
}

function checksServiceAccount(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    return (string) json_encode(['type' => 'service_account', 'project_id' => 'academy-app-1', 'private_key' => $pem, 'client_email' => 'push@academy-app-1.iam.gserviceaccount.com']);
}

it('says when the credit ran out, a key is refused or a model is not there, for each provider, and writes it down', function (string $driver, array $config, string $pattern, array $body, int $status, ServiceProblem $problem, string $says) {
    config(['platform.ai.driver' => $driver] + $config);
    checksAnswering([$pattern => Http::response($body, $status)]);

    $result = app(SettingChecks::class)->run('ai');

    expect($result->level())->toBe('fail')
        ->and($result->lines[0]->text())->toContain($says)
        ->and($result->lines[0]->todoText())->toBe(__('platform::services.todo.'.$problem->value, ['service' => __('platform::services.names.'.checksService($driver))]))
        ->and(ServiceHealth::problem(checksService($driver)))->toBe($problem)
        ->and(ServiceHealth::last(checksService($driver))['via'])->toBe('check');
})->with([
    'Claude out of credit' => ['claude', ['platform.ai.key' => 'sk-ant-1', 'platform.ai.models.best' => 'claude-opus-5-5'], 'api.anthropic.com/v1/messages',
        ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low to access the Anthropic API.']], 400,
        ServiceProblem::Quota, 'Claude (Anthropic) says the credit, quota or subscription ran out. (credit_balance)'],
    'GPT out of quota' => ['openai', ['platform.ai.openai.key' => 'sk-open-1', 'platform.ai.openai.models.best' => 'gpt-5'], '/chat/completions',
        ['error' => ['code' => 'insufficient_quota', 'type' => 'insufficient_quota', 'message' => 'You exceeded your current quota']], 429,
        ServiceProblem::Quota, 'GPT (OpenAI) says the credit, quota or subscription ran out. (insufficient_quota)'],
    'Gemini key refused' => ['gemini', ['platform.ai.gemini.key' => 'AIza-1', 'platform.ai.gemini.models.best' => 'gemini-3-pro'], ':generateContent',
        ['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'API_KEY_INVALID']]]], 400,
        ServiceProblem::Key, 'Gemini (Google) refused the key: it is wrong, revoked or expired. (API_KEY_INVALID)'],
    'Ollama has not pulled the model' => ['ollama', ['platform.ai.ollama.url' => 'http://ollama.internal:11434', 'platform.ai.ollama.models.best' => 'qwen3:8b'], '/api/chat',
        ['error' => "model 'qwen3:8b' not found"], 404,
        ServiceProblem::Model, 'Ollama does not offer qwen3:8b: choose another model.'],
]);

function checksService(string $driver): string
{
    return $driver === 'claude' ? 'anthropic' : $driver;
}

it('says the helpers are off, on the demo, or set to the demo where it never answers', function () {
    expect(app(SettingChecks::class)->run('ai')->says())->toBe('The academies\' helpers are switched off for the whole platform.');

    config(['platform.ai.driver' => 'demo']);
    expect(app(SettingChecks::class)->run('ai')->says())->toStartWith('The helpers answer from the demo');

    app()->detectEnvironment(fn () => 'production');
    $result = app(SettingChecks::class)->run('ai');
    app()->detectEnvironment(fn () => 'testing');
    expect($result->level())->toBe('fail')->and($result->says())->toStartWith('The helpers are set to the demo, which never answers in production');
});

it('warns when the speech plan is nearly used, and fails once it is used up or unpaid', function () {
    config(['platform.ai.driver' => 'demo', 'platform.ai.speech.key' => 'eleven-1']);
    $plan = ['status' => 'active', 'character_count' => 92000, 'character_limit' => 100000, 'next_character_count_reset_unix' => 1793232000];
    checksAnswering(['/v1/user/subscription' => function () use (&$plan) {
        return Http::response($plan);
    }]);

    $result = app(SettingChecks::class)->run('speech');
    expect($result->level())->toBe('warn')
        ->and($result->says())->toContain('ElevenLabs: 92% of the plan is used; it renews on 2026-10-29.')
        ->and(ServiceHealth::last('elevenlabs')['near'])->toBe(92)
        ->and(ServiceHealth::failing('elevenlabs'))->toBeFalse();

    $plan['character_count'] = 100000;
    expect(app(SettingChecks::class)->run('speech')->says())->toContain('ElevenLabs says the credit, quota or subscription ran out. (character_limit)')
        ->and(ServiceHealth::problem('elevenlabs'))->toBe(ServiceProblem::Quota);

    $plan = ['status' => 'past_due', 'character_count' => 10, 'character_limit' => 100000];
    expect(app(SettingChecks::class)->run('speech')->says())->toContain('(past_due)');

    $plan = ['status' => 'active', 'character_count' => 1000, 'character_limit' => 100000];
    expect(app(SettingChecks::class)->run('speech')->says())->toContain('ElevenLabs took the key; 1% of the plan is used.')
        ->and(ServiceHealth::failing('elevenlabs'))->toBeFalse();
});

it('gives each AI card its own try: one attempt for the helpers, the games designer and speech, each card its own lines', function () {
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'sk-ant-1', 'platform.ai.models.best' => 'claude-opus-5-5', 'platform.ai.models.fast' => 'claude-opus-5-5',
        'platform.games.designer.driver' => 'claude', 'platform.games.designer.key' => 'sk-ant-1', 'platform.games.designer.model' => 'claude-sonnet-5-5',
        'platform.ai.speech.key' => 'eleven-1']);
    checksAnswering([
        'api.anthropic.com/v1/messages' => fn (Request $r) => $r['model'] === 'claude-opus-5-5'
            ? Http::response(['content' => [['type' => 'text', 'text' => '{"ok":true}']], 'usage' => ['input_tokens' => 9, 'output_tokens' => 5]])
            : Http::response(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'model: '.$r['model']]], 404),
        '/v1/user/subscription' => Http::response(['status' => 'active', 'character_count' => 10, 'character_limit' => 1000]),
    ]);
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    Livewire::test(PlatformSettingsPage::class)
        ->call('check', 'speech')
        ->assertNotified('Speech to text (ElevenLabs)')
        // Every AI card shows what the one attempt found for it.
        ->assertSeeHtml('<bdi>ElevenLabs</bdi> took the key; <bdi>1</bdi>% of the plan is used.')
        ->assertSee(': choose another model.')
        ->assertSee('answered with');

    // One request a card: the helpers' model (best and fast are one here), the designer's, ElevenLabs' plan.
    Http::assertSentCount(3);
    expect(LastChecks::get('speech')['result']->says())->toBe('ElevenLabs took the key; 1% of the plan is used.')
        ->and(LastChecks::get('designer')['result']->level())->toBe('fail')
        ->and(LastChecks::get('ai')['result']->says())->toBe('Claude (Anthropic) answered with claude-opus-5-5.')
        // The designer's model is the one that failed, whichever card was tried.
        ->and(ServiceHealth::last('anthropic')['scope'])->toBe('claude-sonnet-5-5');
});

it('says when the games designer has no model yet, or answers from the demo', function () {
    config(['platform.games.designer.driver' => 'claude', 'platform.games.designer.key' => 'sk-ant-1']);
    expect(app(SettingChecks::class)->run('designer')->says())->toBe('No model is chosen for the games designer yet: the Game studio designs nothing until one is.');

    config(['platform.games.designer.driver' => 'demo']);
    expect(app(SettingChecks::class)->run('designer')->says())->toStartWith('The games designer hands back one of the platform');

    config(['platform.games.designer.driver' => 'claude', 'platform.games.designer.key' => null, 'platform.games.designer.model' => 'claude-opus-5-5']);
    expect(app(SettingChecks::class)->run('designer')->level())->toBe('fail');
});

it('says why WhatsApp would not send: the payment method, a low quality number', function () {
    config(['communication.whatsapp.meta.token' => 'meta-1', 'communication.whatsapp.meta.phone_number_id' => '1234567890']);
    $answer = Http::response(['error' => ['code' => 131042, 'message' => 'Business eligibility payment issue']], 400);
    checksAnswering(['graph.facebook.com' => function () use (&$answer) {
        return $answer;
    }]);

    $result = app(SettingChecks::class)->run('whatsapp');
    expect($result->level())->toBe('fail')
        ->and($result->says())->toBe('WhatsApp (Meta) says the credit, quota or subscription ran out. (131042)')
        ->and($result->says())->not->toContain('eligibility');

    $answer = Http::response(['display_phone_number' => '+20 100 000 0000', 'quality_rating' => 'RED']);
    $result = app(SettingChecks::class)->run('whatsapp');
    expect($result->level())->toBe('warn')->and($result->says())->toContain('rates the number\'s quality low (red)')
        ->and(ServiceHealth::failing('whatsapp'))->toBeFalse();
});

it('says when the Twilio account is suspended, out of money, low or a trial', function () {
    config(['communication.sms.twilio.sid' => 'AC'.str_repeat('b', 32), 'communication.sms.twilio.token' => 'twilio-1']);
    $account = ['status' => 'suspended', 'type' => 'Full'];
    $balance = ['balance' => '-1.20', 'currency' => 'USD'];
    checksAnswering([
        'Balance.json' => function () use (&$balance) {
            return Http::response($balance);
        },
        'api.twilio.com' => function () use (&$account) {
            return Http::response($account);
        },
    ]);
    $checks = app(SettingChecks::class);

    expect($checks->run('sms')->says())->toBe('Twilio has suspended or closed the account. (suspended)')
        ->and(ServiceHealth::problem('twilio'))->toBe(ServiceProblem::Suspended);
    $account['status'] = 'active';
    expect($checks->run('sms')->says())->toBe('Twilio says the credit, quota or subscription ran out. (balance)');
    $balance['balance'] = '3.5';
    $account['type'] = 'Trial';
    $low = $checks->run('sms');
    expect($low->level())->toBe('warn')
        ->and($low->says())->toBe('The Twilio account is a trial: texts reach only the numbers verified there. Twilio has only 3.50 USD left.')
        ->and(ServiceHealth::failing('twilio'))->toBeFalse();
});

it('says when Stripe stopped the platform account or asks for details', function () {
    config(['billing.payments.stripe.platform_secret' => 'sk_test_platform', 'billing.payments.stripe.connect_client_id' => 'ca_1']);
    $account = ['charges_enabled' => false, 'requirements' => ['disabled_reason' => 'rejected.fraud', 'currently_due' => []]];
    checksAnswering(['api.stripe.com/v1/account' => function () use (&$account) {
        return Http::response($account);
    }]);

    expect(app(SettingChecks::class)->run('stripe')->says())->toBe('Stripe has suspended or closed the account. (rejected.fraud)');
    $account = ['charges_enabled' => false, 'requirements' => ['disabled_reason' => null, 'currently_due' => ['business_profile.url'], 'current_deadline' => 1793232000]];
    $result = app(SettingChecks::class)->run('stripe');
    expect($result->level())->toBe('warn')
        ->and($result->says())->toBe('Stripe took the platform\'s key. Stripe has not enabled charges on the platform\'s own account. Stripe asks for more details about the platform\'s account by 2026-10-29.')
        ->and(ServiceHealth::failing('stripe'))->toBeFalse();
});

it('signs in to Firebase and has a send checked without sending, and tries LiveKit\'s recordings too', function () {
    config(['communication.push.fcm.project_id' => 'academy-app-1', 'communication.push.fcm.credentials_json' => checksServiceAccount(),
        'classroom.livekit.key' => 'APIkey', 'classroom.livekit.secret' => str_repeat('s', 40),
        'classroom.livekit.api_url' => 'https://livekit.company.example', 'classroom.livekit.egress_url' => 'https://egress.company.example']);
    $send = Http::response(['name' => 'projects/academy-app-1/messages/fake']);
    checksAnswering([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake', 'expires_in' => 3600]),
        'fcm.googleapis.com' => function () use (&$send) {
            return $send;
        },
        'livekit.company.example' => Http::response(['rooms' => []]),
        'egress.company.example' => Http::response(['code' => 'unauthenticated', 'msg' => 'invalid token'], 401),
    ]);

    expect(app(SettingChecks::class)->run('push')->says())->toBe('Firebase (push) signed the platform in with the file and would take a message.');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/projects/academy-app-1/messages:send') && $r['validate_only'] === true && $r['message']['topic'] === 'platform-settings-check');
    $send = Http::response(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED']], 403);
    expect(app(SettingChecks::class)->run('push')->lines[0]->says)->toBe('platform::services.problems.permission');

    $classroom = app(SettingChecks::class)->run('classroom');
    expect($classroom->level())->toBe('fail')
        ->and($classroom->says())->toBe('LiveKit answered with the key and secret. LiveKit refused the key: it is wrong, revoked or expired. (unauthenticated)')
        ->and(ServiceHealth::last('livekit')['scope'])->toBe('egress');
});

it('warns when a service cannot be reached, as something that may pass', function () {
    config(['platform.ai.driver' => 'ollama', 'platform.ai.ollama.url' => 'http://ollama.internal:11434', 'platform.ai.ollama.models.best' => 'qwen3:8b']);
    Http::fake(fn () => throw new ConnectionException('cURL error 7'));

    $result = app(SettingChecks::class)->run('ai');
    expect($result->level())->toBe('warn')
        ->and($result->says())->toContain('Ollama did not answer: check the address and that the server can reach it. (connection)')
        ->and(ServiceHealth::problem('ollama'))->toBe(ServiceProblem::Unreachable);
});

it('tries what is typed before it is saved, never marking the saved key broken, and keeps the result under the section', function () {
    app(SavePlatformSettings::class)->handle($this->owner, ['stripe_platform_secret' => 'sk_test_saved_one']);
    checksAnswering(['api.stripe.com/v1/account' => fn (Request $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_typed_one:'))
        ? Http::response(['error' => ['type' => 'invalid_request_error']], 401)
        : Http::response(['charges_enabled' => true, 'requirements' => ['currently_due' => []]])]);
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    Livewire::test(PlatformSettingsPage::class)
        ->fillForm(['stripe_platform_secret' => 'sk_test_typed_one'])
        ->call('check', 'stripe')
        ->assertNotified('Stripe Connect')
        ->assertSeeHtml('<bdi>Stripe</bdi> refused the key')
        ->assertSee('with what is typed here and not saved yet')
        ->assertDontSee('sk_test_typed_one');

    expect(ServiceHealth::last('stripe'))->toBe([])
        ->and(LastChecks::get('stripe')['result']->typed)->toBeTrue()
        ->and(config('billing.payments.stripe.platform_secret'))->toBe('sk_test_saved_one')
        ->and(app(PlatformSettings::class)->values()['stripe_platform_secret'])->toBe('sk_test_saved_one');

    // Nothing typed: the saved key, and what it found is written down.
    Livewire::test(PlatformSettingsPage::class)->call('check', 'stripe')->assertSee('It works');
    expect(ServiceHealth::last('stripe')['checked'])->toBeInt()->and(ServiceHealth::failing('stripe'))->toBeFalse();

    // What is typed is checked as a save would check it.
    Livewire::test(PlatformSettingsPage::class)->fillForm(['mail_port' => 'abc'])->call('check', 'mail')->assertHasErrors(['data.mail_port']);
});

it('heads the page with the services that need someone and links to their section', function () {
    config(['billing.payments.stripe.platform_secret' => 'sk_test_platform', 'communication.sms.driver' => 'twilio',
        'communication.sms.twilio.sid' => 'AC'.str_repeat('c', 32), 'communication.sms.twilio.token' => 'twilio-1']);
    ServiceHealth::failed('stripe', ServiceProblem::Key, 'api_key_expired');
    ServiceHealth::ok('twilio');
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    Livewire::test(PlatformSettingsPage::class)
        ->assertSee('Outside services that need you')
        ->assertSeeHtml('href="?tab=payments#ps-stripe"')
        ->assertSeeHtml('id="ps-stripe"')
        ->assertSee('Make a new key at Stripe, put it in Platform settings, then try it.')
        ->assertDontSeeHtml('#ps-sms"')
        ->assertSee('In use');

    ServiceHealth::ok('stripe', '', 'check');
    Livewire::test(PlatformSettingsPage::class)->assertDontSee('Outside services that need you');
});

it('takes a few tries a minute at most', function () {
    config(['billing.payments.stripe.platform_secret' => null]);
    RateLimiter::clear('settings-check:'.$this->owner->id.':stripe');
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');
    $page = Livewire::test(PlatformSettingsPage::class);
    foreach (range(1, 6) as $try) {
        $page->call('check', 'stripe')->assertNotified('Stripe Connect');
    }
    $page->call('check', 'stripe')->assertNotified('A few tries a minute at most: wait a moment, then try again.');
});

it('has every line and reason in both languages', function () {
    foreach (['platform::settings.checks.lines', 'platform::settings.checks.level', 'platform::services.problems', 'platform::services.todo', 'platform::services.short', 'platform::services.names'] as $group) {
        $en = array_keys((array) trans()->get($group, [], 'en', false));
        expect($en)->not->toBeEmpty()->and(array_keys((array) trans()->get($group, [], 'ar', false)))->toBe($en, $group);
    }
    foreach (ServiceProblem::cases() as $problem) {
        expect(trans()->hasForLocale('platform::services.problems.'.$problem->value, 'ar'))->toBeTrue()
            ->and(trans()->hasForLocale('platform::services.todo.'.$problem->value, 'ar'))->toBeTrue();
    }
});
