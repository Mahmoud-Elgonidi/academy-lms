<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Announcements\Actions\SendAnnouncement;
use Modules\Announcements\Domain\Audience;
use Modules\Announcements\Filament\Pages\Announcements;
use Modules\Communication\Models\Notification;
use Modules\Curriculum\Filament\Programs\ProgramResource;
use Modules\Evaluation\Actions\RequestLessonRating;
use Modules\Evaluation\Filament\Pages\TeachersPerformance;
use Modules\Evaluation\Filament\Teacher\MyPerformance;
use Modules\Games\Filament\Pages\GameSettingsPage;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Filament\Family\FamilyJourney;
use Modules\Learning\Filament\Pages\ReportTemplates;
use Modules\Messaging\Actions\OpenConversation;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\Events\MessageSent;
use Modules\Messaging\Filament\Family\FamilyMessages;
use Modules\Messaging\Filament\Pages\Conversations;
use Modules\Messaging\Filament\Teacher\TeacherMessages;
use Modules\Messaging\Listeners\SendMessagingNotifications;
use Modules\People\Filament\Teacher\MyCard;
use Modules\Platform\Actions\Modules\SaveAcademyModules;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Domain\Modules\ModuleRules;
use Modules\Platform\Filament\Pages\AcademyModulesPage;
use Modules\Platform\Models\AcademyModules;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Platform\Setup\ModulesStep;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Filament\Pages\Groups;
use Modules\Scheduling\Filament\Pages\GroupSettings;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Filament\Pages\SiteRequests;
use Modules\Site\Filament\Pages\SiteSettings as SiteSettingsPage;
use Modules\Site\Models\SiteSettings;
use Modules\Support\Actions\FollowUpTickets;
use Modules\Support\Actions\OpenTicket;
use Modules\Support\Filament\Family\FamilyHelp;
use Modules\Support\Filament\Pages\Tickets;
use Modules\Support\Filament\Teacher\MySupport;
use Tests\Support\EnrolledStudent;
use Tests\Support\GroupLessons;

uses(EnrolledStudent::class, GroupLessons::class);

/*
 * The academy's modules (docs/architecture/02, "وحدات الأكاديمية"): the
 * owner and admins hide or show the optional areas; a hidden area leaves
 * every screen and menu, its API answers 403 feature-not-in-plan (the
 * public page 404), and it sends nothing; shown again, everything is back.
 * Paid areas stay the platform team's. The fixture: EnrolledStudent's
 * academy (an adult London student with a guardian, a Cairo teacher).
 */

afterEach(fn () => CarbonImmutable::setTestNow());

function modules(array $on, ?User $by = null): array
{
    // A request before may have left no academy behind.
    test()->actAsTenant(test()->academy);
    test()->actingAs($by ?? test()->admin);

    return app(SaveAcademyModules::class)->handle($on, (string) ($by ?? test()->admin)->id);
}

function moduleApi(User $user)
{
    test()->actAsTenant(test()->academy);

    return test()->actingAs(User::query()->findOrFail($user->id), 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'X-Timezone' => 'Africa/Cairo']);
}

