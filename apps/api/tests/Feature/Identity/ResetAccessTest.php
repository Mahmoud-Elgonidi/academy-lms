<?php

use Filament\Facades\Filament;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\Support\ResetAccess;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Console\Pages\SupportSignIn;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;
use Symfony\Component\Console\Tester\CommandTester;

/*
 * The platform team resets a back-office account's access (someone forgot
 * their password and lost their second step): password and two-step cleared,
 * sessions and tokens ended, audited without addresses, and one email at once
 * with a fresh link to choose a new password, copied to up to five addresses.
 * From the console (SupportSignIn) or the server (platform:welcome, "reset").
 */

beforeEach(function () {
    $this->withoutDefer();
    // A real mailer's name (log/array are refused), carried by the array transport here.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'array'], 'mail.from.address' => 'no-reply@platform.example']);
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->withTwoFactor()->create(['name' => 'هالة', 'email' => 'hala@noor.test', 'password' => 'a-long-password']);
    $this->owner->createToken('iPhone');
    $this->student = User::factory()->withRole(Role::Student)->create(['name' => 'Omar', 'email' => 'omar@noor.test', 'password' => 'a-long-password']);
    $this->minor = User::factory()->withRole(Role::Student)->create(['name' => 'Yusuf', 'email' => null, 'username' => 'yusuf', 'is_managed_minor' => true]);
    $this->clearTenant();
    $this->academy = app(TenantDirectory::class)->byId($this->noor->id);
    $this->admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    $this->ref = new PlatformAdminRef($this->admin->id, $this->admin->name, $this->admin->email);
    $this->sent = [];
    Event::listen(MessageSending::class, function (MessageSending $e) {
        $this->sent[] = $e->message;
    });
});

afterEach(fn () => AuditActor::forget());

const RESET_REASON = 'المالكة نسيت كلمة المرور وضاع هاتفها، وتحققنا منها بالهاتف.';

it('clears the password and two-step sign-in, ends tokens, audits without addresses, and sends one email with copies whose link then works', function () {
    $done = app(ResetAccess::class)->handle($this->academy, $this->owner->id, $this->ref, RESET_REASON, ['Amr@Partner.example', 'hala@noor.test', ' hazem@partner.example ']);

    expect($done)->toBe(['email' => 'hala@noor.test', 'copies' => 2, 'had_two_factor' => true]);

    $this->actAsTenant($this->noor);
    $owner = $this->owner->fresh();
    expect($owner->getRawOriginal('password'))->toBeNull()
        ->and($owner->getRawOriginal('remember_token'))->toBeNull()
        ->and($owner->getAppAuthenticationSecret())->toBeNull()
        ->and($owner->getRawOriginal('app_authentication_recovery_codes'))->toBeNull()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $owner->id)->exists())->toBeFalse();

    $row = DB::table('audit_logs')->where('action', 'identity.access_reset')->sole();
    expect($row->actor_type)->toBe('platform')->and($row->actor_name)->toBe('سلمى')
        ->and(json_decode($row->data, true))->toMatchArray(['reason' => RESET_REASON, 'had_two_factor' => true, 'copies' => 2])
        ->and($row->data)->not->toContain('partner.example')->not->toContain('hala@');
    // The platform team's step is one line: no "two-step turned off" line beside it.
    expect(DB::table('audit_logs')->where('action', 'identity.two_factor_disabled')->exists())->toBeFalse();

    expect($this->sent)->toHaveCount(1);
    $message = $this->sent[0];
    expect(array_map(fn ($a) => $a->getAddress(), $message->getTo()))->toBe(['hala@noor.test'])
        ->and(array_map(fn ($a) => $a->getAddress(), $message->getCc()))->toBe(['amr@partner.example', 'hazem@partner.example'])
        ->and($message->getSubject())->toBe(__('identity::onboarding.reset_mail.subject', ['academy' => $this->academy->nameAr], 'ar'))
        ->and($message->getHtmlBody())->toContain(e(__('identity::onboarding.reset_mail.button', [], 'ar')));

    preg_match('#href="([^"]+/welcome/[^"]+)"#', (string) $message->getHtmlBody(), $m);
    $link = html_entity_decode($m[1]);
    expect($link)->toContain('noor.lms.test')->toContain('/welcome/'.$owner->id);
    $this->clearTenant();
    $this->get('http://noor.lms.test'.parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY))
        ->assertOk()->assertSee('password_confirmation', false);
});

