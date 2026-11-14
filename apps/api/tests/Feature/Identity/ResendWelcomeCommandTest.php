<?php

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\Models\User;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Audit\AuditActor;
use Symfony\Component\Console\Tester\CommandTester;

/*
 * An owner's welcome sent again from GitHub (console.sh welcome →
 * platform:welcome): one email with a fresh link, to the owner and copied to
 * the addresses given; only while the owner has no password; the link is
 * never printed; audited with the number of copies.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->clearTenant();
    SaasPlan::query()->create(['code' => 'starter', 'name_ar' => 'البداية', 'name_en' => 'Starter', 'features' => [], 'is_default' => true]);
    // A real mailer's name (the command refuses log/array), carried by the array transport here.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'array'], 'mail.from.address' => 'no-reply@platform.example']);
    $tester = new CommandTester(Artisan::all()['platform:onboard']);
    $tester->setInputs([json_encode([
        'slug' => 'nibras', 'name_ar' => 'أكاديمية نبراس', 'name_en' => 'Nibras Academy',
        'country' => 'QA', 'currency' => 'QAR', 'timezone' => 'Asia/Qatar', 'locale' => 'ar',
        'owner_name' => 'خالد', 'owner_email' => 'khaled@nibras.example', 'owner_phone' => null,
        'plan' => 'starter', 'features_off' => [], 'hidden' => [], 'reason' => 'New client',
    ], JSON_UNESCAPED_UNICODE)]);
    expect($tester->execute([], ['interactive' => false]))->toBe(0, $tester->getDisplay());
    $this->clearTenant();
});

afterEach(fn () => AuditActor::forget());

/** @return array{0: int, 1: string} */
function resendWelcome(array $input): array
{
    $tester = new CommandTester(Artisan::all()['platform:welcome']);
    $tester->setInputs([json_encode($input, JSON_UNESCAPED_UNICODE)]);
    $code = $tester->execute([], ['interactive' => false]);

    return [$code, $tester->getDisplay()];
}

function welcomeInput(array $overrides = []): array
{
    return $overrides + [
        'slug' => 'nibras', 'email' => 'Khaled@Nibras.example',
        'cc' => ['amr@partner.example', 'hazem@partner.example'], 'reason' => 'The first one did not arrive',
    ];
}

it('sends the owner one email with a fresh link, copied to the addresses given, and never prints the link', function () {
    $sent = [];
    Event::listen(MessageSending::class, function (MessageSending $e) use (&$sent) {
        $sent[] = $e->message;
    });

    [$code, $out] = resendWelcome(welcomeInput());

    expect($code)->toBe(0, $out)
        ->and($out)->toContain('Welcome sent to khaled@nibras.example with 2 copies')
        ->and($out)->not->toContain('/welcome/')->not->toContain('signature');
    expect($sent)->toHaveCount(1);
    $message = $sent[0];
    expect(array_map(fn ($a) => $a->getAddress(), $message->getTo()))->toBe(['khaled@nibras.example'])
        ->and(array_map(fn ($a) => $a->getAddress(), $message->getCc()))->toBe(['amr@partner.example', 'hazem@partner.example'])
        ->and($message->getHtmlBody())->toContain('nibras.')->toContain('/welcome/')->toContain('signature=');

    $academy = Academy::query()->where('slug', 'nibras')->firstOrFail();
    $this->actAsTenant($academy->toTenantRef());
    $audit = DB::table('audit_logs')->where('action', 'academy.welcome_resent')->first();
    expect($audit)->not->toBeNull()
        ->and(json_decode($audit->data, true))->toMatchArray(['copies' => 2])
        ->and($audit->data)->not->toContain('partner.example');
});

it('refuses an owner who already chose a password, an unknown email, a bad copy, and a server with no mail set up', function () {
    Mail::fake();

    expect(resendWelcome(welcomeInput(['email' => 'nobody@nibras.example']))[0])->toBe(1)
        ->and(resendWelcome(welcomeInput(['cc' => ['not an email']]))[0])->toBe(1)
        ->and(resendWelcome(welcomeInput(['slug' => 'nowhere']))[0])->toBe(1);

    config(['mail.default' => 'log']);
    [$code, $out] = resendWelcome(welcomeInput());
    expect($code)->toBe(1)->and($out)->toContain('No email can leave this server');
    config(['mail.default' => 'smtp']);

    $academy = Academy::query()->where('slug', 'nibras')->firstOrFail();
    $this->actAsTenant($academy->toTenantRef());
    User::query()->where('email', 'khaled@nibras.example')->firstOrFail()->forceFill(['password' => 'a-long-password-1'])->save();
    $this->clearTenant();
    [$code, $out] = resendWelcome(welcomeInput());
    expect($code)->toBe(1)->and($out)->toContain('already chose its password');

    Mail::assertNothingSent();
});
