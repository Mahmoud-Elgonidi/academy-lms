<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\SharedKernel\Http\FormToken;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Contracts\SiteApi;
use Modules\Site\Domain\Page\Audience;
use Modules\Site\Domain\Page\InvalidPage;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Filament\Pages\SiteBuilder;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SitePage;
use Tests\Support\AcademySite;
use Tests\Support\SitePages;

uses(AcademySite::class, SitePages::class);

/*
 * The trial form block (site-builder.md, 1.3 and 9.2): every word beside the
 * form is the academy's to write in the builder (the small line, title,
 * lead, points, the WhatsApp button and its words), and the block says who
 * books from it: adults and children (the "for whom?" choice, as before),
 * adults only or children only. A page for adults takes adults alone. The
 * server holds the request to the published page's choice and says what to
 * do. A page that changes nothing reads as before.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy();
});

/** A trial request as the page sends it (for a child unless changed). */
function trialAsk(array $change = []): array
{
    $token = FormToken::issue(SubmitJoinRequest::FORM);
    test()->travel(5)->seconds();

    return array_merge([
        'kind' => 'trial', 'name' => 'Marwa Salem', 'phone' => '+966501234567', 'timezone' => 'Asia/Riyadh',
        'subject_id' => test()->quran, 'for_child' => true, 'child_name' => 'Yara', 'child_birth_year' => now()->year - 9,
        'form_token' => $token, 'website' => '',
    ], $change);
}

function formBlockOf(array $site): array
{
    return array_values(array_filter($site['page']['blocks'], fn (array $b) => $b['type'] === 'form'))[0];
}

it('keeps the form as it was when nothing is chosen: both, the page\'s WhatsApp words', function () {
    $this->actingAs($this->owner);
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => []]]));

    $form = formBlockOf($this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk()->json('data'));
    expect($form['data'])->toMatchArray(['eyebrow' => null, 'title' => null, 'lead' => null, 'points' => null, 'whatsapp_button' => true, 'whatsapp_label' => null, 'who' => 'both', 'teach' => false])
        // A page saved before the choice existed reads both.
        ->and(PageDoc::normalize(['blocks' => [['type' => 'form', 'data' => ['teach' => true]]]])['blocks'][0]['data']['who'])->toBe('both');

    // The old page (never built) reads both too.
    $this->inNoor(fn () => SitePage::query()->delete());
    expect(formBlockOf($this->getJson('http://noor.lms.test/api/v1/site')->json('data'))['data']['who'])->toBe('both');
});

it('sends every word beside the form as the academy wrote it, in the visitor\'s language', function () {
    $this->actingAs($this->owner);
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'style' => ['eyebrow' => true], 'data' => [
        'eyebrow' => ['ar' => 'ابدأ رحلتك', 'en' => 'Start your journey'],
        'title' => ['ar' => 'خطوتك القادمة نحو التطور المهني', 'en' => 'Your next step'],
        'lead' => ['ar' => 'احجز جلسة تعريفية', 'en' => 'Book an introduction'],
        'points' => [['ar' => 'مدربون معتمدون', 'en' => 'Certified trainers']],
        'whatsapp_label' => ['ar' => 'اسألنا على واتساب', 'en' => 'Ask us on WhatsApp'],
        'who' => 'adults',
    ]]]));

    $ar = formBlockOf($this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'ar'])->json('data'));
    $en = formBlockOf($this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->json('data'));
    expect($ar['style']['eyebrow'])->toBeTrue()
        ->and($ar['data'])->toMatchArray(['eyebrow' => 'ابدأ رحلتك', 'title' => 'خطوتك القادمة نحو التطور المهني', 'points' => ['مدربون معتمدون'], 'whatsapp_label' => 'اسألنا على واتساب', 'who' => 'adults'])
        ->and($en['data'])->toMatchArray(['eyebrow' => 'Start your journey', 'whatsapp_label' => 'Ask us on WhatsApp']);

    // Held to its length like any other word.
    expect(fn () => PageDoc::normalize(['blocks' => [['type' => 'form', 'data' => ['whatsapp_label' => ['ar' => str_repeat('ب', 41)]]]]]))
        ->toThrow(InvalidPage::class);
});

it('takes adults alone on a page for adults, whatever the block says', function () {
    expect(Audience::bookers('children', Audience::ADULTS))->toBe('adults')
        ->and(Audience::bookers('both', Audience::ADULTS))->toBe('adults')
        ->and(Audience::bookers('children', Audience::FAMILIES))->toBe('children')
        ->and(Audience::bookers('nonsense', Audience::FAMILIES))->toBe('both');

    $this->actingAs($this->owner);
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => ['who' => 'children']]], ['audience' => 'adults']));
    expect(formBlockOf($this->getJson('http://noor.lms.test/api/v1/site')->json('data'))['data']['who'])->toBe('adults');

    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(), ['Accept-Language' => 'en'])
        ->assertUnprocessable()->assertJsonFragment(['for_child' => [__('site::site.errors.adults_only', [], 'en')]]);
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(['for_child' => false]))->assertCreated();
    expect($this->inNoor(fn () => JoinRequest::query()->sole()->for_child))->toBeFalse();
});

