<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Communication\Models\Notification;
use Modules\Identity\Actions\Staff\ChangeStaffRoles;
use Modules\Identity\Actions\Staff\DeactivateStaff;
use Modules\Identity\Actions\Staff\InviteStaff;
use Modules\Identity\Actions\Staff\ReactivateStaff;
use Modules\Identity\Actions\Staff\SendStaffWelcome;
use Modules\Identity\Actions\Staff\StaffWelcomeLink;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\SignIn;
use Modules\Identity\Filament\Pages\Team;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Plans\PlanLimitReached;

/*
 * The academy's team (docs/architecture/02, section 19): the owner and
 * admins invite people with back-office roles, change them and turn
 * accounts off and on, each only up to their own rank, never their own
 * place, never while the platform team is signed in as someone; the
 * academy keeps an active owner, a teacher keeps teaching, and someone
 * turned off is signed out everywhere and cannot come back in.
 */

beforeEach(function () {
    $this->withoutDefer();
    CarbonImmutable::setTestNow('2026-10-13 08:00:00');
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Africa/Cairo']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Hala']);
    $this->admin = User::factory()->withRole(Role::Admin)->create(['name' => 'Amr']);
    app()->setLocale('en');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** @return list<string> */
function teamRoles(User|string $user): array
{
    return array_map(fn (Role $r) => $r->value, User::query()->findOrFail($user instanceof User ? $user->id : $user)->roles());
}

function teamRefusal(Closure $change): string
{
    try {
        $change();
    } catch (ValidationException $e) {
        return implode(' ', $e->validator->errors()->all());
    }
    test()->fail('The change should have been refused.');
}

it('invites someone with back-office roles, welcomed in the staff\'s words, audited with the roles, whatever the teacher limit', function () {
    // The plan's teacher limit is full: staff are not teachers.
    $this->clearTenant();
    $plan = SaasPlan::query()->create(['code' => 'small', 'name_ar' => 'صغيرة', 'name_en' => 'Small', 'max_teachers' => 1, 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->noor->id, $plan->id, 'For this test');
    $this->actAsTenant($this->noor);
    User::factory()->withRole(Role::Teacher)->create();
    expect(fn () => app(IdentityApi::class)->createUser(new NewUser(name: 'Second teacher', roles: [Role::Teacher], email: 't2@noor.test')))->toThrow(PlanLimitReached::class);

    $mona = app(InviteStaff::class)->handle(new UserDetails('Mona', 'mona@noor.test', '+201001112223', 'ar', 'Africa/Cairo'), [Role::Sales, Role::Accountant, Role::Sales], true, $this->owner->id);

    expect(teamRoles($mona))->toBe(['accountant', 'sales'])
        ->and($mona->fresh()->getRawOriginal('password'))->toBeNull()
        ->and(Notification::query()->where('user_id', $mona->id)->where('template_key', 'people.welcome_staff')->sole()->body)->toContain('/welcome/'.$mona->id);

    $line = DB::table('audit_logs')->where('action', 'identity.staff_invited')->sole();
    expect(json_decode($line->data, true))->toEqual(['name' => 'Mona', 'roles' => ['accountant', 'sales']])
        ->and($line->data)->not->toContain('mona@')
        ->and(AuditEntries::describe($line, 'UTC')['title'])->toBe('Invited Mona to the team as Accountant, Sales');
    app()->setLocale('ar');
    expect(AuditEntries::describe($line, 'UTC')['title'])->toBe('دعوة Mona للفريق بدور: محاسب، مبيعات');
    app()->setLocale('en');

    // Teacher, student and guardian are People's; an address already here is refused.
    expect(teamRefusal(fn () => app(InviteStaff::class)->handle(new UserDetails('Tarek', 'tarek@noor.test'), [Role::Teacher], true, $this->owner->id)))->toBe(__('identity::team.errors.not_staff_role'))
        ->and(fn () => app(InviteStaff::class)->handle(new UserDetails('Mona 2', 'mona@noor.test'), [Role::Sales], true, $this->owner->id))->toThrow(ValidationException::class);
});

it('keeps an admin below the owner: no owner made or touched, while admins and the rest are theirs', function () {
    $sales = User::factory()->withRole(Role::Sales)->create(['name' => 'Sami']);

    expect(teamRefusal(fn () => app(InviteStaff::class)->handle(new UserDetails('Nader', 'nader@noor.test'), [Role::Owner], false, $this->admin->id)))->toBe(__('identity::team.errors.above_you'))
        ->and(teamRefusal(fn () => app(ChangeStaffRoles::class)->handle($sales->id, [Role::Owner], $this->admin->id)))->toBe(__('identity::team.errors.above_you'))
        ->and(teamRefusal(fn () => app(ChangeStaffRoles::class)->handle($this->owner->id, [Role::Owner, Role::Accountant], $this->admin->id)))->toBe(__('identity::team.errors.touch_above'))
        ->and(teamRefusal(fn () => app(DeactivateStaff::class)->handle($this->owner->id, $this->admin->id)))->toBe(__('identity::team.errors.touch_above'));

    app(ChangeStaffRoles::class)->handle($sales->id, [Role::Admin, Role::Sales], $this->admin->id);
    expect(teamRoles($sales))->toBe(['admin', 'sales']);
    $line = DB::table('audit_logs')->where('action', 'identity.staff_roles_changed')->sole();
    expect(json_decode($line->data, true))->toEqual(['name' => 'Sami', 'roles' => ['admin', 'sales'], 'added' => ['admin'], 'removed' => []]);

    // Nobody without the team in their hands changes it.
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    expect(fn () => app(InviteStaff::class)->handle(new UserDetails('Nader', 'nader@noor.test'), [Role::Sales], false, $supervisor->id))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeStaffRoles::class)->handle($sales->id, [Role::Sales], $supervisor->id))->toThrow(AuthorizationException::class);
});