it('hides each optional area from its screens and its API, and brings everything back', function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::defaults()]);
    $session = (string) DB::table('sessions')->orderBy('starts_at')->value('id');

    // Each area: who opens its screens, and an API route of it.
    $areas = [
        'groups' => fn () => [Groups::canAccess(), GroupSettings::canAccess()],
        'curriculum' => fn () => [ProgramResource::canAccess()],
        'games' => fn () => [GameSettingsPage::canAccess()],
        'messaging' => fn () => [Conversations::canAccess()],
        'announcements' => fn () => [Announcements::canAccess()],
        'support' => fn () => [Tickets::canAccess()],
        'evaluation' => fn () => [TeachersPerformance::canAccess()],
        'site' => fn () => [SiteSettingsPage::canAccess(), SiteRequests::canAccess()],
    ];
    $routes = [
        'curriculum' => [$this->student, 'get', '/api/v1/learning/journey/map'],
        'games' => [$this->student, 'get', '/api/v1/games/world'],
        'messaging' => [$this->teacher, 'get', '/api/v1/messages/conversations'],
        'support' => [$guardian, 'get', '/api/v1/support/tickets'],
        'evaluation' => [$guardian, 'get', "/api/v1/lessons/{$session}/rating"],
    ];

    foreach ($areas as $key => $screens) {
        $this->actingAs($this->admin);
        expect($screens())->each->toBeTrue($key.' is on at first');

        modules([$key => false]);
        expect(app(AcademyPlan::class)->has(Feature::from($key)))->toBeFalse($key);
        $this->actingAs($this->admin);
        expect($screens())->each->toBeFalse($key.' leaves its screens');
        if (isset($routes[$key])) {
            [$who, $method, $uri] = $routes[$key];
            moduleApi($who)->{$method.'Json'}($uri)->assertForbidden()
                ->assertJsonPath('type', 'feature-not-in-plan')->assertJsonPath('feature', $key)->assertJsonPath('by', 'academy');
        }

        modules([$key => true]);
        $this->actingAs($this->admin);
        expect($screens())->each->toBeTrue($key.' comes back');
        if (isset($routes[$key])) {
            [$who, $method, $uri] = $routes[$key];
            expect(moduleApi($who)->{$method.'Json'}($uri)->status())->not->toBe(403, $key);
        }
    }

    // The family's and the teacher's own screens follow.
    modules(['messaging' => false, 'support' => false, 'site' => false, 'curriculum' => false, 'evaluation' => false]);
    $this->actingAs($guardian);
    expect(FamilyMessages::canAccess())->toBeFalse()->and(FamilyHelp::canAccess())->toBeFalse()->and(FamilyJourney::canAccess())->toBeFalse();
    $this->actingAs($this->teacher);
    expect(TeacherMessages::canAccess())->toBeFalse()->and(MySupport::canAccess())->toBeFalse()->and(MyCard::canAccess())->toBeFalse()
        ->and(MyPerformance::canAccess())->toBeFalse();
    moduleApi($this->teacher)->getJson('/api/v1/teacher/card')->assertForbidden()->assertJsonPath('type', 'feature-not-in-plan');
    moduleApi($this->teacher)->getJson('/api/v1/teacher/performance')->assertForbidden()->assertJsonPath('type', 'feature-not-in-plan');
    // The public page answers as if it were off; what it asked is kept.
    $this->getJson('http://noor.lms.test/api/v1/site')->assertNotFound();
    $this->postJson('http://noor.lms.test/api/v1/site/requests', ['kind' => 'trial'])->assertNotFound();
    // Report questions are the reports', which every academy has.
    $this->actAsTenant($this->academy);
    $this->actingAs($supervisor);
    expect(ReportTemplates::canAccess())->toBeTrue();

    modules(['messaging' => true, 'support' => true, 'site' => true, 'curriculum' => true, 'evaluation' => true]);
    $this->actingAs($guardian);
    expect(FamilyMessages::canAccess())->toBeTrue()->and(FamilyJourney::canAccess())->toBeTrue();
    $this->getJson('http://noor.lms.test/api/v1/site')->assertOk();
});

it('takes a hidden area out of the menu, and puts it back', function () {
    $this->enrolStudentOnPlan();
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    // Each request is a process of its own in production; here each one starts from a fresh application too (Filament keeps its menu).
    $menu = function () use ($supervisor) {
        $this->refreshApplication();

        return $this->actingAs($supervisor)->get('http://noor.lms.test/admin/disputes')->assertOk();
    };

    $menu()->assertSee('/admin/conversations', false)->assertSee('/admin/support', false)->assertSee('/admin/curricula', false)
        ->assertSee(__('shared::chrome.nav_groups.communication'));
    modules(['messaging' => false, 'support' => false, 'curriculum' => false]);
    $menu()->assertDontSee('/admin/conversations', false)->assertDontSee('/admin/support', false)->assertDontSee('/admin/curricula', false)
        ->assertDontSee('/admin/games', false);
    $this->actingAs($supervisor)->get('http://noor.lms.test/admin/conversations')->assertForbidden();
    modules(['messaging' => true, 'support' => true, 'curriculum' => true]);
    $menu()->assertSee('/admin/conversations', false)->assertSee('/admin/curricula', false);
});

