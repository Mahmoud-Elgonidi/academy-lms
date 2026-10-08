<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Classroom\Authorization\ClassroomAccess;
use Modules\Communication\Models\Notification;
use Modules\Identity\Contracts\SignInGate;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\SignIn;
use Modules\Identity\Models\User;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Actions\Consent\RecordConsent;
use Modules\People\Actions\Consent\SaveConsentAge;
use Modules\People\Actions\Consent\WithdrawConsent;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Filament\Family\FamilyPrivacy;
use Modules\People\Filament\Pages\GuardianConsents;
use Modules\People\Models\GuardianConsent;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * A guardian's consent to a child's account (docs/architecture/02, "موافقة
 * ولي الأمر"): a student under the academy's consent age signs in, joins by
 * their own link, or hands in anything only once a guardian has agreed; the
 * guardian is asked when linked, agrees from the link, /my or the apps, and
 * can take it back; the office follows it and records what it holds.
 */

beforeEach(function () {
    $this->withoutDefer();
    CarbonImmutable::setTestNow('2026-10-05 07:00:00');
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->office = User::factory()->withRole(Role::Supervisor)->create(['name' => 'Mona']);
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Hala']);
    $this->father = User::factory()->withRole(Role::Guardian)->create(['name' => 'Khaled', 'email' => 'khaled@noor.test', 'password' => 'a-long-password', 'locale' => 'en']);
    $this->mother = User::factory()->withRole(Role::Guardian)->create(['name' => 'Huda']);
    $this->yousef = User::factory()->withRole(Role::Student)->create(['name' => 'Yousef', 'username' => 'yousef', 'email' => null, 'password' => 'a-long-password']);
    $people = app(PeopleApi::class);
    $people->saveStudentProfile($this->yousef->id, CarbonImmutable::parse('2017-03-01'), null);
    $people->linkGuardian($this->father->id, $this->yousef->id);
    $people->linkGuardian($this->mother->id, $this->yousef->id, 'parent', false);
    $this->clearTenant();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

function inNoor(Closure $do): mixed
{
    test()->actAsTenant(test()->noor);
    try {
        return $do();
    } finally {
        test()->clearTenant();
    }
}

function consentLink(User $guardian): string
{
    $body = (string) inNoor(fn () => Notification::query()->where('user_id', $guardian->id)->where('template_key', 'people.consent_request')->latest('created_at')->value('body'));
    preg_match('~https?://\S+/consent/\S+~', $body, $m);

    return $m[0] ?? '';
}

it('asks who is younger than the academy\'s consent age, or of unknown age with a guardian', function () {
    inNoor(function () {
        $people = app(PeopleApi::class);
        $make = fn (string $name, ?string $born, bool $guardian = true) => tap(User::factory()->withRole(Role::Student)->create(['name' => $name]), function (User $s) use ($people, $born, $guardian) {
            if ($born) {
                $people->saveStudentProfile($s->id, CarbonImmutable::parse($born), null);
            }
            if ($guardian) {
                $people->linkGuardian($this->father->id, $s->id);
            }
        });
        $teen = $make('Maryam', '2010-01-01');                      // 16
        $adult = $make('Omar', '2008-10-05');                       // 18 today, in Riyadh
        $almost = $make('Sara', '2008-10-06');                      // 18 tomorrow
        $unknown = $make('Ziad', null);                             // no birth date, a guardian
        $alone = $make('Adel', null, false);                        // no birth date, nobody
        $rules = app(ConsentRules::class);

        expect($rules->state($this->yousef->id)->needed)->toBeTrue()
            ->and($rules->state($teen->id)->needed)->toBeTrue()
            ->and($rules->state($adult->id)->needed)->toBeFalse()
            ->and($rules->state($almost->id)->needed)->toBeTrue()
            ->and($rules->state($unknown->id)->needed)->toBeTrue()
            ->and($rules->state($alone->id)->needed)->toBeFalse()
            ->and($rules->state($this->father->id)->needed)->toBeFalse()
            ->and($rules->state($this->office->id)->needed)->toBeFalse();

        // Where the law allows 16: Maryam no longer needs it; Yousef still does.
        $this->actingAs($this->owner);
        app(SaveConsentAge::class)->handle(16);
        expect($rules->state($teen->id)->needed)->toBeFalse()->and($rules->state($this->yousef->id)->needed)->toBeTrue()
            ->and(DB::table('audit_logs')->where('action', 'people.consent_age_saved')->count())->toBe(1);
        expect(fn () => app(SaveConsentAge::class)->handle(15))->toThrow(ValidationException::class);
    });
});

it('asks each guardian once they are linked, after the commit, by their own link', function () {
    // Linked in beforeEach: both guardians were asked, each with their own link.
    expect(inNoor(fn () => Notification::query()->where('template_key', 'people.consent_request')->pluck('user_id')->sort()->values()->all()))
        ->toBe(collect([$this->father->id, $this->mother->id])->sort()->values()->all());
    $link = consentLink($this->father);
    expect($link)->toContain('noor.lms.test')->toContain('/consent/'.$this->yousef->id.'?');

    // Nothing leaves a transaction that is rolled back (an import's dry run).
    inNoor(function () {
        $aunt = User::factory()->withRole(Role::Guardian)->create();
        DB::beginTransaction();
        app(PeopleApi::class)->linkGuardian($aunt->id, $this->yousef->id);
        DB::rollBack();
        expect(Notification::query()->where('user_id', $aunt->id)->exists())->toBeFalse();
    });

    // The page behind it: the wording, then "I agree" (the box must be ticked).
    $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);
    $page = $this->get('http://noor.lms.test'.$path)->assertOk()->assertSee('Yousef')->assertSee(__('people::consent.text.sections.lessons.title'));
    preg_match('~action="([^"]+)"~', $page->getContent(), $m);
    $action = html_entity_decode($m[1]);
    $this->post('http://noor.lms.test'.$action, [])->assertSessionHasErrors('agree');
    $this->post('http://noor.lms.test'.$action, ['agree' => '1'], ['User-Agent' => 'Safari on iPhone'])->assertRedirect();

    inNoor(function () {
        $consent = GuardianConsent::liveFor($this->yousef->id);
        expect($consent->guardian_id)->toBe($this->father->id)->and($consent->via)->toBe('link')
            ->and($consent->version)->toBe(GuardianConsent::VERSION)->and($consent->user_agent)->toBe('Safari on iPhone')
            ->and(DB::table('audit_logs')->where('action', 'people.consent_granted')->value('subject_id'))->toBe($this->yousef->id);
    });
    // Again: it says so instead of asking.
    $this->get('http://noor.lms.test'.$path)->assertOk()->assertSee(__('people::consent.link.already', ['when' => '5 October 2026']));
    // Someone else's link, or a broken one, opens nothing.
    $this->get('http://noor.lms.test/consent/'.$this->yousef->id.'?g='.$this->office->id)->assertForbidden();
});

it('keeps the child out until a guardian agrees, on every way in', function () {
    $login = fn () => $this->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en'])->postJson('/api/v1/auth/login', ['login' => 'yousef', 'password' => 'a-long-password', 'device_name' => 'iPad']);
    $login()->assertForbidden()->assertJsonPath('type', 'guardian-consent-required')->assertJsonPath('detail', __('people::consent.refusal', [], 'en'));

    // The family area: the sign-in page says why, and a session from before stops at the door.
    $this->get('http://noor.lms.test/my/login')->assertOk();
    Filament::setCurrentPanel('family');
    inNoor(fn () => Livewire::test(SignIn::class)->set('data.email', 'yousef')->set('data.password', 'a-long-password')->call('authenticate')
        ->assertHasErrors(['data.email' => __('people::consent.refusal')]));
    $this->actingAs($this->yousef)->get('http://noor.lms.test/my')->assertForbidden();
    // The guardians and the office are not held back.
    expect(inNoor(fn () => app(SignInGate::class)->refusal($this->father->id)))->toBeNull();

    // The platform team, signed in as the child for support, is let through.
    AuditActor::use(new AuditActor('platform', 'p1', 'Salma', $this->yousef->id));
    expect(inNoor(fn () => app(SignInGate::class)->refusal($this->yousef->id)))->toBeNull();
    AuditActor::forget();

    inNoor(fn () => app(GrantConsent::class)->handle($this->yousef->id, $this->mother->id, 'family'));
    $login()->assertOk()->assertJsonStructure(['token']);
    app('auth')->forgetGuards();
    $this->actingAs($this->yousef)->get('http://noor.lms.test/my')->assertOk();
});

it('lets a guardian agree in /my, and take it back: the child is signed out everywhere and the office hears', function () {
    $this->actingAs($this->father);
    Filament::setCurrentPanel('family');
    inNoor(function () {
        $page = Livewire::test(FamilyPrivacy::class)->assertSee('Yousef')->assertSee(__('people::consent.state.waiting'));
        $page->call('agree', $this->yousef->id)->assertHasErrors(['agreed.'.$this->yousef->id]);
        $page->set('agreed.'.$this->yousef->id, true)->call('agree', $this->yousef->id)->assertHasNoErrors()
            ->assertSee(__('people::consent.state.you_agreed', ['when' => '5 October 2026']));
        expect(GuardianConsent::liveFor($this->yousef->id)->via)->toBe('family');
    });
    // The banner on the other family pages is gone with it.
    $this->get('http://noor.lms.test/my')->assertOk()->assertDontSee(__('people::consent.family.banner_action'));

    inNoor(fn () => $this->yousef->createToken('iPad'));
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $this->yousef->id)->count())->toBe(1);

    inNoor(fn () => Livewire::test(FamilyPrivacy::class)->call('withdraw', $this->yousef->id)->assertSee(__('people::consent.state.waiting')));
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $this->yousef->id)->count())->toBe(0);
    inNoor(function () {
        expect(GuardianConsent::liveFor($this->yousef->id))->toBeNull()
            ->and(GuardianConsent::query()->where('student_id', $this->yousef->id)->value('withdrawn_by'))->toBe($this->father->id)
            ->and(Notification::query()->where('template_key', 'people.consent_withdrawn')->pluck('user_id')->all())->toContain($this->office->id, $this->owner->id)
            ->and(DB::table('audit_logs')->where('action', 'people.consent_withdrawn')->count())->toBe(1);
    });
    $this->get('http://noor.lms.test/my')->assertOk()->assertSee(__('people::consent.family.banner_action'));

    // Only the child's own guardians.
    $stranger = inNoor(fn () => User::factory()->withRole(Role::Guardian)->create());
    expect(fn () => inNoor(fn () => app(GrantConsent::class)->handle($this->yousef->id, $stranger->id, 'family')))->toThrow(AuthorizationException::class);
});

