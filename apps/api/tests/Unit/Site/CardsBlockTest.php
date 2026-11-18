<?php

use Modules\Site\Domain\Page\Audience;
use Modules\Site\Domain\Page\Facts;
use Modules\Site\Domain\Page\InvalidPage;
use Modules\Site\Domain\Page\Localize;
use Modules\Site\Domain\Page\MediaRefs;
use Modules\Site\Domain\Page\PageContext;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Domain\Page\PublishRules;

/*
 * The cards block (site-builder.md, 1.3 and 9.2): the academy's own courses,
 * programmes or topics, 1 to 12 cards, each with an optional picture, a
 * required title, a short text, a small label and a button; 2, 3 or 4
 * columns; three designs. Cleaned by PageDoc, checked by PublishRules, sent
 * by Localize and counted by MediaRefs like every other block. And the age
 * groups a page for adults may name (Audience).
 */

const CB_IMAGE = '0190a8b2-0000-7000-8000-0000000000c1';
const CB_SMALL = '0190a8b2-0000-7000-8000-0000000000c2';
const CB_VIDEO = '0190a8b2-0000-7000-8000-0000000000c3';

function cbCard(array $over = []): array
{
    return $over + ['title' => ['ar' => 'إدارة المخاطر', 'en' => 'Risk management'], 'text' => ['ar' => 'ستة أسابيع', 'en' => 'Six weeks']];
}

function cbDoc(array $data, string $variant = 'grid', array $more = []): array
{
    return PageDoc::normalize(['blocks' => [['type' => 'cards', 'variant' => $variant, 'data' => $data], ...$more]]);
}

function cbFacts(): Facts
{
    return new Facts([
        CB_IMAGE => ['kind' => 'image', 'mime' => 'image/webp', 'width' => 1600, 'height' => 1200],
        CB_SMALL => ['kind' => 'image', 'mime' => 'image/png', 'width' => 300, 'height' => 200],
        CB_VIDEO => ['kind' => 'video', 'mime' => 'video/mp4', 'width' => null, 'height' => null],
    ]);
}

it('keeps a card\'s picture, label, title, text and button, and its three designs', function () {
    $doc = cbDoc(['columns' => '4', 'items' => [cbCard([
        'media' => ['media' => CB_IMAGE, 'alt' => ['ar' => 'اجتماع', 'en' => '']],
        'label' => ['ar' => '6 أسابيع', 'en' => '6 weeks'],
        'link' => ['label' => ['ar' => 'اطلب التفاصيل', 'en' => ''], 'action' => ['kind' => 'trial']],
        'secret' => 'dropped',
    ])]], 'overlay');
    $block = $doc['blocks'][0];

    expect($block['variant'])->toBe('overlay')
        ->and($block['style']['tone'])->toBe('white')
        ->and($block['data']['columns'])->toBe(4)
        ->and(array_keys($block['data']['items'][0]))->toBe(['media', 'label', 'title', 'text', 'link'])
        ->and($block['data']['items'][0]['media'])->toBe(['media' => CB_IMAGE, 'alt' => ['ar' => 'اجتماع', 'en' => '']])
        ->and($block['data']['items'][0]['link'])->toBe(['label' => ['ar' => 'اطلب التفاصيل', 'en' => ''], 'action' => ['kind' => 'trial']]);
    expect(cbDoc(['items' => [cbCard()]], 'carousel')['blocks'][0]['variant'])->toBe('grid')     // unknown: the first
        ->and(cbDoc(['items' => [cbCard()], 'columns' => 7])['blocks'][0]['data']['columns'])->toBe(3)
        ->and(cbDoc(['items' => [cbCard()]])['blocks'][0]['data']['items'][0]['link'])->toBeNull();  // no button unless one is chosen
});

it('refuses more than twelve cards, a title past its length and a link to a script', function () {
    $twelve = array_fill(0, 12, cbCard());
    expect(cbDoc(['items' => $twelve])['blocks'][0]['data']['items'])->toHaveCount(12);

    foreach ([
        ['items' => [...$twelve, cbCard()]],
        ['items' => [cbCard(['title' => ['ar' => str_repeat('م', 81), 'en' => '']])]],
        ['items' => [cbCard(['link' => ['label' => [], 'action' => ['kind' => 'url', 'url' => 'javascript:alert(1)']]])]],
    ] as $data) {
        expect(fn () => cbDoc($data))->toThrow(InvalidPage::class);
    }
});

