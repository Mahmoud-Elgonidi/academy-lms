<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Family\AddOwnChild;
use Modules\People\Contracts\Events\ConsentRequested;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\GuardianConsent;

/*
 * «إضافة طفل» (docs/architecture/02, section 27, J5a): a guardian adds their own
 * child from the portal or the app, consents at once, and goes straight into
 * the start flow.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->mother = User::factory()->withRole(Role::Guardian)->create(['timezone' => 'Asia/Riyadh', 'locale' => 'ar']);
    DB::table('guardian_profiles')->insert(['academy_id' => $this->academy->id, 'user_id' => $this->mother->id, 'country' => 'SA', 'created_at' => now(), 'updated_at' => now()]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function addChild(array $data = [], ?User $as = null, ?string $key = null): TestResponse
{
    app('auth')->forgetGuards();
    $response = test()->withToken(($as ?? test()->mother)->createToken('phone')->plainTextToken)
        ->withHeaders(['Accept-Language' => 'ar', 'Idempotency-Key' => $key ?? 'k-'.bin2hex(random_bytes(6))])
        ->postJson('http://noor.lms.test/api/v1/family/children', $data + ['name' => 'Yara Salem', 'birth_year' => 2018, 'gender' => 'female', 'agree' => true, 'version' => GuardianConsent::VERSION]);
    test()->actAsTenant(test()->academy);

    return $response;
}

it('adds a managed child under the guardian, consented at once, and hands back where to start', function () {
    $answer = addChild()->assertCreated()->json();
    $id = $answer['data']['id'];

    expect($answer['data'])->toMatchArray(['name' => 'Yara Salem', 'consent' => 'granted', 'start' => ['learner_id' => $id], 'stage' => 'kids'])
        ->and($answer['message'])->toContain('Yara Salem')
        ->and(DB::table('users')->where('id', $id)->first(['is_managed_minor', 'timezone', 'email', 'phone_e164']))
        ->toEqual((object) ['is_managed_minor' => true, 'timezone' => 'Asia/Riyadh', 'email' => null, 'phone_e164' => null])
        ->and(app(PeopleApi::class)->guardiansOf($id))->toBe([$this->mother->id])
        ->and(app(PeopleApi::class)->studentDetails($id)->gender)->toBe('female')
        ->and(app(PeopleApi::class)->countryOf($id))->toBe('SA')
        // Only a year: taken as its last day, the youngest she could be.
        ->and(app(PeopleApi::class)->studentProfile($id)->birthDate?->toDateString())->toBe('2018-12-31')
        ->and(GuardianConsent::liveFor($id)?->guardian_id)->toBe($this->mother->id)
        ->and(app(PeopleApi::class)->consentState($id)->waiting())->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'people.child_added')->count())->toBe(1);

    // The family's learners now include her: the start flow takes her id.
    app('auth')->forgetGuards();
    $learners = $this->withToken($this->mother->createToken('phone')->plainTextToken)->getJson('http://noor.lms.test/api/v1/family/learners')->assertOk()->json('data');
    expect(array_column($learners, 'id'))->toContain($id);
});

it('asks nobody for consent: the guardian adding the child is the one who agrees', function () {
    Event::fake([ConsentRequested::class]);
    addChild()->assertCreated();
    Event::assertNotDispatched(ConsentRequested::class);
});

it('answers the same twice for one key, and refuses the same child added again, kindly', function () {
    $first = addChild(key: 'same-key')->assertCreated()->json('data.id');
    expect(addChild(key: 'same-key')->assertCreated()->json('data.id'))->toBe($first);

    $again = addChild(['name' => '  yara   salem '])->assertStatus(422);
    expect($again->json('errors.name.0'))->toContain('Yara Salem')->toContain('اختاروه')
        ->and(DB::table('guardian_student')->where('guardian_id', $this->mother->id)->count())->toBe(1);
    // A twin with another name, or the same name a year apart, is someone else.
    addChild(['name' => 'Yasmin Salem'])->assertCreated();
    addChild(['birth_year' => 2016])->assertCreated();
});

it('refuses an account that is not a guardian, a missing consent, a wording since changed, and more than the cap', function () {
    $adult = User::factory()->withRole(Role::Student)->create();
    expect(addChild(as: $adult)->assertForbidden()->json('detail'))->toContain('وليّ الأمر');
    addChild(['agree' => false])->assertStatus(422)->assertJsonValidationErrors('agree');
    addChild(['version' => GuardianConsent::VERSION + 1])->assertStatus(422)->assertJsonValidationErrors('version');
    addChild(['birth_year' => null])->assertStatus(422)->assertJsonValidationErrors('birth_year');
    addChild(['birth_year' => 2030])->assertStatus(422)->assertJsonValidationErrors('birth_year');

    foreach (range(1, AddOwnChild::MAX_CHILDREN) as $i) {
        app(AddOwnChild::class)->handle($this->mother->id, "Child {$i}", null, 2015, null, GuardianConsent::VERSION);
    }
    expect(addChild()->assertStatus(422)->json('errors.name.0'))->toContain((string) AddOwnChild::MAX_CHILDREN);
});
