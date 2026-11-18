<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Site\Actions\SaveSiteDraft;
use Modules\Site\Domain\Page\LegacyContent;
use Modules\Site\Domain\Page\LegacyImport;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SiteSettings;
use Tests\Support\AcademySite;
use Tests\Support\SitePages;

uses(AcademySite::class, SitePages::class);

/*
 * GET v1/site with the page builder (docs/architecture/site-builder.md, 3.1):
 * `page` holds the published page in the visitor's language (visible blocks
 * only, in order); the teachers and plans are filled only for a visible
 * block that shows them, with its choice and order; the subjects are always
 * all of them (the app and the form need them); `content` keeps its old
 * shape. An academy that never built a page reads its old one, unchanged.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy();
});

it('reads an academy\'s old page while it has built none, exactly as before', function () {
    $page = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk();
    $doc = $this->inNoor(fn () => LegacyImport::fromContent(SiteSettings::current()->said()));

    expect($page->json('data.page.revision'))->toBe(1)
        ->and(array_column($page->json('data.page.blocks'), 'type'))->toBe(['hero', 'features', 'subjects', 'teachers', 'steps', 'app', 'pricing', 'faq', 'form'])
        ->and($page->json('data.page.blocks.0.variant'))->toBe('phones')
        ->and($page->json('data.page.blocks.1.anchor'))->toBe('why')
        ->and($page->json('data.page.blocks.8.anchor'))->toBe('join')
        ->and($page->json('data.page.blocks.0.data.title'))->toBe(__('site::site.defaults.hero.title', [], 'en'))
        ->and($page->json('data.content'))->toBe(SiteContent::localized($this->inNoor(fn () => SiteSettings::current()->said()), 'en'))
        ->and($page->json('data.content'))->toBe(LegacyContent::from($doc, 'en'))
        ->and($page->json('data.teachers'))->toHaveCount(1)
        ->and($page->json('data.plans'))->toHaveCount(1);
});

it('shows the published page in the visitor\'s language: visible blocks only, in order, every field there', function () {
    $this->actingAs($this->owner);
    $this->inNoor(fn () => $this->publishPage([
        ['type' => 'hero', 'variant' => 'minimal', 'nav' => ['ar' => 'البداية', 'en' => 'Start'], 'data' => ['title' => ['ar' => 'دورات للكبار', 'en' => 'Courses for adults']]],
        ['type' => 'faq', 'hidden' => true, 'data' => ['items' => [['q' => ['ar' => 'س'], 'a' => ['ar' => 'ج']]]]],
        ['type' => 'stats', 'data' => ['items' => [['value' => ['ar' => '١٢'], 'label' => ['ar' => 'عامًا', 'en' => 'years']], ['value' => ['en' => '98%'], 'label' => ['en' => 'pass']]]]],
        ['type' => 'form', 'variant' => 'centered', 'data' => []],
    ], ['audience' => 'adults']));

    $en = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk()->json('data');
    $ar = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'ar'])->assertOk()->json('data');

    expect($en['page']['revision'])->toBe(2)
        ->and(array_column($en['page']['blocks'], 'type'))->toBe(['hero', 'stats', 'form'])
        ->and($en['page']['blocks'][0]['nav'])->toBe('Start')
        ->and($ar['page']['blocks'][0]['nav'])->toBe('البداية')
        ->and($en['page']['blocks'][0]['data']['title'])->toBe('Courses for adults')
        ->and($en['page']['blocks'][0]['data']['primary'])->toBe(['label' => '', 'action' => ['kind' => 'trial']])
        ->and($en['page']['blocks'][1]['data']['items'])->toBe([['value' => '١٢', 'label' => 'years'], ['value' => '98%', 'label' => 'pass']])
        ->and($en['page']['blocks'][1]['style'])->toBe(['tone' => 'tint', 'align' => 'start', 'space' => 'normal', 'width' => 'normal', 'eyebrow' => false])
        ->and(array_keys($en['page']['blocks'][0]['data']))->toBe(['kicker', 'title', 'lead', 'primary', 'secondary', 'trust', 'media', 'video', 'poster', 'media_side', 'media_shape', 'scrim', 'show'])
        ->and($en['page']['settings']['audience'])->toBe('adults')
        // No teachers or pricing block: no lists; the subjects stay whole.
        ->and($en['teachers'])->toBe([])->and($en['plans'])->toBe([])
        ->and(array_column($en['subjects'], 'name'))->toContain('Quran', 'Arabic')
        // The old content, made from the page.
        ->and($en['content']['hero']['title'])->toBe('Courses for adults')
        ->and($en['content']['sections'])->toBe([])
        ->and($en['content']['faqs'])->toBe([['q' => 'س', 'a' => 'ج']])
        ->and($en['content']['audience'])->toBe('adults');
});

it('fills the teachers and plans its blocks show, as they chose them, and the subjects block names its own', function () {
    $this->actingAs($this->owner);
    $sara = $this->inNoor(fn () => DB::table('teacher_public_cards')->where('teacher_id', $this->teachers['sara']->id)->value('id'));
    $plan = $this->inNoor(fn () => DB::table('plans')->value('id'));
    $this->inNoor(fn () => $this->publishPage([
        ['type' => 'subjects', 'data' => ['only' => [$this->arabic]]],
        ['type' => 'teachers', 'variant' => 'portraits', 'data' => ['only' => [$sara], 'max' => 4]],
        ['type' => 'pricing', 'data' => ['only' => [$plan], 'highlight' => $plan]],
        ['type' => 'form', 'data' => []],
    ]));

    $data = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk()->json('data');
    expect($data['page']['blocks'][0]['data']['subject_ids'])->toBe([$this->arabic])
        ->and($data['page']['blocks'][0]['data'])->not->toHaveKey('only')
        ->and(array_column($data['teachers'], 'id'))->toBe([$sara])
        ->and(array_column($data['plans'], 'id'))->toBe([$plan])
        ->and($data['page']['blocks'][2]['data']['highlight'])->toBe($plan)
        ->and($data['subjects'])->toHaveCount(2);

    // A hidden teachers block: nobody.
    $this->inNoor(fn () => $this->publishPage([['type' => 'teachers', 'hidden' => true], ['type' => 'form']]));
    expect($this->getJson('http://noor.lms.test/api/v1/site')->json('data.teachers'))->toBe([]);
});

it('shows the draft only once it is published', function () {
    $this->actingAs($this->owner);
    $this->inNoor(function () {
        $this->publishPage([['type' => 'hero', 'variant' => 'minimal', 'data' => ['title' => ['en' => 'Live']]], ['type' => 'form']]);
        app(SaveSiteDraft::class)->handle(['blocks' => [['type' => 'hero', 'data' => ['title' => ['en' => 'Draft']]]]], SitePage::current()->draft_revision);
    });

    expect($this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->json('data.page.blocks.0.data.title'))->toBe('Live');
});

it('is not there while the page is off, built or not', function () {
    $this->actingAs($this->owner);
    $this->inNoor(function () {
        $this->publishPage([['type' => 'hero', 'data' => ['title' => ['en' => 'x']]], ['type' => 'form']]);
        SiteSettings::query()->update(['enabled' => false]);
    });

    $this->getJson('http://noor.lms.test/api/v1/site')->assertNotFound();
});