it('shows the office who waits, and records a consent it holds, or takes one back at a guardian\'s request', function () {
    $this->actingAs($this->office);
    Filament::setCurrentPanel('academy');
    inNoor(function () {
        $page = Livewire::test(GuardianConsents::class)->assertCanSeeTableRecords([$this->yousef->id])->assertSee('Huda, Khaled');
        $before = Notification::query()->where('template_key', 'people.consent_request')->count();
        $page->callTableAction('resend', $this->yousef->id);
        expect(Notification::query()->where('template_key', 'people.consent_request')->count())->toBe($before + 2);

        $page->callTableAction('record', $this->yousef->id, ['guardian' => $this->mother->id, 'note' => 'short'])->assertHasTableActionErrors(['note']);
        $page = Livewire::test(GuardianConsents::class);
        $page->callTableAction('record', $this->yousef->id, ['guardian' => $this->mother->id, 'note' => 'Signed form, 4 October, in the file'])->assertHasNoTableActionErrors();
        $consent = GuardianConsent::liveFor($this->yousef->id);
        expect($consent->via)->toBe('staff')->and($consent->recorded_by)->toBe($this->office->id)->and($consent->note)->toBe('Signed form, 4 October, in the file');

        $page->set('tab', 'granted')->assertCanSeeTableRecords([$this->yousef->id])
            ->callTableAction('withdraw', $this->yousef->id, ['note' => 'Father called on 6 October'])->assertHasNoTableActionErrors();
        $page->set('tab', 'withdrawn')->assertCanSeeTableRecords([$this->yousef->id]);
        expect(app(RecordConsent::class)->handle($this->yousef->id, null, 'Mother signed again at the desk', $this->office->id)->guardian_id)->toBeNull();

        // The consent age is the owner's and admins' to set, not a supervisor's.
        $page->assertActionHidden('age');
    });
});

