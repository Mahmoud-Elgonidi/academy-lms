<?php

use Modules\Site\Domain\Page\Localize;
use Modules\Site\Domain\Page\PageContext;
use Modules\Site\Domain\Page\PageDoc;

/*
 * The page in one language for GET v1/site (Site\Domain\Page\Localize, pure):
 * each word in the visitor's language or the other's, null when neither (the
 * portal's own words then); visible blocks only; files as their address,
 * size and alt (gone: null); buttons that cannot work left out; the live
 * blocks' filters kept for the server; every field present.
 */

const LC_IMAGE = '0190a8b2-0000-7000-8000-0000000000a1';
const LC_GONE = '0190a8b2-0000-7000-8000-0000000000a2';

function lcContext(string $locale = 'ar', array $subjects = ['s1', 's2', 's3']): PageContext
{
    return new PageContext(
        locale: $locale,
        media: [LC_IMAGE => ['kind' => 'image', 'mime' => 'image/webp', 'url' => '/storage/site/a/media/x.webp', 'width' => 2400, 'height' => 1600, 'alt' => ['ar' => 'قوس', 'en' => 'An arch']]],
        subjects: $subjects,
        brandFonts: ['heading' => 'Cairo', 'body' => 'Cairo'],
        fontsUrl: fn (string ...$f) => 'https://fonts.example/'.implode(',', $f),
    );
}

function lcPage(array $blocks, array $settings = [], string $locale = 'ar'): array
{
    return Localize::page(PageDoc::normalize(['settings' => $settings, 'blocks' => $blocks]), lcContext($locale));
}

it('says each word in the visitor\'s language, the other\'s when empty, null when neither', function () {
    $hero = ['type' => 'hero', 'variant' => 'split', 'data' => ['title' => ['ar' => 'تعلّم', 'en' => 'Learn'], 'lead' => ['ar' => 'بالعربية فقط', 'en' => ''], 'media' => ['media' => LC_IMAGE]]];

    $ar = lcPage([$hero])['blocks'][0];
    $en = lcPage([$hero], [], 'en')['blocks'][0];

    expect($ar['data']['title'])->toBe('تعلّم')
        ->and($en['data']['title'])->toBe('Learn')
        ->and($en['data']['lead'])->toBe('بالعربية فقط')
        ->and($en['data']['kicker'])->toBeNull()          // the portal's own words
        ->and($en['data']['trust'])->toBeNull()           // never set: default
        ->and($en['data']['media'])->toBe(['url' => '/storage/site/a/media/x.webp', 'width' => 2400, 'height' => 1600, 'alt' => 'An arch', 'mime' => 'image/webp'])
        ->and($en['data']['video'])->toBeNull()
        ->and($en['anchor'])->toBe('b-'.$en['id'])
        ->and($en['nav'])->toBeNull()
        ->and($en['data']['primary'])->toBeNull();        // a trial with no form on the page
    expect(lcPage([['type' => 'hero', 'data' => ['trust' => []]]])['blocks'][0]['data']['trust'])->toBe([]);   // none, on purpose
});

it('sends only the visible blocks, in order, and leaves out what cannot show', function () {
    $page = lcPage([
        ['type' => 'faq', 'hidden' => true, 'data' => ['items' => [['q' => ['ar' => 'س'], 'a' => ['ar' => 'ج']]]]],
        ['type' => 'stats', 'data' => ['items' => [['value' => ['ar' => '1'], 'label' => ['ar' => 'أ']]]]],
        ['type' => 'video', 'data' => ['source' => ['kind' => 'upload', 'video' => ['media' => LC_GONE]]]],
        ['type' => 'gallery', 'data' => ['items' => [['media' => ['media' => LC_GONE]], ['media' => ['media' => LC_IMAGE], 'caption' => ['ar' => 'هنا']]]]],
        ['type' => 'form', 'data' => []],
    ]);

    expect(array_column($page['blocks'], 'type'))->toBe(['stats', 'gallery', 'form'])
        ->and($page['blocks'][1]['data']['items'])->toHaveCount(1)
        ->and($page['blocks'][2]['anchor'])->toBe('join');
});