it('stops publishing without a card or a card\'s title, and warns of mixed pictures and small ones', function () {
    $codes = fn (array $doc) => PublishRules::check($doc, cbFacts())->codes();
    $form = ['type' => 'form', 'data' => []];

    expect($codes(cbDoc(['items' => [cbCard(['media' => CB_IMAGE]), cbCard(['media' => CB_IMAGE])]], 'grid', [$form])))->toBe([])
        ->and($codes(cbDoc(['items' => []], 'grid', [$form])))->toContain('too_few')
        ->and($codes(cbDoc(['items' => [cbCard(['title' => ['ar' => '', 'en' => '']])]], 'grid', [$form])))->toContain('required')
        ->and($codes(cbDoc(['items' => [cbCard(['media' => CB_VIDEO])]], 'grid', [$form])))->toContain('media_wrong')
        ->and($codes(cbDoc(['items' => [cbCard(['link' => ['label' => [], 'action' => ['kind' => 'block', 'block' => 'zzzzzzzz']]])]], 'grid', [$form])))->toContain('link_block')
        ->and($codes(cbDoc(['items' => [cbCard(['media' => CB_IMAGE]), cbCard()]], 'grid', [$form])))->toContain('cards_mixed')
        ->and($codes(cbDoc(['items' => [cbCard(['media' => CB_SMALL])]], 'grid', [$form])))->toContain('image_small')
        ->and($codes(cbDoc(['items' => [cbCard(), cbCard()]], 'list', [$form])))->toBe([]);   // none with a picture: fine
});

it('sends each card in the visitor\'s language, its picture as a file and its button only where it can work', function () {
    $ctx = fn (string $locale) => new PageContext(
        locale: $locale,
        media: [CB_IMAGE => ['kind' => 'image', 'mime' => 'image/webp', 'url' => '/storage/x.webp', 'width' => 1600, 'height' => 1200, 'alt' => ['ar' => 'صورة', 'en' => 'A picture']]],
        subjects: [],
        brandFonts: ['heading' => 'Cairo', 'body' => 'Cairo'],
        fontsUrl: fn (string ...$f) => '',
    );
    $doc = cbDoc(['title' => ['ar' => 'برامجنا', 'en' => 'Our programmes'], 'items' => [
        cbCard(['media' => CB_IMAGE, 'label' => ['ar' => 'حسب الطلب', 'en' => ''], 'link' => ['label' => [], 'action' => ['kind' => 'trial']]]),
        cbCard(['link' => ['label' => ['ar' => 'المزيد', 'en' => 'More'], 'action' => ['kind' => 'url', 'url' => 'https://example.com/a']]]),
        ['title' => ['ar' => '', 'en' => ''], 'text' => ['ar' => '', 'en' => '']],
    ]]);

    $en = Localize::page($doc, $ctx('en'))['blocks'][0]['data'];
    expect($en['title'])->toBe('Our programmes')
        ->and($en['columns'])->toBe(3)
        ->and($en['items'])->toHaveCount(2)                                   // the blank row is left out
        ->and($en['items'][0]['label'])->toBe('حسب الطلب')                   // the other language when empty
        ->and($en['items'][0]['media'])->toBe(['url' => '/storage/x.webp', 'width' => 1600, 'height' => 1200, 'alt' => 'A picture', 'mime' => 'image/webp'])
        ->and($en['items'][0]['link'])->toBeNull()                            // no form on the page: no trial button
        ->and($en['items'][1]['media'])->toBeNull()
        ->and($en['items'][1]['label'])->toBeNull()
        ->and($en['items'][1]['link'])->toBe(['label' => 'More', 'action' => ['kind' => 'url', 'url' => 'https://example.com/a']]);

    $withForm = cbDoc($doc['blocks'][0]['data'], 'grid', [['type' => 'form', 'data' => []]]);
    expect(Localize::page($withForm, $ctx('ar'))['blocks'][0]['data']['items'][0]['link'])->toBe(['label' => '', 'action' => ['kind' => 'trial']])
        ->and(MediaRefs::usage($doc))->toBe([CB_IMAGE => ['cards']]);
});

it('names no child\'s age group on a page for adults, and every group on a page for families', function () {
    expect(Audience::ageGroups(['kids', 'teen', 'adults'], 'adults'))->toBe(['adults'])
        ->and(Audience::ageGroups(['kids', 'middle'], 'adults'))->toBe([])
        ->and(Audience::ageGroups([], 'adults'))->toBe([])
        ->and(Audience::ageGroups(['kids', 'teen', 'adults'], 'families'))->toBe(['kids', 'teen', 'adults']);
});