it('lets the apps list, agree with the wording\'s version, and take back', function () {
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en']);

    $api($this->father)->getJson('/api/v1/family/consents')->assertOk()
        ->assertJsonPath('age', 18)
        ->assertJsonPath('children.0.student.name', 'Yousef')
        ->assertJsonPath('children.0.state', 'waiting')
        ->assertJsonPath('children.0.text.version', GuardianConsent::VERSION)
        ->assertJsonStructure(['children' => [['text' => ['title', 'intro', 'sections' => [['title', 'body']], 'agree']]]]);
    $api($this->father)->postJson('/api/v1/family/consents/'.$this->yousef->id, ['agree' => true, 'version' => 0])->assertUnprocessable()->assertJsonValidationErrors('version');
    $api($this->father)->postJson('/api/v1/family/consents/'.$this->yousef->id, ['version' => GuardianConsent::VERSION])->assertUnprocessable()->assertJsonValidationErrors('agree');
    $api($this->yousef)->postJson('/api/v1/family/consents/'.$this->yousef->id, ['agree' => true, 'version' => GuardianConsent::VERSION])->assertForbidden();
    $api($this->father)->postJson('/api/v1/family/consents/'.$this->yousef->id, ['agree' => true, 'version' => GuardianConsent::VERSION])->assertOk()->assertJsonPath('state', 'granted');
    $api($this->mother)->getJson('/api/v1/family/consents')->assertJsonPath('children.0.state', 'granted')
        ->assertJsonPath('children.0.by', 'Khaled agreed on 5 October 2026')->assertJsonPath('children.0.can_withdraw', true);
    $api($this->mother)->postJson('/api/v1/family/consents/'.$this->yousef->id.'/withdraw')->assertOk()->assertJsonPath('state', 'waiting');
    // A student signed in sees no one to consent for.
    $api($this->yousef)->getJson('/api/v1/family/consents')->assertOk()->assertJsonPath('children', []);
});

