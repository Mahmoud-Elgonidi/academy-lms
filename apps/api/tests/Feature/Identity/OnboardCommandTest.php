<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Modules\SaveAcademyModules;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\AcademyModules;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\Feature;
use Symfony\Component\Console\Tester\CommandTester;

/*
 * A client academy opened by the platform team from GitHub (infra/deploy/
 * console.sh academy → platform:onboard): one JSON object on stdin, checked
 * whole before anything is made; the academy and its owner as the console
 * makes them, the plan's areas taken away, optional areas hidden, and the
 * owner's welcome by email. The link is never printed.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->clearTenant();
    SaasPlan::query()->create(['code' => 'starter', 'name_ar' => 'البداية', 'name_en' => 'Starter', 'features' => [], 'is_default' => true]);
    $this->growth = SaasPlan::query()->create(['code' => 'growth', 'name_ar' => 'النمو', 'name_en' => 'Growth', 'features' => ['crm', 'quran', 'payroll']]);
});

afterEach(fn () => AuditActor::forget());

/** @return array{0: int, 1: string} the exit code and what it printed */
function onboard(array|string $input): array
{
    $tester = new CommandTester(Artisan::all()['platform:onboard']);
    $tester->setInputs([is_string($input) ? $input : json_encode($input, JSON_UNESCAPED_UNICODE)]);
    $code = $tester->execute([], ['interactive' => false]);

    return [$code, $tester->getDisplay()];
}

function clientAcademy(array $overrides = []): array
{
    return $overrides + [
        'slug' => 'nibras', 'name_ar' => 'أكاديمية نبراس', 'name_en' => 'Nibras Academy',
        'country' => 'QA', 'currency' => 'QAR', 'timezone' => 'Asia/Qatar', 'locale' => 'ar',
        'owner_name' => 'خالد', 'owner_email' => 'Khaled@Nibras.example', 'owner_phone' => '55123456',
        'plan' => 'growth', 'features_off' => ['quran'], 'hidden' => ['games', 'messaging', 'evaluation'],
        'reason' => 'New client, signed contract',
    ];
}

it('opens an academy with its owner, its plan without the Quran, three areas hidden, and the welcome by email, never printing the link', function () {
    [$code, $out] = onboard(clientAcademy());

    expect($code)->toBe(0, $out)
        ->and($out)->toContain('nibras.lms.test')->toContain('growth')->toContain('quran')
        ->toContain('games, messaging, evaluation')->toContain('welcome sent to khaled@nibras.example')
        ->not->toContain('/welcome/')->not->toContain('signature')->not->toContain('expires=');

    $academy = Academy::query()->where('slug', 'nibras')->sole();
    expect($academy)->name_ar->toBe('أكاديمية نبراس')->base_currency->toBe('QAR')->default_timezone->toBe('Asia/Qatar')->country->toBe('QA');

    $current = SaasSubscription::query()->where('academy_id', $academy->id)->whereNull('ended_at')->sole();
    expect($current->plan_id)->toBe($this->growth->id)
        ->and($current->features_off)->toBe(['quran'])
        ->and($current->reason)->toBe('New client, signed contract');

    $this->actAsTenant($academy->toTenantRef());
    $owner = User::query()->sole();
    expect($owner->email)->toBe('khaled@nibras.example')
        ->and($owner->phone_e164)->toBe('+97455123456')
        ->and($owner->getRawOriginal('password'))->toBeNull()
        ->and(array_map(fn (Role $r) => $r->value, $owner->roles()))->toBe(['owner', 'admin'])
        ->and(AcademyModules::current()->hidden)->toEqualCanonicalizing(['games', 'messaging', 'evaluation'])
        ->and(Notification::query()->where('user_id', $owner->id)->where('template_key', 'people.welcome_staff')->sole()->body)
        ->toContain('/welcome/'.$owner->id);

    $actions = DB::table('audit_logs')->pluck('action')->all();
    expect($actions)->toContain('academy.created', 'academy.plan_changed', 'academy.modules_changed');
});

it('opens one with nothing taken away or hidden', function () {
    [$code, $out] = onboard(clientAcademy(['features_off' => [], 'hidden' => [], 'owner_phone' => null]));

    expect($code)->toBe(0, $out);
    $academy = Academy::query()->where('slug', 'nibras')->sole();
    expect(SaasSubscription::query()->where('academy_id', $academy->id)->sole()->plan_id)->toBe($this->growth->id);
});

it('refuses a taken address, a bad email or anything else wrong, with nothing made', function (array $input, string $says) {
    $this->createAcademy(['slug' => 'noor']);
    $this->clearTenant();
    $before = Academy::query()->count();

    [$code, $out] = onboard(clientAcademy($input));

    expect($code)->toBe(1)
        ->and($out)->toContain($says)
        ->and(Academy::query()->count())->toBe($before)
        ->and(Academy::query()->where('slug', 'nibras')->exists())->toBeFalse();
})->with([
    'a taken address' => [['slug' => 'noor'], 'slug'],
    'a bad email' => [['owner_email' => 'not-an-email'], 'owner_email'],
    'an unknown plan' => [['plan' => 'gold'], 'plan'],
    'a paid area hidden' => [['hidden' => ['quran']], 'Only optional modules'],
    'an unknown area off' => [['features_off' => ['teleport']], 'features_off'],
    'a bad address' => [['slug' => '-no'], 'slug'],
    'no reason' => [['reason' => ''], 'reason'],
    'a currency we do not bill in' => [['currency' => 'XYZ'], 'currency'],
    'a bad mobile' => [['owner_phone' => '12'], 'owner_phone'],
]);

it('wants one JSON object on stdin', function () {
    expect(onboard('not json')[0])->toBe(1)
        ->and(onboard('[1, 2]')[0])->toBe(1)
        ->and(Academy::query()->where('slug', 'nibras')->exists())->toBeFalse();
});

it('hides areas as the platform only from the server: never for someone signed in, never while signed in as someone', function () {
    $noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $owner = User::factory()->withRole(Role::Owner, Role::Admin)->create();

    $this->actingAs($owner);
    expect(fn () => app(SaveAcademyModules::class)->byPlatform(['games' => false]))->toThrow(AuthorizationException::class);
    auth()->guard('web')->forgetUser();

    AuditActor::use(new AuditActor('platform', '0190f0a0-0000-7000-8000-0000000000aa', 'Salma', $owner->id));
    expect(fn () => app(SaveAcademyModules::class)->byPlatform(['games' => false]))->toThrow(ValidationException::class)
        ->and(AcademyModules::current()->hidden)->toBe([]);

    AuditActor::forget();
    expect(app(SaveAcademyModules::class)->byPlatform(['games' => false]))->toBe([Feature::Games])
        ->and(DB::table('audit_logs')->where('action', 'academy.modules_changed')->value('actor_type'))->toBe('system');
});