it('never changes one\'s own place, and always keeps an active owner', function () {
    expect(teamRefusal(fn () => app(ChangeStaffRoles::class)->handle($this->owner->id, [Role::Admin], $this->owner->id)))->toBe(__('identity::team.errors.self'))
        ->and(teamRefusal(fn () => app(DeactivateStaff::class)->handle($this->owner->id, $this->owner->id)))->toBe(__('identity::team.errors.self'))
        ->and(teamRefusal(fn () => app(ChangeStaffRoles::class)->handle($this->admin->id, [], $this->owner->id)))->toBe(__('identity::team.errors.roles_required'));

    // Handing the academy over: Amr becomes owner, then takes Hala's ownership; she is an admin now and cannot touch him.
    app(ChangeStaffRoles::class)->handle($this->admin->id, [Role::Owner, Role::Admin], $this->owner->id);
    app(ChangeStaffRoles::class)->handle($this->owner->id, [Role::Admin], $this->admin->id);
    expect(teamRoles($this->owner))->toBe(['admin'])->and(teamRoles($this->admin))->toBe(['owner', 'admin'])
        ->and(teamRefusal(fn () => app(ChangeStaffRoles::class)->handle($this->admin->id, [Role::Admin], $this->owner->id)))->toBe(__('identity::team.errors.touch_above'))
        ->and(teamRefusal(fn () => app(ChangeStaffRoles::class)->handle($this->admin->id, [Role::Admin], $this->admin->id)))->toBe(__('identity::team.errors.self'));

    // A second owner may be turned off by the first, never the other way round once off.
    $partner = User::factory()->withRole(Role::Owner)->create(['name' => 'Partner']);
    app(DeactivateStaff::class)->handle($partner->id, $this->admin->id);
    expect(fn () => app(ReactivateStaff::class)->handle($this->admin->id, $partner->id))->toThrow(AuthorizationException::class);
    $activeOwners = fn () => User::query()->where('status', 'active')->whereHas('roleRecords', fn ($q) => $q->where('role', 'owner'))->pluck('name')->all();
    expect($activeOwners())->toBe(['Amr']);
});

it('turns a team member off, signed out of the panel and the apps with no way back in, and on again', function () {
    $password = Str::random(20);
    $sami = User::factory()->withRole(Role::Sales)->create(['name' => 'Sami', 'email' => 'sami@noor.test', 'password' => $password]);
    $sami->createToken('phone');
    config(['session.driver' => 'database']);
    DB::table('http_sessions')->insert(['id' => Str::random(40), 'user_id' => $sami->id, 'payload' => '', 'last_activity' => time()]);
    $remember = $sami->fresh()->getRememberToken();

    app(DeactivateStaff::class)->handle($sami->id, $this->admin->id);
    config(['session.driver' => 'array']);
    expect($sami->fresh()->isActive())->toBeFalse()
        ->and($sami->tokens()->count())->toBe(0)
        ->and(DB::table('http_sessions')->where('user_id', $sami->id)->exists())->toBeFalse()
        ->and($sami->fresh()->getRememberToken())->not->toBe($remember)
        ->and(json_decode(DB::table('audit_logs')->where('action', 'identity.staff_deactivated')->sole()->data, true))->toEqual(['name' => 'Sami', 'roles' => ['sales']]);

    // No way in: the apps, the panel's sign-in, and a session from before.
    $this->clearTenant();
    $apiSignIn = fn () => $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/login', ['login' => 'sami@noor.test', 'password' => $password, 'device_name' => 'Phone']);
    $apiSignIn()->assertUnprocessable();
    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('academy');
    Livewire::test(SignIn::class)->fillForm(['email' => 'sami@noor.test', 'password' => $password])->call('authenticate')->assertHasErrors();
    expect(Auth::guard('web')->check())->toBeFalse();
    $this->actingAs($sami->fresh())->get('http://noor.lms.test/admin/leads')->assertForbidden();

    // Back on: in again, with the same roles; signing in is remembered for the Team screen.
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actAsTenant($this->noor);
    app(ReactivateStaff::class)->handle($sami->id, $this->admin->id);
    expect(teamRoles($sami))->toBe(['sales'])->and(DB::table('audit_logs')->where('action', 'identity.staff_reactivated')->count())->toBe(1);
    $this->clearTenant();
    $apiSignIn()->assertOk();
    $this->actAsTenant($this->noor);
    expect($sami->fresh()->last_sign_in_at?->toDateTimeString())->toBe('2026-10-13 08:00:00');

    // Someone who also teaches is turned off from People's screens instead.
    $khaled = User::factory()->withRole(Role::Teacher, Role::Supervisor)->create();
    expect(teamRefusal(fn () => app(DeactivateStaff::class)->handle($khaled->id, $this->owner->id)))->toBe(__('identity::team.errors.also_other'));
});

