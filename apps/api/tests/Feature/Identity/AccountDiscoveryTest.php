<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\OtpSender;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\SignIn;
use Modules\Identity\Models\User;
use Modules\Platform\Models\Academy;

beforeEach(function () {
    $this->withoutDefer();
    $this->sender = new class implements OtpSender
    {
        /** @var list<OtpMessage> */
        public array $sent = [];

        public function supports(string $channel): bool
        {
            return true;
        }

        public function send(OtpMessage $message): void
        {
            $this->sent[] = $message;
        }
    };
    app()->instance(OtpSender::class, $this->sender);

    // The same mother at two academies, and a third academy that is suspended.
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'name_en' => 'Noor']));
    $this->huda = User::factory()->withRole(Role::Guardian)->create(['name' => 'Huda', 'email' => 'huda@example.test', 'phone_e164' => '+966500000001']);
    $this->rawda = $this->actAsTenant($this->createAcademy(['slug' => 'rawda', 'name_en' => 'Rawda']));
    $this->hudaAtRawda = User::factory()->withRole(Role::Guardian)->withRole(Role::Teacher)->create(['name' => 'Huda A.', 'email' => 'HUDA@example.test']);
    $closed = $this->actAsTenant($this->createAcademy(['slug' => 'closed', 'status' => 'suspended']));
    User::factory()->withRole(Role::Guardian)->create(['email' => 'huda@example.test']);
    $this->clearTenant();
});

function discover(string $contact)
{
    return test()->postJson('/api/v1/accounts/code', ['contact' => $contact]);
}

it('finds my accounts in every active academy once the code is right, and signs in to each', function () {
    $sent = discover('huda@example.test')->assertStatus(202)->assertJsonPath('channel', 'email');
    $message = $this->sender->sent[0];
    expect($message->senderName)->toBe(config('app.name'));

    $found = $this->postJson('/api/v1/accounts/code/verify', ['challenge_id' => $sent->json('challenge_id'), 'code' => $message->code])
        ->assertOk()
        ->assertJsonStructure(['discovery_token', 'expires_at' => ['utc'], 'accounts' => [['academy' => ['id', 'slug', 'name_ar', 'name_en', 'logo_url', 'colour'], 'user' => ['id', 'name', 'roles']]]])
        ->json();

    expect(collect($found['accounts'])->pluck('academy.slug')->all())->toBe(['noor', 'rawda'])
        ->and(collect($found['accounts'])->firstWhere('academy.slug', 'rawda')['user']['roles'])->toBe(['guardian', 'teacher']);

    foreach ([['noor', $this->huda->id], ['rawda', $this->hudaAtRawda->id]] as [$slug, $userId]) {
        $this->withHeaders(['X-Academy' => $slug])->postJson('/api/v1/accounts/sign-in', [
            'discovery_token' => $found['discovery_token'], 'user_id' => $userId, 'device_name' => 'iPhone',
        ])->assertOk()->assertJsonPath('user.id', $userId)->assertJsonPath('user.academy.slug', $slug);
    }
});

it('works with a phone number, which must carry its country', function () {
    discover('0500000001')->assertUnprocessable()->assertJsonPath('errors.contact.0', 'Enter an email, or a mobile number with its country code (+966…).');

    $id = discover('00966 50 000 0001')->assertStatus(202)->assertJsonPath('channel', 'whatsapp')->json('challenge_id');
    $found = $this->postJson('/api/v1/accounts/code/verify', ['challenge_id' => $id, 'code' => $this->sender->sent[0]->code])->assertOk();

    expect(collect($found->json('accounts'))->pluck('user.id')->all())->toBe([$this->huda->id]);
});

it('says nothing before the code, and sends nothing to a contact with no accounts', function () {
    $none = discover('stranger@example.test')->assertStatus(202)->json();
    expect(array_keys($none))->toBe(['challenge_id', 'channel', 'sent_to', 'expires_at', 'resend_after', 'channels'])
        ->and($this->sender->sent)->toBe([]);
});

it('signs in only to an account carrying the verified contact, in the academy asked, for a few minutes', function () {
    $id = discover('huda@example.test')->json('challenge_id');
    $token = $this->postJson('/api/v1/accounts/code/verify', ['challenge_id' => $id, 'code' => $this->sender->sent[0]->code])->json('discovery_token');

    $this->actAsTenant($this->noor);
    $someoneElse = User::factory()->withRole(Role::Guardian)->create(['email' => 'else@example.test']);
    $this->clearTenant();

    $signIn = fn (string $slug, string $userId) => $this->withHeaders(['X-Academy' => $slug])->postJson('/api/v1/accounts/sign-in', [
        'discovery_token' => $token, 'user_id' => $userId, 'device_name' => 'iPhone',
    ]);

    $signIn('noor', $someoneElse->id)->assertUnprocessable()->assertJsonPath('errors.user_id.0', 'This account does not use the email or number you verified.');
    $signIn('noor', $this->hudaAtRawda->id)->assertUnprocessable();   // RLS: not this academy's account
    $signIn('noor', 'not-a-uuid')->assertUnprocessable();

    $this->travel(16)->minutes();
    $signIn('noor', $this->huda->id)->assertUnprocessable()->assertJsonPath('errors.discovery_token.0', 'Your check has expired. Ask for a new code.');
});

it('asks for a code instead of the password on the panels\' sign-in page', function () {
    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('family');

    $page = Livewire::test(SignIn::class)
        ->call('mountAction', 'useCode')->call('callMountedAction')
        ->assertSet('mode', 'code')
        ->fillForm(['email' => 'huda@example.test'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertSee('h•••@example.test');

    expect($this->sender->sent)->toHaveCount(1);

    $page->fillForm(['code' => $this->sender->sent[0]->code === '000000' ? '111111' : '000000'])->call('authenticate')->assertHasFormErrors(['code']);
    expect(Auth::check())->toBeFalse();

    $page->fillForm(['code' => $this->sender->sent[0]->code])->call('authenticate')->assertHasNoFormErrors();
    expect(Auth::id())->toBe($this->huda->id);
});

it('offers the code by text when WhatsApp was used, and keeps the door rules', function () {
    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('academy');      // the back office: a parent cannot come in

    $page = Livewire::test(SignIn::class)
        ->call('mountAction', 'useCode')->call('callMountedAction')
        ->fillForm(['email' => '+966500000001'])
        ->call('authenticate')
        ->assertSet('channel', 'whatsapp');

    $this->travel(61)->seconds();
    $page->call('mountAction', 'resendBy')->call('callMountedAction')->assertSet('channel', 'sms');
    expect(collect($this->sender->sent)->pluck('channel')->all())->toBe(['whatsapp', 'sms']);

    $page->fillForm(['code' => $this->sender->sent[1]->code])->call('authenticate')->assertHasFormErrors(['email'])->assertSet('challengeId', null);
    expect(Auth::check())->toBeFalse();
});
