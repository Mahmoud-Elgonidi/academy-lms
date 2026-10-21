<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Filament\Console\Pages\NewAcademy;
use Modules\Platform\Actions\Plans\SavePlan;
use Modules\Platform\Actions\Site\HandleSignupRequest;
use Modules\Platform\Actions\Site\SaveSiteContent;
use Modules\Platform\Actions\Site\SubmitSignupRequest;
use Modules\Platform\Filament\Console\Pages\SignupRequests;
use Modules\Platform\Filament\Console\Pages\SitePage;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\Site\PlatformSite;
use Modules\Platform\Models\Site\SignupRequest;
use Modules\Platform\Site\NewSignupRequest;
use Modules\Platform\Site\SiteContent;
use Modules\SharedKernel\Http\FormToken;

/*
 * The platform's own page (docs/architecture/02, section 23): what it says is
 * an owner's to set from the console, it shows the plans marked for it, and
 * an academy asks to join from it; the platform team answers each request and
 * makes the academy from it. Machines' forms are taken and kept nowhere.
 */

beforeEach(function () {
    $this->owner = PlatformAdmin::query()->create(['name' => 'Salma', 'email' => 'salma@company.example', 'password' => 'a-long-password']);
    $this->owner->forceFill(['is_owner' => true])->save();
    $this->owner->saveAppAuthenticationSecret('LMSSITETESTCONSOLE2FASECRET23456');
    $this->helper = PlatformAdmin::query()->create(['name' => 'Omar', 'email' => 'omar@company.example', 'password' => 'a-long-password']);
    SaasPlan::query()->create(['code' => 'starter', 'name_ar' => 'البداية', 'name_en' => 'Starter', 'max_teachers' => 5, 'features' => [], 'price_minor' => 29900, 'currency' => 'SAR', 'is_default' => true, 'sort' => 1, 'tagline_ar' => 'لأكاديمية بتبدأ', 'tagline_en' => 'For a new academy']);
    SaasPlan::query()->create(['code' => 'growth', 'name_ar' => 'النمو', 'name_en' => 'Growth', 'features' => ['crm', 'ai'], 'price_minor' => 59900, 'currency' => 'SAR', 'sort' => 2, 'highlight' => true]);
    SaasPlan::query()->create(['code' => 'hidden', 'name_ar' => 'خاصة', 'name_en' => 'Private', 'features' => [], 'sort' => 3, 'shown_on_site' => false]);
    SaasPlan::query()->create(['code' => 'old', 'name_ar' => 'قديمة', 'name_en' => 'Old', 'features' => [], 'sort' => 4, 'archived_at' => now()]);
    Mail::fake();
});

/** A form as the page sends it, its token given long enough ago. */
function signupForm(array $change = []): array
{
    $token = FormToken::issue(SubmitSignupRequest::FORM);
    test()->travel(5)->seconds();

    return array_merge([
        'academy_name' => 'أكاديمية الهدى', 'contact_name' => 'Huda Salem', 'email' => 'Huda@Example.com', 'phone' => '+966 50 123 4567',
        'country' => 'sa', 'students' => '21-50', 'plan' => 'growth', 'message' => "We teach Quran.\nTen teachers.", 'utm_source' => 'google', 'utm_campaign' => 'launch',
        'form_token' => $token, 'website' => '',
    ], $change);
}

it('shows the page in the visitor\'s language: the words, the plans marked for it, the store links and a fresh form token', function () {
    config(['platform.app.android_url' => 'https://play.google.com/store/apps/details?id=com.academylms.academy_app']);

    $page = $this->getJson('http://lms.test/api/v1/site/platform', ['Accept-Language' => 'ar'])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($page->json('data.content.hero.title'))->toBe(__('platform::site.defaults.hero.title', [], 'ar'))
        ->and($page->json('data.content.faqs'))->toHaveCount(6)
        ->and($page->json('data.content.sections'))->not->toContain('testimonials')
        ->and(collect($page->json('data.plans'))->pluck('code')->all())->toBe(['starter', 'growth'])
        ->and($page->json('data.plans.0.tagline'))->toBe('لأكاديمية بتبدأ')
        ->and($page->json('data.plans.1.highlight'))->toBeTrue()
        ->and($page->json('data.plans.0.price'))->toContain('299')
        ->and(collect($page->json('data.plans.1.features'))->firstWhere('key', 'crm')['on'])->toBeTrue()
        ->and($page->json('data.app.android_url'))->toStartWith('https://play.google.com')
        ->and(FormToken::passes(SubmitSignupRequest::FORM, $page->json('data.form_token')))->toBeFalse(); // too soon to be a person's

    expect($this->getJson('http://lms.test/api/v1/site/platform', ['Accept-Language' => 'en'])->json('data.content.hero.title'))
        ->toBe(__('platform::site.defaults.hero.title', [], 'en'));
});