it('refuses every change to the team while the platform team is signed in as someone', function () {
    $sales = User::factory()->withRole(Role::Sales)->create(['password' => null]);
    AuditActor::use(new AuditActor('platform', '0190f0a0-0000-7000-8000-0000000000aa', 'Salma', $this->owner->id));
    $refused = __('identity::team.errors.impersonating');

    expect(teamRefusal(fn () => app(InviteStaff::class)->handle(new UserDetails('Nader', 'nader@noor.test'), [Role::Sales], true, $this->owner->id)))->toBe($refused)
        ->and(teamRefusal(fn () => app(ChangeStaffRoles::class)->handle($sales->id, [Role::Admin], $this->owner->id)))->toBe($refused)
        ->and(teamRefusal(fn () => app(DeactivateStaff::class)->handle($sales->id, $this->owner->id)))->toBe($refused)
        ->and(teamRefusal(fn () => app(ReactivateStaff::class)->handle($sales->id, $this->owner->id)))->toBe($refused)
        ->and(teamRefusal(fn () => app(StaffWelcomeLink::class)->handle($sales->id, $this->owner->id)))->toBe($refused)
        ->and(teamRefusal(fn () => app(SendStaffWelcome::class)->handle($sales->id, $this->owner->id)))->toBe($refused)
        ->and(User::query()->where('email', 'nader@noor.test')->exists())->toBeFalse()
        ->and(teamRoles($sales))->toBe(['sales'])
        ->and(DB::table('audit_logs')->where('action', 'like', 'identity.%')->exists())->toBeFalse();
});

it('gives a new admin their welcome link to copy (audited, never the link), and leads them to set up two-step sign-in', function () {
    $nour = app(InviteStaff::class)->handle(new UserDetails('Nour', 'nour@noor.test', null, 'en', 'Africa/Cairo'), [Role::Admin], false, $this->owner->id);
    expect(Notification::query()->where('user_id', $nour->id)->exists())->toBeFalse();

    $link = app(StaffWelcomeLink::class)->handle($nour->id, $this->owner->id);
    $shown = DB::table('audit_logs')->where('action', 'identity.welcome_link_shown')->sole();
    expect($link)->toContain('noor.lms.test')->toContain('/welcome/'.$nour->id.'?')
        ->and(json_decode($shown->data, true))->toEqual(['name' => 'Nour'])
        ->and($shown->data)->not->toContain('signature');

    // Sent again on request (at most once a minute), to the same person.
    app(SendStaffWelcome::class)->handle($nour->id, $this->owner->id);
    app(SendStaffWelcome::class)->handle($nour->id, $this->owner->id);
    $this->travel(2)->minutes();
    app(SendStaffWelcome::class)->handle($nour->id, $this->owner->id);
    expect(Notification::query()->where('user_id', $nour->id)->where('template_key', 'people.welcome_staff')->count())->toBe(2);

    // The link: a password, signed in, then straight to setting up two-step sign-in, not stuck.
    $this->clearTenant();
    $this->get('http://noor.lms.test'.parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY))->assertOk()->assertSee('Nour');
    $password = Str::random(20);
    $store = URL::temporarySignedRoute('identity.welcome.store', now()->addMinutes(30), ['user' => $nour->id], absolute: false);
    $this->post('http://noor.lms.test'.$store, ['password' => $password, 'password_confirmation' => $password])->assertRedirect();
    $setUp = 'http://noor.lms.test/admin/multi-factor-authentication/set-up';
    $this->get('http://noor.lms.test/admin')->assertRedirect($setUp);
    $this->get($setUp)->assertOk();

    $this->actAsTenant($this->noor);
    expect($nour->fresh()->last_sign_in_at)->not->toBeNull()
        ->and(teamRefusal(fn () => app(StaffWelcomeLink::class)->handle($nour->id, $this->owner->id)))->toBe(__('identity::team.errors.has_password'))
        ->and(teamRefusal(fn () => app(StaffWelcomeLink::class)->handle($this->owner->id, $this->admin->id)))->toBe(__('identity::team.errors.touch_above'));
});