it('sends nothing for a hidden area', function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->actingAs($this->teacher);
    $conversation = app(OpenConversation::class)->handle($this->teacher->id, $this->teacher->id, $this->student->id);
    $message = app(SendMessage::class)->handle($conversation, $this->teacher->id, 'See you on Tuesday.');
    $told = fn () => Notification::query()->where('template_key', 'messaging.new_message')->count();
    $before = $told();
    expect($before)->toBeGreaterThan(0);

    modules(['messaging' => false, 'announcements' => false, 'evaluation' => false, 'support' => false]);
    // Messages: no new one, and a message sent just before is told of no more.
    expect(fn () => app(SendMessage::class)->handle($conversation->refresh(), $this->teacher->id, 'Hello'))->toThrow(FeatureNotInPlan::class);
    app(SendMessagingNotifications::class)->sent(new MessageSent($this->academy->id, $conversation->id, $message->id));
    expect($told())->toBe($before);
    // Announcements: none written.
    expect(fn () => app(SendAnnouncement::class)->handle($this->admin->id, 'Eid', 'No lessons on Eid.', Audience::of('families')))->toThrow(FeatureNotInPlan::class);
    expect(Notification::query()->where('template_key', 'like', 'announcements.%')->count())->toBe(0);
    // Ratings: nobody is asked; help: no ticket, no follow-up.
    expect(app(RequestLessonRating::class)->handle((string) DB::table('sessions')->value('id'), 'completed'))->toBeFalse()
        ->and(app(FollowUpTickets::class)->handle())->toBe(0)
        ->and(fn () => app(OpenTicket::class)->handle($guardian->id, 'family', 'technical', 'Sound', 'No sound.', $this->student->id))->toThrow(FeatureNotInPlan::class);

    // Back on, the conversation goes on where it was.
    modules(['messaging' => true]);
    expect(app(SendMessage::class)->handle($conversation->refresh(), $this->teacher->id, 'Hello again')->body)->toBe('Hello again');
});

it('refuses to hide help while a ticket is open, and says how many', function () {
    $this->enrolStudentOnPlan();
    $guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    app(OpenTicket::class)->handle($guardian->id, 'family', 'technical', 'Sound', 'No sound in the lesson.', $this->student->id);
    app()->setLocale('en');

    $why = fn (array $on) => rescue(fn () => modules($on), fn (ValidationException $e) => $e->errors()['modules'][0] ?? null, false);
    expect($why(['support' => false]))->toBe('One help request is open. Answer or close it first, then switch “Help and complaints” off.')
        ->and(AcademyModules::query()->exists())->toBeFalse();
    // The preview says it too, before anyone confirms.
    expect(app(SaveAcademyModules::class)->preview(Feature::Support, false)['refusals'])->toHaveCount(1);
    app()->setLocale('ar');
    expect(app(SaveAcademyModules::class)->preview(Feature::Support, false)['refusals'][0])->toContain('طلب مساعدة واحد مفتوح');
});

it('refuses to hide group lessons while a group has students in running courses', function () {
    $this->setUpGroups();
    $this->otherGroup();
    $this->actingAs($this->admin);
    app()->setLocale('en');

    expect(fn () => modules(['groups' => false]))->toThrow(ValidationException::class, 'One group has students in running courses. End it or move its students to private lessons first, then switch “Group lessons” off.');
    expect(app(AcademyPlan::class)->has(Feature::Groups))->toBeTrue();
});

it('hides the games with the curriculum, and never shows the games without it', function () {
    $this->enrolStudentOnPlan();
    $this->actingAs($this->admin);

    $preview = app(SaveAcademyModules::class)->preview(Feature::Curriculum, false);
    expect($preview['also'])->toBe([Feature::Games->label()])->and($preview['refusals'])->toBe([]);

    expect(modules(['curriculum' => false]))->toBe([Feature::Curriculum, Feature::Games])
        ->and(app(AcademyPlan::class)->has(Feature::Games))->toBeFalse();
    app()->setLocale('en');
    expect(fn () => modules(['games' => true]))->toThrow(ValidationException::class, 'Switch on “Curricula');

    // Both at once is fine; the curriculum alone leaves the games as they were (hidden).
    expect(modules(['curriculum' => true]))->toBe([Feature::Games])
        ->and(modules(['games' => true]))->toBe([]);

    // The rule itself, pure.
    $decision = ModuleRules::decide([Feature::Curriculum, Feature::Games], ['games' => true, 'curriculum' => true], []);
    expect($decision->allowed())->toBeTrue()->and($decision->hidden)->toBe([]);
});