it('refuses while signed in as someone, a short reason, a managed minor, a family account, and bad copies, changing nothing', function () {
    $refused = function (callable $call, string $key) {
        try {
            $call();
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($key);

            return;
        }
        test()->fail('It should have been refused.');
    };
    $reset = fn (string $id, string $reason = RESET_REASON, array $cc = []) => app(ResetAccess::class)->handle($this->academy, $id, $this->ref, $reason, $cc);

    AuditActor::use(new AuditActor('platform', $this->ref->id, $this->ref->name, $this->owner->id));
    $refused(fn () => $reset($this->owner->id), 'user');
    AuditActor::forget();

    $refused(fn () => $reset($this->owner->id, 'نسيت'), 'reason');
    $refused(fn () => $reset($this->minor->id), 'user');
    $refused(fn () => $reset($this->student->id), 'user');
    $refused(fn () => $reset($this->owner->id, RESET_REASON, ['not an email']), 'cc');
    $refused(fn () => $reset($this->owner->id, RESET_REASON, ['a@x.example', 'A@x.example']), 'cc');
    $refused(fn () => $reset($this->owner->id, RESET_REASON, ['a@x.example', 'b@x.example', 'c@x.example', 'd@x.example', 'e@x.example', 'f@x.example']), 'cc');

    // No mail set up: refused before anything changes.
    config(['mail.default' => 'log']);
    $refused(fn () => $reset($this->owner->id), 'mail');

    $this->actAsTenant($this->noor);
    expect($this->owner->fresh()->getRawOriginal('password'))->not->toBeNull()
        ->and($this->owner->fresh()->getAppAuthenticationSecret())->not->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'identity.access_reset')->exists())->toBeFalse()
        ->and($this->sent)->toBe([]);
});

it('says the access was reset when the mailer refuses the email afterwards', function () {
    Mail::shouldReceive('html')->once()->andThrow(new RuntimeException('550 secret provider words'));

    try {
        app(ResetAccess::class)->handle($this->academy, $this->owner->id, $this->ref, RESET_REASON);
        test()->fail('It should have said the mail did not leave.');
    } catch (ValidationException $e) {
        expect($e->errors()['mail'][0])->toContain('RuntimeException')->not->toContain('secret provider');
    }

    $this->actAsTenant($this->noor);
    expect($this->owner->fresh()->getRawOriginal('password'))->toBeNull();
});

it('resets from the console with copies, as the platform admin signed in, and shows refusals', function () {
    $this->actingAs($this->admin, 'platform');
    Filament::setCurrentPanel('console');

    $page = Livewire::test(SupportSignIn::class)
        ->set('academy', $this->noor->id)->call('choose', $this->owner->id)
        ->assertSee(__('identity::impersonation.console.reset_access.button'));

    // The reason is checked before asking.
    $page->set('reason', 'قصير')->mountAction('resetAccess')->assertHasErrors(['reason'])->assertActionNotMounted('resetAccess');
    // A copy that is not an email: a danger notice, the modal stays open, nothing changed.
    $page->set('reason', RESET_REASON)
        ->callAction('resetAccess', ['cc' => 'not an email'])
        ->assertNotified(__('identity::onboarding.access.errors.cc_bad'))
        ->assertActionMounted('resetAccess');
    expect($this->sent)->toBe([]);

    $page->fillForm(['cc' => "amr@partner.example\n\nhazem@partner.example"])->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified(__('identity::impersonation.console.reset_access.done', ['email' => 'hala@noor.test']))
        ->assertSet('reason', '');

    expect($this->sent)->toHaveCount(1)
        ->and(array_map(fn ($a) => $a->getAddress(), $this->sent[0]->getCc()))->toBe(['amr@partner.example', 'hazem@partner.example']);
    $this->actAsTenant($this->noor);
    expect($this->owner->fresh()->getRawOriginal('password'))->toBeNull();
    $row = DB::table('audit_logs')->where('action', 'identity.access_reset')->sole();
    expect($row->actor_type)->toBe('platform')->and($row->actor_id)->toBe($this->admin->id);
    $this->clearTenant();

    // Not offered for a family account.
    Livewire::test(SupportSignIn::class)
        ->set('academy', $this->noor->id)->call('choose', $this->student->id)
        ->assertDontSee(__('identity::impersonation.console.reset_access.button'));
});

it('resets from the server through platform:welcome with "reset": true', function () {
    $run = function (array $input) {
        $tester = new CommandTester(Artisan::all()['platform:welcome']);
        $tester->setInputs([json_encode($input, JSON_UNESCAPED_UNICODE)]);

        return [$tester->execute([], ['interactive' => false]), $tester->getDisplay()];
    };
    $input = ['slug' => 'noor', 'email' => 'Hala@Noor.test', 'cc' => ['amr@partner.example'], 'reason' => RESET_REASON];

    // Without reset, an account that has a password is still refused.
    [$code, $out] = $run($input);
    expect($code)->toBe(1)->and($out)->toContain('already chose its password')->and($this->sent)->toBe([]);

    [$code, $out] = $run($input + ['reset' => true]);
    expect($code)->toBe(0, $out)
        ->and($out)->toContain('Access reset (password and two-step sign-in cleared')->toContain('hala@noor.test with 1 copy')
        ->and($out)->not->toContain('/welcome/')->not->toContain('signature');
    expect($this->sent)->toHaveCount(1);

    $this->actAsTenant($this->noor);
    expect($this->owner->fresh()->getRawOriginal('password'))->toBeNull();
    $row = DB::table('audit_logs')->where('action', 'identity.access_reset')->sole();
    expect($row->actor_type)->toBe('system')->and(json_decode($row->data, true)['copies'])->toBe(1);
    $this->clearTenant();

    // A family account is refused by the action, with its words.
    [$code, $out] = $run(['slug' => 'noor', 'email' => 'omar@noor.test', 'reason' => RESET_REASON, 'reset' => true]);
    expect($code)->toBe(1)->and($out)->toContain('back office');
});
