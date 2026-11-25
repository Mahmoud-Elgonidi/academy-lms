<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\OnboardAcademy;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Console\Pages\NewAcademy;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\SetAcademyAudience;
use Modules\Platform\Contracts\DTOs\NewAcademy as NewAcademyData;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Tenancy\AcademyAudience;
use Modules\SharedKernel\Tenancy\Audience;
use Symfony\Component\Console\Tester\CommandTester;

/*
 * Whom an academy teaches (docs/architecture/02, section 29): families by
 * default, adults only for companies training professionals. Chosen when it
 * is made (the console's wizard, for owners; platform:onboard), changed only
 * by an owner of the console through SetAcademyAudience, with a reason and
 * in the audit, and told to the apps in branding and me.
 */

beforeEach(function () {
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'name_ar' => 'أكاديمية النور', 'name_en' => 'Noor']));
    $this->student = User::factory()->withRole(Role::Student)->create(['email' => 'student@noor.test']);
    $this->clearTenant();
    $this->owner = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    $this->owner->forceFill(['is_owner' => true])->save();
    $this->owner->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
    $this->member = PlatformAdmin::query()->create(['name' => 'رامي', 'email' => 'rami@platform.test', 'password' => 'a-long-password']);
    $this->member->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
    AuditActor::use(new AuditActor('platform', $this->owner->id, $this->owner->name));
});

afterEach(fn () => AuditActor::forget());

it('keeps every academy teaching families until an owner says otherwise', function () {
    expect(Academy::query()->findOrFail($this->noor->id)->audience)->toBe(Audience::Families)
        ->and($this->noor->audience)->toBe(Audience::Families)
        ->and(DB::connection('pgsql_platform')->table('academies')->where('id', $this->noor->id)->value('audience'))->toBe('families');
    expect(fn () => DB::connection('pgsql_platform')->table('academies')->where('id', $this->noor->id)->update(['audience' => 'kids']))
        ->toThrow(QueryException::class);
});