it('refuses kindly what the published form does not take, and takes the rest', function () {
    $this->actingAs($this->owner);

    // Adults only on a families page: no trial for a child.
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => ['who' => 'adults']]]));
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(), ['Accept-Language' => 'ar'])
        ->assertUnprocessable()->assertJsonFragment(['for_child' => [__('site::site.errors.adults_only', [], 'ar')]]);

    // Children only: the guardian asks for a child, never for themself.
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => ['who' => 'children']]]));
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(['for_child' => false]), ['Accept-Language' => 'en'])
        ->assertUnprocessable()->assertJsonFragment(['for_child' => [__('site::site.errors.children_only', [], 'en')]]);
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk())->assertCreated();

    // Both: either.
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => []]]));
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(['for_child' => false, 'phone' => '+966501234568']))->assertCreated();

    // "Teach with us" is not a trial: never held to it.
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => ['who' => 'children', 'teach' => true]]]));
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(['kind' => 'teach', 'for_child' => false, 'subject_ids' => [$this->quran]]))->assertCreated();

    expect($this->inNoor(fn () => JoinRequest::query()->count()))->toBe(3);
});

it('holds a trial booked with a code to the same choice, and names the fields in training words on a page for adults', function () {
    $this->actingAs($this->owner);
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => []]], ['audience' => 'adults']));

    // BookOwnTrial keeps its request through SiteApi::recordTrialRequest, the same rules as the page's form.
    $this->inNoor(function () {
        $ask = trialAsk();
        unset($ask['form_token'], $ask['website'], $ask['kind']);
        expect(fn () => app(SiteApi::class)->recordTrialRequest($ask, 'ar'))->toThrow(ValidationException::class, __('site::site.errors.adults_only'));
    });

    // A missing area is named as the form names it, never "the subject".
    $ar = $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(['for_child' => false, 'subject_id' => null]), ['Accept-Language' => 'ar'])
        ->assertUnprocessable()->json('errors.subject_id.0');
    $en = $this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(['for_child' => false, 'subject_id' => null]), ['Accept-Language' => 'en'])
        ->assertUnprocessable()->json('errors.subject_id.0');
    expect($ar)->toContain('المجال')->not->toContain('المادة')
        ->and($en)->toContain('area')->not->toContain('subject');

    // A page for families keeps its words.
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => []]]));
    expect($this->postJson('http://noor.lms.test/api/v1/site/requests', trialAsk(['subject_id' => null]), ['Accept-Language' => 'ar'])->json('errors.subject_id.0'))
        ->toContain('المادة');
});

it('shows every word and choice of the form in the builder, and where the rest are set', function () {
    Storage::fake('public');
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);

    $screen = Livewire::test(SiteBuilder::class);
    $key = array_values(array_filter(array_keys($screen->get('data.blocks')), fn ($k) => $screen->get("data.blocks.{$k}.type") === 'form'))[0];
    $screen->call('edit', $screen->get("data.blocks.{$key}.data.id"))
        ->assertSee(__('site::builder.form.words'))
        ->assertSee(__('site::builder.fields.eyebrow_on'))
        ->assertSee(__('site::builder.fields.whatsapp_label'))
        ->assertSee(__('site::builder.fields.who'))
        ->assertSee(__('site::builder.options.who.children'))
        ->assertSee(__('site::builder.form.sources'))
        ->assertSee(__('site::builder.form.open_trials'))
        ->assertSee(__('site::builder.form.open_subjects'));

    $screen->set("data.blocks.{$key}.data.content.who", 'children')
        ->set("data.blocks.{$key}.data.content.whatsapp_label", ['ar' => 'اسألنا', 'en' => 'Ask us'])
        ->call('saveDraft')->assertHasNoErrors();
    $form = array_values(array_filter(SitePage::current()->draftDoc()['blocks'], fn (array $b) => $b['type'] === 'form'))[0];
    expect($form['data']['who'])->toBe('children')
        ->and($form['data']['whatsapp_label'])->toBe(['ar' => 'اسألنا', 'en' => 'Ask us']);

    // On a page for adults the choice gives way to why.
    $screen->set('settings.audience', 'adults')
        ->assertSee(__('site::builder.form.adults_page'))
        ->assertDontSee(__('site::builder.options.who.children'));
});
