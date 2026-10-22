<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Actions\DeliverNotification;
use Modules\Communication\Actions\RegisterPushDevice;
use Modules\Communication\Actions\ReleaseHeldDeliveries;
use Modules\Communication\Actions\ResendDelivery;
use Modules\Communication\Actions\SaveCommunicationSettings;
use Modules\Communication\Actions\SaveTemplateWording;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\Events\NotificationFailed;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Jobs\SendDelivery;
use Modules\Communication\Mail\NotificationMail;
use Modules\Communication\Models\Delivery;
use Modules\Communication\Models\Notification;
use Modules\Communication\Models\PushDevice;
use Modules\Communication\NotificationPreferences;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 12:00', 'UTC'));
    Mail::fake();
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    app(SaveCommunicationSettings::class)->handle(['whatsapp_enabled' => true]);
    $this->sara = User::factory()->withRole(Role::Guardian)->create(['name' => 'سارة', 'locale' => 'ar', 'timezone' => 'Asia/Riyadh', 'phone_e164' => '+966500000001']);
    $this->tom = User::factory()->withRole(Role::Student)->create(['name' => 'Tom', 'locale' => 'en', 'timezone' => 'Europe/London', 'phone_e164' => null]);
    app(RegisterPushDevice::class)->handle($this->sara->id, 'android', 'token-sara', '1.0.0');

    app(NotificationsApi::class)->define(new TemplateDefinition(
        key: 'test.lesson_moved', category: 'lessons',
        title: 'test.title', body: 'test.body', audience: 'test.audience',
        channels: ['push', 'whatsapp', 'email'], sample: ['subject' => 'Quran', 'when' => 'Tue 18:00', 'status' => 'moved'],
    ));
    app('translator')->addLines(['test.title' => 'Lesson moved', 'test.body' => ':subject moves to :when (:status).'], 'en');
    app('translator')->addLines(['test.title' => 'تغيير موعد', 'test.body' => 'حصة :subject اتنقلت لـ :when (:status).'], 'ar');
    app('translator')->addLines(['test.moved' => 'moved'], 'en');
    app('translator')->addLines(['test.moved' => 'اتأجلت'], 'ar');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function moved(array $to, ?string $dedupe = null, bool $urgent = false): int
{
    return app(NotificationsApi::class)->send(new Message('test.lesson_moved', $to, [
        'subject' => 'Quran', 'when' => CarbonImmutable::parse('2026-10-06 15:00', 'UTC'), 'status' => new Translatable('test.moved'),
    ], ['screen' => 'session', 'id' => 's-1'], $dedupe, $urgent));
}

it('writes each person\'s message in their language, time zone and clock, and sends it on every channel they can be reached on', function () {
    $this->tom->update(['time_format' => 'h24']);   // Sara keeps the academy's 12 hours
    expect(moved([$this->sara->id, $this->tom->id, $this->sara->id]))->toBe(2);

    $sara = Notification::query()->where('user_id', $this->sara->id)->sole();
    $tom = Notification::query()->where('user_id', $this->tom->id)->sole();
    expect($sara->title)->toBe('تغيير موعد')
        ->and($sara->body)->toContain('6:00 م')->toContain('اتأجلت')       // 15:00 UTC on Riyadh's clock, in 12 hours
        ->and($tom->body)->toBe('Quran moves to Tue 6 Oct · 16:00 (moved).')
        ->and($sara->action)->toEqualCanonicalizing(['screen' => 'session', 'id' => 's-1']);

    expect($sara->deliveries()->pluck('status', 'channel')->sortKeys()->all())->toBe(['email' => 'sent', 'push' => 'sent', 'whatsapp' => 'sent'])
        ->and($tom->deliveries()->pluck('status', 'channel')->sortKeys()->all())->toBe(['email' => 'sent', 'push' => 'skipped', 'whatsapp' => 'skipped'])
        ->and($tom->deliveries()->where('channel', 'whatsapp')->value('error'))->toBe('no_address');
    Mail::assertSent(NotificationMail::class, fn ($mail) => $mail->hasTo($this->tom->email) && $mail->heading === 'Lesson moved');
});

