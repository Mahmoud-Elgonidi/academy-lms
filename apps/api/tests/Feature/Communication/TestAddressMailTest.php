<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Modules\Communication\Actions\SaveCommunicationSettings;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Models\Notification;
use Modules\Communication\Otp\CommunicationOtpSender;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Health\TestMail;
use Modules\SharedKernel\Health\MailHealth;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/*
 * Mail to the demo's addresses (…@demo.test, …@platform.test) never goes out
 * through a server's SMTP account (docs/architecture/03, section 12): .test
 * can never be delivered, so it would only bounce and turn mail red on the
 * health page. A notification to one is recorded as skipped, a sign-in code
 * or an alert to one is simply not sent, and real addresses on the same
 * message still get it. Mail that goes nowhere anyway (log, array) is left
 * as it is.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 12:00', 'UTC'));
    Cache::forget('health:mail');
    // A mailer that goes out, as SMTP does on a server, but into memory.
    $this->transport = new class extends AbstractTransport
    {
        /** @var list<Email> */
        public array $sent = [];

        protected function doSend(SentMessage $message): void
        {
            $this->sent[] = $message->getOriginalMessage();
        }

        public function __toString(): string
        {
            return 'outbound://memory';
        }
    };
    $transport = $this->transport;
    Mail::extend('outbound', fn (): TransportInterface => $transport);
    config(['mail.mailers.outbound' => ['transport' => 'outbound'], 'mail.default' => 'outbound']);
    Mail::forgetMailers();

    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    app(SaveCommunicationSettings::class)->handle(['whatsapp_enabled' => false]);
    app(NotificationsApi::class)->define(new TemplateDefinition(
        key: 'test.hello', category: 'lessons', title: 'test.title', body: 'test.body', audience: 'test.audience', channels: ['email'], sample: [],
    ));
    app('translator')->addLines(['test.title' => 'Hello', 'test.body' => 'A lesson moved.'], 'en');
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** @return list<string> */
function outboundTo(object $transport): array
{
    return collect($transport->sent)->flatMap(fn ($m) => array_map(fn ($a) => $a->getAddress(), [...$m->getTo(), ...$m->getCc(), ...$m->getBcc()]))->all();
}

it('records a notification to a .test address as skipped, and sends one to a real address', function () {
    $demo = User::factory()->withRole(Role::Guardian)->create(['email' => 'khaled@demo.test', 'locale' => 'en']);
    $real = User::factory()->withRole(Role::Guardian)->create(['email' => 'huda@noor-academy.example', 'locale' => 'en']);

    app(NotificationsApi::class)->send(new Message('test.hello', [$demo->id, $real->id], []));

    $skipped = Notification::query()->where('user_id', $demo->id)->sole()->deliveries()->sole();
    $sent = Notification::query()->where('user_id', $real->id)->sole()->deliveries()->sole();
    expect($skipped->status)->toBe('skipped')->and($skipped->error)->toBe('test_address')
        ->and($sent->status)->toBe('sent')
        ->and(outboundTo($this->transport))->toBe(['huda@noor-academy.example'])
        ->and(MailHealth::failing())->toBeFalse();
});

it('sends no sign-in code to a .test address and does not count it as mail down', function () {
    app(CommunicationOtpSender::class)->send(new OtpMessage('email', 'owner@demo.test', '123456', 10, 'en', 'Noor'));
    app(CommunicationOtpSender::class)->send(new OtpMessage('email', 'Admin@Platform.TEST', '123456', 10, 'en', 'Noor'));

    expect($this->transport->sent)->toBe([])
        ->and(MailHealth::last())->toBe([])
        ->and(MailHealth::failing())->toBeFalse();
});

it('takes the .test addresses off a message and sends it to the rest', function () {
    Mail::to(['admin@platform.test', 'ops@company.example'])->cc('auditor@sub.demo.test')->send(new TestMail);

    expect(outboundTo($this->transport))->toBe(['ops@company.example'])
        ->and(MailHealth::last())->toHaveKey('sent');
});

it('leaves mail that goes nowhere anyway as it is', function () {
    config(['mail.default' => 'array']);
    Mail::forgetMailers();
    Mail::to('owner@demo.test')->send(new TestMail);

    expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);
});