it('is not on an academy\'s address', function () {
    $this->createAcademy(['slug' => 'noor']);

    $this->getJson('http://noor.lms.test/api/v1/site/platform')->assertNotFound();
    $this->postJson('http://noor.lms.test/api/v1/site/platform/requests', signupForm())->assertNotFound();
    expect(SignupRequest::query()->count())->toBe(0);
});

it('takes a request to join, and tells the owners by email', function () {
    $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm(), ['Accept-Language' => 'en'])->assertCreated()->assertJson(['data' => ['received' => true]]);

    $request = SignupRequest::query()->sole();
    expect($request->only(['academy_name', 'email', 'phone', 'country', 'students', 'plan_code', 'locale', 'utm_source', 'utm_campaign', 'status']))->toBe([
        'academy_name' => 'أكاديمية الهدى', 'email' => 'huda@example.com', 'phone' => '+966501234567', 'country' => 'SA', 'students' => '21-50',
        'plan_code' => 'growth', 'locale' => 'en', 'utm_source' => 'google', 'utm_campaign' => 'launch', 'status' => 'new',
    ]);
    Mail::assertQueued(NewSignupRequest::class, fn (NewSignupRequest $mail) => $mail->hasTo('salma@company.example') && ! $mail->hasTo('omar@company.example')
        && $mail->request['academy_name'] === 'أكاديمية الهدى');

    // The same address again within the week is the same request.
    $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm(['message' => 'again']))->assertCreated();
    expect(SignupRequest::query()->count())->toBe(1);
    Mail::assertQueuedCount(1);
});

it('says what is missing or wrong, in the visitor\'s words', function () {
    $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm(['academy_name' => '', 'email' => 'not-an-email', 'plan' => 'hidden', 'students' => 'many']), ['Accept-Language' => 'ar'])
        ->assertUnprocessable()->assertJsonValidationErrors(['academy_name', 'email', 'plan', 'students']);
    expect(SignupRequest::query()->count())->toBe(0);
});

it('answers a machine the same and keeps nothing: the hidden field filled, a token too fresh, missing or another form\'s', function (string $how) {
    $form = match ($how) {
        'hidden field' => signupForm(['website' => 'https://spam.example']),
        'no token' => signupForm(['form_token' => null]),
        'another form' => signupForm(['form_token' => FormToken::issue('academy-join')]),
        default => signupForm(),
    };
    if ($how === 'too fresh') {
        $form['form_token'] = FormToken::issue(SubmitSignupRequest::FORM);
    }

    $this->postJson('http://lms.test/api/v1/site/platform/requests', $form)->assertCreated();
    expect(SignupRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
})->with(['hidden field', 'too fresh', 'no token', 'another form']);

it('takes a few requests an hour from one address at most', function () {
    RateLimiter::clear('platform.site.requests');
    foreach (range(1, 5) as $i) {
        $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm(['email' => "a{$i}@example.com"]))->assertCreated();
    }
    $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm(['email' => 'a6@example.com']))->assertTooManyRequests();
});

it('keeps what an owner saves, cleaned and held to its limits, and audits the sections only', function () {
    $content = SiteContent::defaults();
    $content['hero']['title_ar'] = "  أكاديميتك\u{0007} على الإنترنت  ";
    $content['stats'] = [['value' => '+50', 'label_ar' => 'أكاديمية', 'label_en' => 'academies'], ['value' => '', 'label_ar' => '', 'label_en' => '']];
    $content['testimonials'] = [['name' => 'Huda', 'role_ar' => '', 'role_en' => 'Owner', 'quote_ar' => 'نظام ممتاز', 'quote_en' => 'Great system']];
    $content['sections'] = ['pricing', 'faq', 'nonsense', 'testimonials'];

    $kept = app(SaveSiteContent::class)->handle($this->owner, $content);

    expect($kept['hero']['title_ar'])->toBe('أكاديميتك على الإنترنت')
        ->and($kept['stats'])->toHaveCount(1)
        ->and($kept['sections'])->toBe(['pricing', 'testimonials', 'faq'])
        ->and(PlatformSite::content()['testimonials'][0]['quote_en'])->toBe('Great system');
    $audit = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.site_saved')->sole();
    expect(json_decode($audit->data, true))->toBe(['shown' => ['pricing', 'testimonials', 'faq']]);

    $page = $this->getJson('http://lms.test/api/v1/site/platform', ['Accept-Language' => 'en'])->json('data.content');
    expect($page['hero']['title'])->toBe($content['hero']['title_en'])
        ->and($page['testimonials'][0])->toBe(['name' => 'Huda', 'role' => 'Owner', 'quote' => 'Great system']);

    // Arabic left empty reads the English, and the other way round.
    expect($this->getJson('http://lms.test/api/v1/site/platform', ['Accept-Language' => 'ar'])->json('data.content.testimonials.0.role'))->toBe('Owner');

    expect(fn () => app(SaveSiteContent::class)->handle($this->owner, ['hero' => ['title_ar' => str_repeat('x', 121)]] + $content))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveSiteContent::class)->handle($this->owner, ['demo_url' => 'javascript:alert(1)'] + $content))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveSiteContent::class)->handle($this->helper, $content))->toThrow(AuthorizationException::class);
});