it('lets only the owner and admins choose, never a paid area, never while signed in as someone, and audits names only', function () {
    $this->enrolStudentOnPlan();
    $owner = User::factory()->withRole(Role::Owner)->create();
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    $accountant = User::factory()->withRole(Role::Accountant)->create();

    foreach ([$supervisor, $accountant, $this->teacher] as $user) {
        $this->actingAs($user);
        expect(AcademyModulesPage::canAccess())->toBeFalse();
        expect(fn () => app(SaveAcademyModules::class)->handle(['games' => false], $user->id))->toThrow(AuthorizationException::class);
    }
    $this->actingAs($supervisor)->get('http://noor.lms.test/admin/settings/modules')->assertForbidden();
    $this->actAsTenant($this->academy);

    $this->actingAs($owner);
    expect(AcademyModulesPage::canAccess())->toBeTrue();
    app()->setLocale('en');
    expect(fn () => modules(['crm' => false], $owner))->toThrow(ValidationException::class, 'is a plan module');

    // Signed in as the owner by the platform team: refused.
    AuditActor::use(new AuditActor('platform', (string) Str::uuid(), 'Platform admin', $owner->id));
    expect(fn () => modules(['games' => false], $owner))->toThrow(ValidationException::class, 'signed in as someone else');
    AuditActor::forget();

    expect(modules(['games' => false, 'site' => false], $owner))->toBe([Feature::Games, Feature::Site]);
    $row = DB::table('audit_logs')->where('action', 'academy.modules_changed')->sole();
    expect(json_decode($row->data, true))->toEqual(['modules_hidden' => ['games', 'site'], 'modules_shown' => []]);
    modules(['games' => true], $owner);
    expect(DB::table('audit_logs')->where('action', 'academy.modules_changed')->pluck('data')->map(fn ($d) => json_decode($d, true))->all())
        ->toContain(['modules_shown' => ['games'], 'modules_hidden' => []]);   // jsonb keeps its keys shortest first
});

it('shows the three kinds on its page, switches one after its confirmation, and the menu follows', function () {
    $this->enrolStudentOnPlan();
    $this->actingAs($this->admin);
    app()->setLocale('ar');

    $this->get('http://noor.lms.test/admin/settings/modules')->assertOk()
        ->assertSee(__('platform::modules.sections.core.title'))->assertSee(__('platform::modules.never_off'))
        ->assertSee(Feature::Groups->label())->assertSee(Feature::Crm->label())->assertSee('/admin/groups', false);

    $this->actAsTenant($this->academy);
    Livewire::test(AcademyModulesPage::class)
        ->callAction('toggle', arguments: ['feature' => 'groups', 'on' => false])->assertHasNoActionErrors();
    expect(AcademyModules::current()->hiddenFeatures())->toBe([Feature::Groups]);
    $this->refreshApplication();
    $this->actingAs($this->admin);
    $this->get('http://noor.lms.test/admin/settings/modules')->assertOk()->assertDontSee('/admin/groups', false);
    $this->actAsTenant($this->academy);
    expect(app(ModulesStep::class)->done())->toBeTrue();
    // Find a teacher has no group mode now.
    expect(FindTeacher::groupsOn())->toBeFalse();
});

it('hides an optional area the platform team took away, which the academy cannot bring back', function () {
    $this->enrolStudentOnPlan();
    $this->clearTenant();
    $plan = SaasPlan::query()->create(['code' => 'full', 'name_ar' => 'كاملة', 'name_en' => 'Full', 'features' => array_column(Feature::cases(), 'value')]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test', [], [], ['messaging']);
    $this->actAsTenant($this->academy);
    app(PlatformAcademyPlan::class)->forget();
    $this->actingAs($this->admin);

    expect(app(AcademyPlan::class)->has(Feature::Messaging))->toBeFalse()->and(Conversations::canAccess())->toBeFalse()
        ->and(app(AcademyPlan::class)->has(Feature::Games))->toBeTrue();
    moduleApi($this->teacher)->getJson('/api/v1/messages/conversations')->assertForbidden()->assertJsonPath('by', 'plan');
    $this->actAsTenant($this->academy);
    app()->setLocale('en');
    expect(fn () => modules(['messaging' => true]))->toThrow(ValidationException::class, 'The platform team switched');
});

it('tells the apps which modules are on, in me and in branding', function () {
    $this->enrolStudentOnPlan();
    $all = array_column(Feature::cases(), 'value');

    moduleApi($this->student)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.modules', $all);
    $this->getJson('http://noor.lms.test/api/v1/branding')->assertOk()->assertJsonPath('modules', $all);

    modules(['games' => false, 'support' => false]);
    $on = array_values(array_diff($all, ['games', 'support']));
    moduleApi($this->student)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.modules', $on);
    $this->getJson('http://noor.lms.test/api/v1/branding')->assertOk()->assertJsonPath('modules', $on);
});
