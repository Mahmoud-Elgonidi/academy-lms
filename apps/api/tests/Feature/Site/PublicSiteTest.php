<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\SharedKernel\Http\FormToken;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SiteSettings;
use Tests\Support\AcademySite;

uses(AcademySite::class);

/*
 * An academy's public page (docs/architecture/02, section 24), as the portal
 * reads it on the academy's host: the words in the visitor's language, the
 * subjects with their levels and programs, the teachers who chose to show
 * (and never a number or an email), the plans priced in each region, and the
 * regions with their countries. Off, it is not there. Its form takes a
 * request for a free trial or to teach; a machine's form is answered the
 * same and kept nowhere.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy();
});

/** A form as the page sends it, its token given long enough ago. */
function joinForm(array $change = []): array
{
    $token = FormToken::issue(SubmitJoinRequest::FORM);
    test()->travel(5)->seconds();

    return array_merge([
        'kind' => 'trial', 'name' => 'Marwa Salem', 'phone' => '00966 50-123 4567', 'email' => 'Marwa@Example.com', 'country' => 'sa', 'timezone' => 'Asia/Riyadh',
        'subject_id' => test()->quran, 'for_child' => true, 'child_name' => 'Yara', 'child_birth_year' => now()->year - 9,
        'message' => "Evenings please.\nAfter 6.", 'utm_source' => 'instagram', 'utm_medium' => 'paid', 'utm_campaign' => 'ramadan',
        'form_token' => $token, 'website' => '',
    ], $change);
}

it('is not there while the academy keeps its page off', function () {
    $this->inNoor(fn () => SiteSettings::query()->update(['enabled' => false]));

    $this->getJson('http://noor.lms.test/api/v1/site')->assertNotFound();
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm())->assertNotFound();
    expect($this->inNoor(fn () => JoinRequest::query()->count()))->toBe(0);

    // Nor on an academy that never made one.
    $this->createAcademy(['slug' => 'huda']);
    $this->getJson('http://huda.lms.test/api/v1/site')->assertNotFound();
});

it('shows the page in the visitor\'s language, with the academy\'s name and code and a fresh form token', function () {
    $page = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'ar'])->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect($page->json('data.academy'))->toBe(['name' => 'أكاديمية نور', 'code' => 'noor'])
        ->and($page->json('data.content.hero.title'))->toBe(__('site::site.defaults.hero.title', [], 'ar'))
        ->and($page->json('data.content.features'))->toHaveCount(6)
        ->and($page->json('data.content.faqs'))->toHaveCount(6)
        ->and($page->json('data.content.testimonials'))->toBe([])
        ->and($page->json('data.content.sections'))->not->toContain('testimonials')->toContain('teachers', 'pricing', 'teach')
        ->and($page->json('data.whatsapp'))->toBeNull()
        ->and(FormToken::passes(SubmitJoinRequest::FORM, $page->json('data.form_token')))->toBeFalse();   // too soon to be a person's

    $en = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk();
    expect($en->json('data.academy.name'))->toBe('Noor Academy')
        ->and($en->json('data.content.hero.title'))->toBe(__('site::site.defaults.hero.title', [], 'en'));
});

it('lists the subjects with their levels, tracks and published programs', function () {
    $subjects = collect($this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->json('data.subjects'))->keyBy('name');

    expect($subjects->keys()->sort()->values()->all())->toBe(['Arabic', 'Quran'])
        ->and($subjects['Quran']['id'])->toBe($this->quran)
        ->and($subjects['Quran']['levels'])->toBe(['Beginner', 'Intermediate', 'Advanced'])
        ->and($subjects['Quran']['categories'])->toBe(['Memorising', 'Tajweed'])
        ->and($subjects['Quran']['programs'])->toBe([['name' => 'Nour Al-Bayan', 'description' => 'From the letters to reading']])
        ->and($subjects['Arabic']['programs'])->toBe([]);
});