it('lets owners alone edit the page from the console', function () {
    Filament::setCurrentPanel('console');

    $this->actingAs($this->helper, 'platform');
    expect(SitePage::canAccess())->toBeFalse();

    $this->actingAs($this->owner, 'platform');
    Livewire::test(SitePage::class)
        ->assertSee(__('platform::site.console.title'))
        ->set('data.hero.title_en', 'Run your academy online')
        ->call('save')
        ->assertNotified(__('platform::site.console.saved'));
    expect(PlatformSite::content()['hero']['title_en'])->toBe('Run your academy online');
});

it('lists the requests in the console, and the team answers them', function () {
    $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm())->assertCreated();
    $request = SignupRequest::query()->sole();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->helper, 'platform');

    expect(SignupRequests::getNavigationBadge())->toBe('1');
    Livewire::test(SignupRequests::class)
        ->assertSee('أكاديمية الهدى')->assertSee('huda@example.com')->assertSee('https://wa.me/966501234567')
        ->callAction('contacted', arguments: ['request' => $request->id])
        ->assertNotified(__('platform::site.requests.answered'));
    expect($request->fresh()->status)->toBe('contacted')->and($request->fresh()->handled_by)->toBe($this->helper->id)
        ->and(SignupRequests::getNavigationBadge())->toBeNull();

    Livewire::test(SignupRequests::class)
        ->callAction('decline', ['note' => 'Not tutoring'], ['request' => $request->id]);
    expect($request->fresh()->only(['status', 'note']))->toBe(['status' => 'declined', 'note' => 'Not tutoring']);
    Livewire::withQueryParams(['status' => 'open'])->test(SignupRequests::class)->assertDontSee('أكاديمية الهدى');
});

it('makes the academy from a request, filled in, on the plan asked for, and marks the request made', function () {
    $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm(['academy_name' => 'Huda Academy']), ['Accept-Language' => 'en'])->assertCreated();
    $request = SignupRequest::query()->sole();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    Livewire::withQueryParams(['request' => $request->id])->test(NewAcademy::class)
        ->assertSet('data.owner_email', 'huda@example.com')
        ->assertSet('data.name_en', 'Huda Academy')
        ->assertSet('data.plan', 'growth')
        ->assertSet('data.default_locale', 'en')
        ->set('data.name_ar', 'أكاديمية الهدى')
        ->set('data.slug', 'huda')
        ->call('create')
        ->assertHasNoErrors();

    $request->refresh();
    $academyId = DB::connection('pgsql_platform')->table('academies')->where('slug', 'huda')->value('id');
    expect($request->status)->toBe('created')->and($request->academy_id)->toBe($academyId)
        ->and(DB::connection('pgsql_platform')->table('saas_subscriptions')->where('academy_id', $academyId)->whereNull('ended_at')->value('plan_id'))
        ->toBe(SaasPlan::query()->where('code', 'growth')->value('id'));
    expect(fn () => app(HandleSignupRequest::class)->handle($this->owner, $request->id, 'created'))->toThrow(ValidationException::class);
});

it('lets requests that never became an academy go after a year', function () {
    $this->postJson('http://lms.test/api/v1/site/platform/requests', signupForm())->assertCreated();
    $made = SignupRequest::query()->create(['academy_name' => 'Made', 'contact_name' => 'M', 'email' => 'm@example.com', 'status' => 'created']);
    $this->travel(13)->months();

    $this->artisan('platform:site-sweep')->assertSuccessful();
    expect(SignupRequest::query()->pluck('id')->all())->toBe([$made->id]);
});

it('shows plans on the page as the console sets them: one set apart at most, the hidden and the closed left out', function () {
    $growth = SaasPlan::query()->where('code', 'growth')->sole();
    app(SavePlan::class)->handle(SaasPlan::query()->where('code', 'starter')->value('id'), [
        'code' => 'starter', 'name_ar' => 'البداية', 'name_en' => 'Starter', 'features' => [], 'highlight' => true, 'shown_on_site' => true, 'tagline_en' => 'Start here',
    ]);

    expect($growth->fresh()->highlight)->toBeFalse()
        ->and(collect($this->getJson('http://lms.test/api/v1/site/platform', ['Accept-Language' => 'en'])->json('data.plans'))->where('highlight', true)->pluck('code')->all())->toBe(['starter']);
});