it('keeps the buttons that can work, and points a block button at its anchor', function () {
    $page = lcPage([
        ['type' => 'hero', 'data' => [
            'title' => ['ar' => 'ع'],
            'primary' => ['label' => ['ar' => 'احجز'], 'action' => ['kind' => 'trial']],
            'secondary' => ['label' => ['ar' => 'الأسئلة'], 'action' => ['kind' => 'block', 'block' => 'faqblock']],
        ]],
        ['type' => 'cta', 'data' => ['title' => ['ar' => 'ابدأ'], 'primary' => ['label' => ['ar' => 'واتساب'], 'action' => ['kind' => 'whatsapp']], 'secondary' => ['label' => ['ar' => 'درّس'], 'action' => ['kind' => 'teach']]]],
        ['id' => 'faqblock', 'type' => 'faq', 'anchor' => 'faq', 'data' => ['items' => [['q' => ['ar' => 'س'], 'a' => ['ar' => 'ج']]]]],
        ['type' => 'form', 'data' => ['teach' => false]],
    ], ['whatsapp' => '']);

    expect($page['blocks'][0]['data']['primary'])->toBe(['label' => 'احجز', 'action' => ['kind' => 'trial']])
        ->and($page['blocks'][0]['data']['secondary'])->toBe(['label' => 'الأسئلة', 'action' => ['kind' => 'block', 'block' => 'faq']])
        ->and($page['blocks'][1]['data']['primary'])->toBeNull()     // no WhatsApp number
        ->and($page['blocks'][1]['data']['secondary'])->toBeNull()   // the form has no teach tab
        ->and($page['settings']['header']['cta'])->toBe(['label' => '', 'action' => ['kind' => 'trial']])   // '' = the portal's words
        ->and($page['settings']['whatsapp'])->toBeNull();
});

it('applies the subjects block\'s choice to the academy\'s subjects, and keeps the filters out', function () {
    $page = lcPage([
        ['type' => 'subjects', 'data' => ['only' => ['0190a8b2-0000-7000-8000-000000000003', '0190a8b2-0000-7000-8000-0000000000ee']]],
        ['type' => 'teachers', 'data' => ['only' => [], 'max' => 3]],
    ]);
    $ctx = lcContext('ar', ['0190a8b2-0000-7000-8000-000000000001', '0190a8b2-0000-7000-8000-000000000003']);
    $all = Localize::page(PageDoc::normalize(['blocks' => [['type' => 'subjects', 'data' => []]]]), $ctx);

    expect($page['blocks'][0]['data'])->not->toHaveKey('only')
        ->and($page['blocks'][1]['data'])->not->toHaveKeys(['only', 'max'])
        ->and(Localize::page(PageDoc::normalize(['blocks' => [['type' => 'subjects', 'data' => ['only' => ['0190a8b2-0000-7000-8000-000000000003', '0190a8b2-0000-7000-8000-0000000000ee']]]]]), $ctx)['blocks'][0]['data']['subject_ids'])
        ->toBe(['0190a8b2-0000-7000-8000-000000000003'])
        ->and($all['blocks'][0]['data']['subject_ids'])->toBe(['0190a8b2-0000-7000-8000-000000000001', '0190a8b2-0000-7000-8000-000000000003']);
});

it('gives the page settings with the fonts it uses, and a stylesheet only for fonts the branding does not load', function () {
    $settings = lcPage([], ['fonts' => ['heading' => 'Readex Pro'], 'seo' => ['title' => ['en' => 'Courses'], 'image' => ['media' => LC_IMAGE]]], 'ar')['settings'];

    expect($settings['fonts'])->toBe(['heading' => 'Readex Pro', 'body' => 'Cairo', 'url' => 'https://fonts.example/Readex Pro,Cairo'])
        ->and(lcPage([])['settings']['fonts']['url'])->toBeNull()
        ->and($settings['seo']['title'])->toBe('Courses')
        ->and($settings['seo']['description'])->toBeNull()
        ->and($settings['seo']['image']['alt'])->toBe('قوس')
        ->and(array_keys($settings))->toBe(['audience', 'fonts', 'accent', 'mood', 'corners', 'motion', 'header', 'footer', 'whatsapp', 'floating_whatsapp', 'seo']);
});
