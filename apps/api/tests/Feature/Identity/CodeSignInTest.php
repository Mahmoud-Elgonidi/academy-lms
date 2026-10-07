<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Communication\Mail\OtpMail;
use Modules\Communication\Otp\CommunicationOtpSender;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\OtpSender;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;

/** Catches what would be sent, so a test can read the code. */
final class CapturingOtpSender implements OtpSender
{
    /** @var list<OtpMessage> */
    public array $sent = [];

    public function __construct(private readonly array $channels = ['whatsapp', 'sms', 'email']) {}

    public function supports(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function send(OtpMessage $message): void
    {
        $this->sent[] = $message;
    }

    public function lastCode(): ?string
    {
        return $this->sent === [] ? null : $this->sent[array_key_last($this->sent)]->code;
    }
}

beforeEach(function () {
    $this->withoutDefer();
    $this->sender = new CapturingOtpSender;
    app()->instance(OtpSender::class, $this->sender);

    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'name_en' => 'Noor Academy']));
    $this->user = User::factory()->withRole(Role::Guardian)->create([
        'name' => 'Huda', 'email' => 'huda@example.test', 'phone_e164' => '+966500000001', 'locale' => 'en',
    ]);
    $this->clearTenant();
});

function askCode(string $contact, array $extra = [], array $headers = [])
{
    return test()->withHeaders(['X-Academy' => 'noor'] + $headers)->postJson('/api/v1/auth/code', ['contact' => $contact] + $extra);
}

function verifyCode(string $challenge, string $code)
{
    return test()->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/code/verify', [
        'challenge_id' => $challenge, 'code' => $code, 'device_name' => 'Pixel 9',
    ]);
}

it('sends a code by WhatsApp to a phone and signs in with it', function () {
    $sent = askCode('+966 50 000 0001')
        ->assertStatus(202)
        ->assertJsonPath('channel', 'whatsapp')
        ->assertJsonPath('sent_to', '+966•••••••01')
        ->assertJsonPath('channels', ['whatsapp', 'sms'])
        ->assertJsonPath('resend_after', 60);

    $message = $this->sender->sent[0];
    expect($message->destination)->toBe('+966500000001')
        ->and($message->code)->toMatch('/^\d{6}$/')
        ->and($message->senderName)->toBe('Noor Academy')
        ->and($message->locale)->toBe('en');

    verifyCode($sent->json('challenge_id'), $message->code)
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $this->user->id);

    expect(PersonalAccessToken::query()->sole()->name)->toBe('Pixel 9');
    // Only hashes are kept, and the contact encrypted.
    $row = DB::table('otp_challenges')->sole();
    expect($row->code_hash)->not->toContain($message->code)
        ->and($row->destination)->not->toContain('966500000001');
});