it('sends a dedupe key once per person, and skips suspended accounts', function () {
    moved([$this->sara->id], 'moved:s-1');
    moved([$this->sara->id], 'moved:s-1');
    $this->tom->update(['status' => 'disabled']);

    expect(moved([$this->tom->id]))->toBe(0)
        ->and(Notification::query()->where('user_id', $this->sara->id)->count())->toBe(1);
});

it('holds WhatsApp and push for quiet hours, but not email, and not when urgent', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 21:00', 'UTC'));   // midnight in Riyadh
    moved([$this->sara->id]);
    $deliveries = Notification::query()->sole()->deliveries()->get()->keyBy('channel');

    expect($deliveries['whatsapp']->status)->toBe('held')
        ->and($deliveries['whatsapp']->not_before->setTimezone('Asia/Riyadh')->format('H:i'))->toBe('07:00')
        ->and($deliveries['email']->status)->toBe('sent');

    expect(app(ReleaseHeldDeliveries::class)->handle(CarbonImmutable::parse('2026-10-07 03:59', 'UTC')))->toBe(0)
        ->and(app(ReleaseHeldDeliveries::class)->handle(CarbonImmutable::parse('2026-10-07 04:00', 'UTC')))->toBe(2)
        ->and($deliveries['whatsapp']->refresh()->status)->toBe('sent');

    moved([$this->sara->id], urgent: true);
    expect(Notification::query()->where('urgent', true)->sole()->deliveries()->where('status', 'held')->count())->toBe(0);
});

it('follows each person\'s choice per category, and the academy\'s channel switches', function () {
    app(NotificationPreferences::class)->set($this->sara->id, 'notifications.lessons', 'no_whatsapp');
    moved([$this->sara->id]);
    expect(Notification::query()->latest('created_at')->first()->deliveries()->pluck('channel')->sort()->values()->all())->toBe(['email', 'push']);

    app(NotificationPreferences::class)->set($this->sara->id, 'notifications.lessons', 'app');
    moved([$this->sara->id], 'second');
    expect(Notification::query()->where('dedupe_key', 'second')->sole()->deliveries()->count())->toBe(0);

    app(NotificationPreferences::class)->set($this->sara->id, 'notifications.lessons', 'all');
    app(SaveCommunicationSettings::class)->handle(['whatsapp_enabled' => false, 'email_enabled' => false]);
    moved([$this->sara->id], 'third');
    expect(Notification::query()->where('dedupe_key', 'third')->sole()->deliveries()->pluck('channel')->all())->toBe(['push']);
});

it('keeps to the channels a message names, among its template\'s', function () {
    $send = fn (?array $channels) => app(NotificationsApi::class)->send(new Message('test.lesson_moved', [$this->sara->id], [
        'subject' => 'Quran', 'when' => CarbonImmutable::parse('2026-10-06 15:00', 'UTC'), 'status' => 'moved',
    ], channels: $channels));

    $send(['email', 'sms']);   // a channel the template does not have is never added
    $send([]);                 // the inbox alone
    $send(null);               // every channel of the template

    $sent = Notification::query()->where('user_id', $this->sara->id)->orderBy('created_at')->orderBy('id')->get()
        ->map(fn (Notification $n) => $n->deliveries()->orderBy('channel')->pluck('channel')->all())->all();
    expect($sent)->toContain(['email'], [], ['email', 'push', 'whatsapp'])->toHaveCount(3);
});