it('shows only the teachers who chose to show, teach now and were not taken off, never how to reach them', function () {
    $page = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk();
    $teachers = $page->json('data.teachers');
    $sara = $this->inNoor(fn () => DB::table('teacher_public_cards')->where('teacher_id', $this->teachers['sara']->id)->value('id'));

    expect($teachers)->toBe([[
        'id' => $sara, 'name' => 'Ms Sara', 'bio' => 'Sara Fathy teaches Quran.', 'photo_url' => null, 'gender' => 'female',
        'languages' => ['ar', 'en'], 'age_groups' => ['kids', 'teen'],
        'subjects' => [['name' => 'Quran', 'level_from' => 'Beginner', 'level_to' => 'Intermediate']], 'accepts_trials' => true, 'accepts_groups' => false,
    ]]);
    $body = (string) $page->getContent();
    foreach ($this->noorContacts() as $contact) {
        expect($body)->not->toContain($contact);
    }
    foreach ($this->teachers as $teacher) {
        expect($body)->not->toContain($teacher->id);
    }

    // A teacher whose profile is put on hold leaves the page too; with the section off, nobody shows.
    $this->inNoor(fn () => DB::table('teacher_profiles')->where('user_id', $this->teachers['sara']->id)->update(['status' => 'inactive']));
    expect($this->getJson('http://noor.lms.test/api/v1/site')->json('data.teachers'))->toBe([]);
    $this->inNoor(function () {
        DB::table('teacher_profiles')->where('user_id', $this->teachers['sara']->id)->update(['status' => 'active']);
        $settings = SiteSettings::current();
        $settings->forceFill(['content' => ['sections' => ['subjects', 'faq']] + $settings->content])->save();
    });
    $page = $this->getJson('http://noor.lms.test/api/v1/site')->assertOk();
    expect($page->json('data.teachers'))->toBe([])->and($page->json('data.plans'))->toBe([]);
});

it('prices each plan in every region, and names each region\'s countries', function () {
    $page = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk();

    expect($page->json('data.regions'))->toBe([
        ['id' => $this->regions['SAR'], 'name' => 'Gulf', 'currency' => 'SAR', 'default' => true, 'countries' => ['SA', 'KW']],
        ['id' => $this->regions['EGP'], 'name' => 'Egypt', 'currency' => 'EGP', 'default' => false, 'countries' => ['EG']],
    ]);
    $plan = $page->json('data.plans.0');
    expect($plan)->toMatchArray(['name' => '8 lessons', 'session_minutes' => 30, 'sessions_per_week' => 2, 'sessions_per_block' => 8, 'shared' => false, 'format' => 'private', 'max_students' => null])
        ->and(collect($plan['prices'])->keyBy('currency')->map(fn ($p) => [$p['region_id'], $p['amount_minor']])->all())
        ->toBe(['SAR' => [$this->regions['SAR'], 40000], 'EGP' => [$this->regions['EGP'], 220000]])
        ->and(collect($plan['prices'])->firstWhere('currency', 'SAR')['price'])->toContain('400');
});

it('takes a request for a free trial, cleaned, and tells who answers it without who asked', function () {
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(), ['Accept-Language' => 'en'])->assertCreated()->assertJson(['data' => ['received' => true]]);

    $request = $this->inNoor(fn () => JoinRequest::query()->sole());
    expect($request->only(['kind', 'name', 'phone', 'email', 'country', 'timezone', 'locale', 'subject_id', 'for_child', 'child_name', 'utm_source', 'utm_medium', 'utm_campaign', 'status']))->toBe([
        'kind' => 'trial', 'name' => 'Marwa Salem', 'phone' => '+966501234567', 'email' => 'marwa@example.com', 'country' => 'SA', 'timezone' => 'Asia/Riyadh', 'locale' => 'en',
        'subject_id' => $this->quran, 'for_child' => true, 'child_name' => 'Yara', 'utm_source' => 'instagram', 'utm_medium' => 'paid', 'utm_campaign' => 'ramadan', 'status' => 'new',
    ])->and($request->message)->toBe("Evenings please.\nAfter 6.");

    // Who works leads hears of it (sales, the scheduler, the owner and admins), with the kind only.
    $told = $this->inNoor(fn () => DB::table('notifications')->where('template_key', 'site.request_new')->get());
    expect($told->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->owner, $this->admin, $this->sales, $this->scheduler])->pluck('id')->sort()->values()->all());
    foreach ($told as $note) {
        expect($note->title.' '.$note->body)->not->toContain('Marwa')->not->toContain('4567')->not->toContain('Yara')
            ->and($note->body)->toContain(__('site::notifications.kinds.trial', [], 'en'));
    }
});