it('finds a local number the way the web sign-in does, and takes Arabic digits in the code', function () {
    $sent = askCode('0500000001')->assertStatus(202);
    $arabic = strtr($this->sender->lastCode(), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    verifyCode($sent->json('challenge_id'), mb_substr($arabic, 0, 3).' '.mb_substr($arabic, 3))->assertOk();
});

it('answers the same for a contact with no account, and sends nothing', function () {
    $known = askCode('huda@example.test')->assertStatus(202)->json();
    $unknown = askCode('nobody@example.test')->assertStatus(202)->json();

    expect(array_keys($unknown))->toBe(array_keys($known))
        ->and($unknown['channel'])->toBe('email')
        ->and($this->sender->sent)->toHaveCount(1);

    // No code can open it.
    foreach (range(0, 2) as $_) {
        verifyCode($unknown['challenge_id'], '123456')->assertUnprocessable();
    }
});

it('counts wrong codes and closes the challenge at the fifth', function () {
    $id = askCode('huda@example.test')->json('challenge_id');
    $right = $this->sender->lastCode();
    $wrong = $right === '000000' ? '111111' : '000000';

    verifyCode($id, $wrong)->assertUnprocessable()->assertJsonPath('errors.code.0', 'That code is not right. 4 tries left.');
    foreach (range(1, 3) as $_) {
        verifyCode($id, $wrong)->assertUnprocessable();
    }
    verifyCode($id, $wrong)->assertJsonPath('errors.code.0', 'Too many wrong codes. Ask for a new one.');
    verifyCode($id, $right)->assertUnprocessable()->assertJsonPath('errors.code.0', 'Too many wrong codes. Ask for a new one.');
});

it('refuses a used, expired or replaced code', function () {
    $first = askCode('huda@example.test')->json('challenge_id');
    $code = $this->sender->lastCode();
    verifyCode($first, $code)->assertOk();
    verifyCode($first, $code)->assertUnprocessable()->assertJsonPath('errors.code.0', 'This code has expired. Ask for a new one.');

    $this->travel(61)->seconds();
    $second = askCode('huda@example.test')->json('challenge_id');
    $old = $this->sender->lastCode();
    $this->travel(61)->seconds();
    askCode('huda@example.test')->assertStatus(202);
    verifyCode($second, $old)->assertUnprocessable();   // only the newest code works

    $this->travel(61)->seconds();
    $late = askCode('huda@example.test')->json('challenge_id');
    $lateCode = $this->sender->lastCode();
    $this->travel(11)->minutes();
    verifyCode($late, $lateCode)->assertUnprocessable();
});

it('waits a minute between codes and caps codes per contact and per address', function () {
    askCode('huda@example.test')->assertStatus(202);
    askCode('huda@example.test')->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('type', 'too-many-requests');

    foreach (range(2, 5) as $_) {
        $this->travel(61)->seconds();
        askCode('huda@example.test')->assertStatus(202);
    }
    $this->travel(61)->seconds();
    askCode('huda@example.test')->assertStatus(429);

    // Many contacts from one address.
    foreach (range(1, 15) as $i) {
        askCode("someone{$i}@example.test")->assertStatus(202);
    }
    askCode('one-more@example.test')->assertStatus(429);
});

it('sends by text when asked, and refuses a channel that does not fit', function () {
    askCode('+966500000001', ['channel' => 'sms'])->assertStatus(202)->assertJsonPath('channel', 'sms');
    $this->travel(61)->seconds();
    askCode('huda@example.test', ['channel' => 'sms'])->assertUnprocessable()->assertJsonPath('errors.channel.0', 'A code cannot be sent that way.');
    askCode('huda')->assertUnprocessable()->assertJsonPath('errors.contact.0', 'Enter your email or mobile number to get a code.');
});

it('gives no code to a disabled account', function () {
    $this->actAsTenant($this->academy);
    $this->user->update(['status' => 'disabled']);
    $this->clearTenant();

    $id = askCode('huda@example.test')->assertStatus(202)->json('challenge_id');
    expect($this->sender->sent)->toBe([]);
    verifyCode($id, '000000')->assertUnprocessable();
});

it('does not let a code from one academy sign in at another', function () {
    $other = $this->createAcademy(['slug' => 'rawda']);
    $id = askCode('huda@example.test')->json('challenge_id');

    $this->withHeaders(['X-Academy' => 'rawda'])->postJson('/api/v1/auth/code/verify', [
        'challenge_id' => $id, 'code' => $this->sender->lastCode(), 'device_name' => 'x',
    ])->assertUnprocessable();
});

it('lists my signed-in devices and signs one out', function () {
    $this->actAsTenant($this->academy);
    $phone = $this->user->createToken('Old phone')->plainTextToken;
    $tablet = $this->user->createToken('Tablet')->plainTextToken;
    $this->clearTenant();

    $list = $this->withToken($tablet)->getJson('/api/v1/me/sessions')->assertOk()->json('data');
    expect(collect($list)->pluck('device')->sort()->values()->all())->toBe(['Old phone', 'Tablet'])
        ->and(collect($list)->firstWhere('device', 'Tablet')['current'])->toBeTrue();

    $oldId = collect($list)->firstWhere('device', 'Old phone')['id'];
    $this->withToken($tablet)->deleteJson("/api/v1/me/sessions/{$oldId}")->assertNoContent();
    app('auth')->forgetGuards();
    $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();
});

it('cannot sign out someone else\'s device', function () {
    $this->actAsTenant($this->academy);
    $other = User::factory()->withRole(Role::Guardian)->create(['email' => 'other@example.test']);
    $theirs = $other->createToken('Theirs');
    $mine = $this->user->createToken('Mine')->plainTextToken;
    $this->clearTenant();

    $this->withToken($mine)->deleteJson("/api/v1/me/sessions/{$theirs->accessToken->id}")->assertNotFound();
    expect(PersonalAccessToken::query()->whereKey($theirs->accessToken->id)->exists())->toBeTrue();
});

it('words the email in the person\'s language and the academy\'s name', function () {
    Mail::fake();
    app()->forgetInstance(OtpSender::class);
    app()->singleton(OtpSender::class, CommunicationOtpSender::class);

    askCode('huda@example.test')->assertStatus(202);

    Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
        $mail->assertSeeInHtml($mail->otp->code);

        return $mail->hasTo('huda@example.test') && str_contains($mail->envelope()->subject, 'Noor Academy');
    });
});

it('sends WhatsApp codes by the authentication template and texts through Twilio', function () {
    config([
        'communication.whatsapp.driver' => 'meta',
        'communication.whatsapp.meta' => ['token' => 't', 'phone_number_id' => '123', 'api_version' => 'v21.0', 'template' => 'academy_notification', 'otp_template' => 'sign_in_code'],
        'communication.sms.driver' => 'twilio',
        'communication.sms.twilio' => ['sid' => 'AC1', 'token' => 'secret', 'from' => '+15550001111', 'messaging_service_sid' => null],
    ]);
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        'api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201),
    ]);
    app()->forgetInstance(OtpSender::class);
    app()->singleton(OtpSender::class, CommunicationOtpSender::class);

    askCode('+966500000001')->assertStatus(202);
    $this->travel(61)->seconds();
    askCode('+966500000001', ['channel' => 'sms'])->assertStatus(202);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'graph.facebook.com')) {
            return false;
        }
        $template = $request['template'];

        return $template['name'] === 'sign_in_code' && $template['language']['code'] === 'en'
            && $template['components'][0]['parameters'][0]['text'] === $template['components'][1]['parameters'][0]['text']
            && $template['components'][1]['sub_type'] === 'url';
    });
    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com/2010-04-01/Accounts/AC1/Messages.json')
        && $request['To'] === '+966500000001'
        && str_contains($request['Body'], 'Noor Academy')
        && preg_match('/@\S+ #\d{6}$/', $request['Body']) === 1);
});