it('keeps what was agreed: never deleted, withdrawn only once', function () {
    $consent = inNoor(fn () => app(GrantConsent::class)->handle($this->yousef->id, $this->father->id, 'family'));
    inNoor(function () use ($consent) {
        expect(fn () => DB::table('guardian_consents')->where('id', $consent->id)->update(['granted_at' => now()->subYear()]))->toThrow(QueryException::class);
    });
    inNoor(function () use ($consent) {
        expect(fn () => DB::table('guardian_consents')->where('id', $consent->id)->delete())->toThrow(QueryException::class);
    });
    inNoor(fn () => app(WithdrawConsent::class)->handle($this->yousef->id, $this->father->id, true));
    inNoor(function () use ($consent) {
        expect(fn () => DB::table('guardian_consents')->where('id', $consent->id)->update(['withdrawn_at' => now(), 'withdraw_note' => 'again']))->toThrow(QueryException::class);
    });
    expect(fn () => inNoor(fn () => app(WithdrawConsent::class)->handle($this->yousef->id, $this->father->id, true)))->toThrow(ValidationException::class);
});

it('keeps the child\'s own class link closed until then; a guardian can bring them in', function () {
    $teacher = inNoor(fn () => User::factory()->withRole(Role::Teacher)->create(['name' => 'Ahmed']));
    $lesson = new LessonCard('s1', 'scheduled', CarbonImmutable::now(), CarbonImmutable::now()->addHour(), $teacher->id, 'Ahmed', $this->yousef->id, 'Yousef', 'قرآن', 'Quran', null);
    inNoor(function () use ($lesson) {
        $access = app(ClassroomAccess::class);
        expect($access->seatFor($this->yousef->id, $lesson))->toBeNull()
            ->and($access->waitsForConsent($this->yousef->id, $lesson))->toBeTrue()
            ->and($access->seatFor($this->father->id, $lesson)?->role)->toBe('student');
        app(GrantConsent::class)->handle($this->yousef->id, $this->father->id, 'family');
        expect($access->seatFor($this->yousef->id, $lesson)?->role)->toBe('student');
    });
});