it('uses the academy\'s own wording, and refuses placeholders the template does not have', function () {
    app(SaveTemplateWording::class)->handle('test.lesson_moved', 'ar', 'موعد جديد', 'حصة :subject بقت :when.', null);
    moved([$this->sara->id]);
    expect(Notification::query()->sole()->title)->toBe('موعد جديد');

    expect(fn () => app(SaveTemplateWording::class)->handle('test.lesson_moved', 'ar', 'x', 'Hello :teacher', null))->toThrow(ValidationException::class);
    app(SaveTemplateWording::class)->handle('test.lesson_moved', 'ar', '', '', null);    // back to the default
    moved([$this->sara->id], 'again');
    expect(Notification::query()->where('dedupe_key', 'again')->sole()->title)->toBe('تغيير موعد');
});

it('sends WhatsApp through the approved Meta template, records a failure after the retries, and resends from the log', function () {
    config(['communication.whatsapp.driver' => 'meta', 'communication.whatsapp.meta.token' => 't0k', 'communication.whatsapp.meta.phone_number_id' => '123']);
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'Template not approved']], 400)
        ->push(['messages' => [['id' => 'wamid.OK']]], 200)]);
    Queue::fake();
    Event::fake([NotificationFailed::class]);

    moved([$this->sara->id]);
    $delivery = Delivery::query()->where('channel', 'whatsapp')->sole();
    $job = new SendDelivery($delivery->id);
    try {
        $job->handle(app(DeliverNotification::class));
    } catch (RuntimeException $e) {
        $job->failed($e);
    }

    expect($delivery->refresh()->status)->toBe('failed')->and($delivery->error)->toContain('Template not approved');
    Event::assertDispatched(NotificationFailed::class);
    Http::assertSent(fn ($request) => $request['to'] === '966500000001' && $request['template']['name'] === 'academy_notification'
        && $request['template']['language']['code'] === 'ar' && $request['template']['components'][0]['parameters'][0]['text'] === 'تغيير موعد');

    app(ResendDelivery::class)->handle($delivery);
    (new SendDelivery($delivery->id))->handle(app(DeliverNotification::class));
    expect($delivery->refresh()->status)->toBe('sent')->and($delivery->provider_message_id)->toBe('wamid.OK');
});

it('pushes through FCM and forgets devices the app was removed from', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode(['client_email' => 'push@noor.iam.gserviceaccount.com', 'private_key' => $pem]));
    config(['communication.push.driver' => 'fcm', 'communication.push.fcm' => ['project_id' => 'noor-app', 'credentials' => $path]]);
    app(RegisterPushDevice::class)->handle($this->sara->id, 'ios', 'token-old-phone', '0.9.0');
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.x']),
        'fcm.googleapis.com/*' => fn ($request) => $request['message']['token'] === 'token-old-phone'
            ? Http::response(['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]], 404)
            : Http::response(['name' => 'projects/noor-app/messages/1']),
    ]);

    moved([$this->sara->id]);

    expect(Delivery::query()->where('channel', 'push')->sole()->status)->toBe('sent')
        ->and(PushDevice::query()->pluck('token')->all())->toBe(['token-sara']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
        && $request['message']['data']['screen'] === 'session'
        && $request['message']['data']['academy'] === $this->academy->slug
        && $request['message']['data']['user'] === $this->sara->id);
});

it('never breaks what caused a message when the message cannot be written', function () {
    Exceptions::fake();
    $broken = new class
    {
        public function __toString(): string
        {
            throw new RuntimeException('cannot write this');
        }
    };

    $sent = app(NotificationsApi::class)->send(new Message('test.lesson_moved', [$this->sara->id], ['subject' => $broken]));

    expect($sent)->toBe(0)->and(Notification::query()->count())->toBe(0);
    Exceptions::assertReported(RuntimeException::class);
});

it('never shows one academy the messages of another', function () {
    moved([$this->sara->id]);
    $this->actAsTenant($this->createAcademy(['slug' => 'other']));

    expect(Notification::query()->count())->toBe(0)->and(Delivery::query()->count())->toBe(0)->and(PushDevice::query()->count())->toBe(0);
});