it('opens the Team screen to the owner and admins only', function () {
    Filament::setCurrentPanel('academy');
    foreach ([$this->owner, $this->admin] as $who) {
        $this->actAsTenant($this->noor);   // a request ends the one before
        $this->flushSession();             // and its session belongs to the last person
        $this->actingAs($who)->get('http://noor.lms.test/admin/settings/team')->assertOk()->assertSee('Hala')->assertSee('Amr');
    }
    foreach ([Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales, Role::Teacher] as $role) {
        $this->actAsTenant($this->noor);   // a request ends the one before
        $this->flushSession();
        $this->actingAs(User::factory()->withRole($role)->create());
        $this->get('http://noor.lms.test/admin/settings/team')->assertForbidden();
    }
});

it('invites, changes roles and turns accounts off and on from the screen, offering only what one may do', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    $khaled = User::factory()->withRole(Role::Teacher)->create(['name' => 'Khaled']);

    $page = Livewire::test(Team::class)
        ->assertCanSeeTableRecords([$this->owner->id, $this->admin->id])
        ->assertCanNotSeeTableRecords([$khaled->id])
        ->assertSee('Two-step sign-in')
        ->callAction('invite', data: ['who' => 'new', 'name' => 'Mona', 'email' => 'mona@noor.test', 'phone' => '010 1234 5678',
            'timezone' => 'Africa/Cairo', 'locale' => 'ar', 'roles' => ['accountant'], 'welcome' => true])
        ->assertHasNoActionErrors();
    $mona = User::query()->where('email', 'mona@noor.test')->sole();
    expect($mona->phone_e164)->toBe('+201012345678')->and(teamRoles($mona))->toBe(['accountant'])
        ->and(Notification::query()->where('user_id', $mona->id)->where('template_key', 'people.welcome_staff')->exists())->toBeTrue();

    // No welcome: their link opens at once, to pass on by hand.
    $page->callAction('invite', data: ['who' => 'new', 'name' => 'Rana', 'email' => 'rana@noor.test', 'timezone' => 'Africa/Cairo', 'locale' => 'en', 'roles' => ['scheduler'], 'welcome' => false]);
    $rana = User::query()->where('email', 'rana@noor.test')->sole();
    expect($page->instance()->mountedActions[0]['name'] ?? null)->toBe('link')
        ->and((string) ($page->instance()->mountedActions[0]['data']['link'] ?? ''))->toContain('/welcome/'.$rana->id.'?')
        ->and(Notification::query()->where('user_id', $rana->id)->exists())->toBeFalse();

    // A teacher joins the team and keeps teaching; they are turned off from People's screens, not here.
    $page = Livewire::test(Team::class)
        ->callAction('invite', data: ['who' => 'teacher', 'teacher_id' => $khaled->id, 'roles' => ['supervisor']])
        ->assertHasNoActionErrors()
        ->assertCanSeeTableRecords([$khaled->id])
        ->assertTableActionHidden('deactivate', $khaled->id);
    expect(teamRoles($khaled))->toBe(['supervisor', 'teacher']);

    $page->callTableAction('roles', $mona->id, ['roles' => ['accountant', 'sales']])->assertHasNoTableActionErrors();
    expect(teamRoles($mona))->toBe(['accountant', 'sales']);
    $page->callTableAction('deactivate', $mona->id);
    expect($mona->fresh()->isActive())->toBeFalse();
    $page->assertTableActionHidden('deactivate', $mona->id)->callTableAction('reactivate', $mona->id);
    expect($mona->fresh()->isActive())->toBeTrue();

    // Not on oneself; and an admin is offered nothing on the owner, nor the owner's role.
    $page->assertTableActionHidden('roles', $this->owner->id)->assertTableActionHidden('deactivate', $this->owner->id);
    $this->actingAs($this->admin);
    Livewire::test(Team::class)
        ->assertTableActionHidden('roles', $this->owner->id)
        ->assertTableActionHidden('deactivate', $this->owner->id)
        ->assertTableActionVisible('roles', $mona->id)
        ->callAction('invite', data: ['who' => 'new', 'name' => 'Nader', 'email' => 'nader@noor.test', 'timezone' => 'Africa/Cairo', 'locale' => 'ar', 'roles' => ['owner'], 'welcome' => false]);
    expect(User::query()->where('email', 'nader@noor.test')->exists())->toBeFalse();
});