it('changes whom an academy teaches only by an owner, with a reason, in both logs', function () {
    $set = app(SetAcademyAudience::class);

    expect(fn () => $set->handle($this->member, $this->noor->id, Audience::Adults, 'Signed contract'))->toThrow(AuthorizationException::class)
        ->and(fn () => $set->handle($this->owner, $this->noor->id, Audience::Adults, 'no'))->toThrow(ValidationException::class);
    $this->owner->forceFill(['is_active' => false])->save();
    expect(fn () => $set->handle($this->owner, $this->noor->id, Audience::Adults, 'Signed contract'))->toThrow(AuthorizationException::class);
    $this->owner->forceFill(['is_active' => true])->save();
    // Never while signed in as someone of an academy.
    AuditActor::use(new AuditActor('platform', $this->owner->id, $this->owner->name, onBehalfOf: $this->student->id, impersonationId: 'imp-1'));
    expect(fn () => $set->handle($this->owner, $this->noor->id, Audience::Adults, 'Signed contract'))->toThrow(AuthorizationException::class);
    AuditActor::use(new AuditActor('platform', $this->owner->id, $this->owner->name));
    expect(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.audience_changed')->count())->toBe(0);

    // Acting as the academy, the rest of the request sees it at once.
    $this->actAsTenant($this->noor);
    $set->handle($this->owner, $this->noor->id, Audience::Adults, ' HM-Trust: professionals only ');
    expect(app(AcademyAudience::class)->adultsOnly())->toBeTrue();
    $this->clearTenant();

    expect(Academy::query()->findOrFail($this->noor->id)->audience)->toBe(Audience::Adults);
    $row = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.audience_changed')->sole();
    expect($row->academy_id)->toBe($this->noor->id)->and($row->actor_type)->toBe('platform')
        ->and(json_decode($row->data, true))->toEqual(['academy_audience' => 'adults', 'previous_audience' => 'families', 'reason' => 'HM-Trust: professionals only']);
    app()->setLocale('en');
    expect(AuditEntries::describe($row, 'UTC')['title'])->toBe('The academy now teaches adults only (was families): HM-Trust: professionals only');

    // The same again changes nothing and writes nothing.
    $set->handle($this->owner, $this->noor->id, Audience::Adults, 'Signed contract');
    expect(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.audience_changed')->count())->toBe(1);
});

it('offers the change on the console\'s academy page to owners alone', function () {
    Filament::setCurrentPanel('console');

    $this->actingAs($this->member, 'platform');
    Livewire::test(AcademyOverview::class, ['academy' => $this->noor->id])->assertActionDoesNotExist('audience');

    $this->actingAs($this->owner, 'platform');
    Livewire::test(AcademyOverview::class, ['academy' => $this->noor->id])
        ->assertActionVisible('audience')
        ->callAction('audience', data: ['audience' => 'adults', 'reason' => 'x'])->assertHasActionErrors(['reason']);
    Livewire::test(AcademyOverview::class, ['academy' => $this->noor->id])
        ->callAction('audience', data: ['audience' => 'adults', 'reason' => 'Professionals only'])->assertHasNoActionErrors();

    expect(Academy::query()->findOrFail($this->noor->id)->audience)->toBe(Audience::Adults);
});

it('tells the apps whom the academy teaches, in branding and in me', function () {
    $this->getJson('http://noor.lms.test/api/v1/branding')->assertOk()->assertJsonPath('audience', 'families');
    $this->actAsTenant($this->noor);
    $this->actingAs(User::query()->findOrFail($this->student->id), 'sanctum')->withHeaders(['X-Academy' => 'noor'])
        ->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.academy.audience', 'families');
    $this->clearTenant();

    app(SetAcademyAudience::class)->handle($this->owner, $this->noor->id, Audience::Adults, 'Professionals only');
    app()->forgetScopedInstances();

    $this->getJson('http://noor.lms.test/api/v1/branding')->assertOk()->assertJsonPath('audience', 'adults');
    $this->actAsTenant(Academy::query()->findOrFail($this->noor->id)->toTenantRef());
    $this->actingAs(User::query()->findOrFail($this->student->id), 'sanctum')->withHeaders(['X-Academy' => 'noor'])
        ->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.academy.audience', 'adults');
});

it('makes an adults academy from the wizard only for an owner', function () {
    $fill = fn ($page, string $slug) => $page->set('data.name_ar', 'أكاديمية '.$slug)->set('data.name_en', $slug)->set('data.slug', $slug)
        ->set('data.owner_name', 'هاني')->set('data.owner_email', $slug.'@example.test');
    Filament::setCurrentPanel('console');

    $this->actingAs($this->owner, 'platform');
    $fill(Livewire::test(NewAcademy::class), 'hm-trust')->assertSee(__('identity::onboarding.fields.audience_hint'))->set('data.audience', 'adults')
        ->call('create')->assertHasNoFormErrors();
    expect(Academy::query()->where('slug', 'hm-trust')->sole()->audience)->toBe(Audience::Adults);

    // Someone else of the team makes families academies (the field is not theirs).
    $this->actingAs($this->member, 'platform');
    $fill(Livewire::test(NewAcademy::class), 'other-one')->assertDontSee(__('identity::onboarding.fields.audience_hint'))->set('data.audience', 'adults')
        ->call('create')->assertHasNoFormErrors();
    expect(Academy::query()->where('slug', 'other-one')->sole()->audience)->toBe(Audience::Families);

    $made = app(OnboardAcademy::class)->handle(new NewAcademyData('direct', 'مباشر', 'Direct', 'SAR', audience: Audience::Adults), 'هاني', 'h@direct.test');
    expect($made['academy']->audience)->toBe(Audience::Adults);

    // An adults academy starts with the games hidden; a families one with every area shown.
    $hidden = fn (string $slug) => json_decode((string) DB::connection('pgsql_platform')->table('academy_modules')
        ->where('academy_id', Academy::query()->where('slug', $slug)->value('id'))->value('hidden'), true) ?? [];
    expect($hidden('direct'))->toBe(['games'])->and($hidden('hm-trust'))->toBe(['games'])->and($hidden('other-one'))->toBe([]);
});

it('takes the audience from platform:onboard\'s JSON, families when left out', function () {
    $this->withoutDefer();
    AuditActor::forget();
    SaasPlan::query()->create(['code' => 'growth', 'name_ar' => 'النمو', 'name_en' => 'Growth', 'features' => [], 'is_default' => true]);
    $run = function (array $overrides): array {
        $tester = new CommandTester(Artisan::all()['platform:onboard']);
        $tester->setInputs([json_encode($overrides + [
            'name_ar' => 'أكاديمية', 'name_en' => 'Academy', 'country' => 'QA', 'currency' => 'QAR', 'timezone' => 'Asia/Qatar', 'locale' => 'ar',
            'owner_name' => 'خالد', 'plan' => 'growth', 'reason' => 'Signed contract',
        ], JSON_UNESCAPED_UNICODE)]);

        return [$tester->execute([], ['interactive' => false]), $tester->getDisplay()];
    };

    [$code, $out] = $run(['slug' => 'hm-trust', 'owner_email' => 'k@hm.example', 'audience' => 'adults']);
    expect($code)->toBe(0, $out)->and($out)->toContain('audience:      adults')
        ->and(Academy::query()->where('slug', 'hm-trust')->sole()->audience)->toBe(Audience::Adults);

    [$code] = $run(['slug' => 'plain', 'owner_email' => 'k@plain.example']);
    expect($code)->toBe(0)->and(Academy::query()->where('slug', 'plain')->sole()->audience)->toBe(Audience::Families);

    [$code, $out] = $run(['slug' => 'kids-only', 'owner_email' => 'k@kids.example', 'audience' => 'kids']);
    expect($code)->toBe(1)->and($out)->toContain('audience: The audience is families or adults.')
        ->and(Academy::query()->where('slug', 'kids-only')->exists())->toBeFalse();
});