it('takes a teacher asking to teach, told to who runs the teachers', function () {
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm([
        'kind' => 'teach', 'name' => 'Ahmed Zaki', 'phone' => '+20 100 222 3333', 'subject_ids' => [$this->quran, $this->arabic, $this->quran], 'years_teaching' => 6,
        'subject_id' => $this->quran, 'for_child' => true,
    ]))->assertCreated();

    $request = $this->inNoor(fn () => JoinRequest::query()->sole());
    expect($request->only(['kind', 'phone', 'subject_ids', 'years_teaching', 'subject_id', 'for_child', 'child_name']))->toBe([
        'kind' => 'teach', 'phone' => '+201002223333', 'subject_ids' => [$this->quran, $this->arabic], 'years_teaching' => 6, 'subject_id' => null, 'for_child' => false, 'child_name' => null,
    ]);
    $told = $this->inNoor(fn () => DB::table('notifications')->where('template_key', 'site.request_new')->pluck('user_id')->sort()->values()->all());
    expect($told)->toBe(collect([$this->owner, $this->admin, $this->scheduler])->pluck('id')->sort()->values()->all())
        ->and($this->inNoor(fn () => DB::table('leads')->count()))->toBe(0);
});

it('says what is missing or wrong, in the visitor\'s words', function () {
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['kind' => 'lesson', 'name' => '', 'phone' => '0501234567', 'email' => 'nope', 'timezone' => '+03:00']), ['Accept-Language' => 'en'])
        ->assertUnprocessable()->assertJsonValidationErrors(['kind', 'name', 'phone', 'email', 'timezone'])
        ->assertJsonFragment(['phone' => [__('site::site.errors.phone', [], 'en')]]);
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['child_name' => '', 'subject_id' => (string) Str::uuid7(), 'child_birth_year' => 1980]))
        ->assertUnprocessable()->assertJsonValidationErrors(['child_name', 'subject_id', 'child_birth_year']);
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['kind' => 'teach', 'subject_ids' => ['not-a-subject']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['subject_ids.0']);
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['subject_id' => 'not-a-subject']))
        ->assertUnprocessable()->assertJsonValidationErrors(['subject_id']);

    expect($this->inNoor(fn () => JoinRequest::query()->count()))->toBe(0);
});

it('answers a machine the same and keeps nothing: the hidden field filled, a token too fresh, missing or another form\'s', function (string $how) {
    $form = match ($how) {
        'hidden field' => joinForm(['website' => 'https://spam.example']),
        'no token' => joinForm(['form_token' => null]),
        'another form' => joinForm(['form_token' => FormToken::issue('platform-signup')]),
        default => joinForm(),
    };
    if ($how === 'too fresh') {
        $form['form_token'] = FormToken::issue(SubmitJoinRequest::FORM);
    }

    $this->postJson('http://noor.lms.test/api/v1/site/requests', $form)->assertCreated()->assertJson(['data' => ['received' => true]]);
    expect($this->inNoor(fn () => JoinRequest::query()->count()))->toBe(0)
        ->and($this->inNoor(fn () => DB::table('notifications')->count()))->toBe(0);
})->with(['hidden field', 'too fresh', 'no token', 'another form']);

it('keeps the same number asking the same again within a week as one request', function () {
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm())->assertCreated();
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['name' => 'Marwa S.', 'phone' => '+966501234567']))->assertCreated();
    expect($this->inNoor(fn () => JoinRequest::query()->count()))->toBe(1);

    // To teach is another request; so is the same a week later.
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['kind' => 'teach']))->assertCreated();
    $this->travel(8)->days();
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm())->assertCreated();
    expect($this->inNoor(fn () => JoinRequest::query()->count()))->toBe(3);
});

it('takes a few requests an hour from one address at most', function () {
    RateLimiter::clear('site.requests');
    foreach (range(1, 5) as $i) {
        $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['phone' => "+96650000000{$i}"]))->assertCreated();
    }
    $this->postJson('http://noor.lms.test/api/v1/site/requests', joinForm(['phone' => '+966500000009']))->assertTooManyRequests();
});

it('reads the other language where one is empty, and keeps what families said in their own name', function () {
    $this->inNoor(function () {
        $content = SiteContent::defaults();
        $content['testimonials'] = [['name' => 'Umm Yousef', 'role_ar' => '', 'role_en' => 'A mother in London', 'quote_ar' => 'ممتاز', 'quote_en' => '']];
        $content['sections'][] = 'testimonials';
        SiteSettings::query()->update(['content' => json_encode(SiteContent::normalize($content))]);
    });

    expect($this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'ar'])->json('data.content.testimonials'))
        ->toBe([['name' => 'Umm Yousef', 'role' => 'A mother in London', 'quote' => 'ممتاز']])
        ->and($this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->json('data.content.testimonials.0.quote'))->toBe('ممتاز');
});
