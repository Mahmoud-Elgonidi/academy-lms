<?php

use Modules\Site\Domain\Page\Blocks;
use Modules\Site\Domain\Page\InvalidPage;
use Modules\Site\Domain\Page\PageDoc;

/*
 * The page's document (Site\Domain\Page\PageDoc, pure): known fields kept and
 * the rest dropped, every word cleaned and held to its length, ids made for
 * blocks without one (or with a taken one), unknown types dropped and
 * unknown layouts turned into the type's first, links held to their rules,
 * "never set" kept apart from "none", and a size limit.
 */

function pdBlock(string $type, array $data = [], array $extra = []): array
{
    return ['type' => $type, 'data' => $data] + $extra;
}

function pdProblems(array $doc): array
{
    try {
        PageDoc::normalize($doc);
    } catch (InvalidPage $e) {
        return $e->problems;
    }

    return [];
}

it('keeps the known fields, cleans the words and fills what is missing', function () {
    $doc = PageDoc::normalize(['blocks' => [pdBlock('hero', [
        'title' => ['ar' => "  تعلّم\u{0007} معنا  ", 'en' => 'Learn'],
        'unknown' => 'dropped',
    ], ['variant' => 'nope', 'style' => ['tone' => 'neon', 'align' => 'center']])]]);
    $hero = $doc['blocks'][0];

    expect($doc['v'])->toBe(1)
        ->and($hero['variant'])->toBe('centered')
        ->and($hero['style'])->toBe(['tone' => 'paper', 'align' => 'center', 'space' => 'normal', 'width' => 'normal', 'eyebrow' => false])
        ->and($hero['data']['title'])->toBe(['ar' => 'تعلّم معنا', 'en' => 'Learn'])
        ->and($hero['data'])->not->toHaveKey('unknown')
        ->and($hero['data'])->not->toHaveKey('trust')   // never set: the portal's words
        ->and($hero['data']['primary'])->toBe(['label' => ['ar' => '', 'en' => ''], 'action' => ['kind' => 'trial']])
        ->and($hero['data']['secondary']['action'])->toBe(['kind' => 'sign_in'])
        ->and($hero['data']['show'])->toBe(['kicker' => true, 'lead' => true, 'buttons' => true, 'trust' => true, 'media' => true])
        ->and($hero['id'])->toMatch(Blocks::ID)
        ->and($doc['settings']['header']['cta']['action'])->toBe(['kind' => 'trial'])
        ->and($doc['settings']['audience'])->toBe('families');

    // The phones and video openings are dark unless told otherwise.
    expect(PageDoc::normalize(['blocks' => [pdBlock('hero', [], ['variant' => 'phones'])]])['blocks'][0]['style']['tone'])->toBe('night');
});

it('keeps ids, makes new ones for missing or taken ids, and drops unknown types', function () {
    $doc = PageDoc::normalize(['blocks' => [
        pdBlock('faq', [], ['id' => 'abcd1234']),
        pdBlock('faq', [], ['id' => 'abcd1234']),   // a copy: a new id
        pdBlock('robots'),
        pdBlock('cta', [], ['id' => 'NOT-OK']),
    ]]);
    $ids = array_column($doc['blocks'], 'id');

    expect($doc['blocks'])->toHaveCount(3)
        ->and($ids[0])->toBe('abcd1234')
        ->and($ids[1])->not->toBe('abcd1234')->toMatch(Blocks::ID)
        ->and($ids[2])->toMatch(Blocks::ID);
});

it('keeps old anchors once each, never one that looks like a block id', function () {
    $doc = PageDoc::normalize(['blocks' => [
        pdBlock('faq', [], ['anchor' => 'faq']), pdBlock('faq', [], ['anchor' => 'faq']), pdBlock('cta', [], ['anchor' => 'b-x']), pdBlock('stats', [], ['anchor' => 'Bad Anchor']),
    ]]);

    expect($doc['blocks'][0]['anchor'])->toBe('faq')
        ->and($doc['blocks'][1])->not->toHaveKey('anchor')
        ->and($doc['blocks'][2])->not->toHaveKey('anchor')
        ->and($doc['blocks'][3])->not->toHaveKey('anchor')
        ->and(PageDoc::anchor($doc['blocks'][0]))->toBe('faq')
        ->and(PageDoc::anchor($doc['blocks'][1]))->toBe('b-'.$doc['blocks'][1]['id'])
        ->and(PageDoc::anchor(['id' => 'x', 'type' => 'form']))->toBe('join');
});

it('holds every word to its length, and each list to its size', function () {
    expect(pdProblems(['blocks' => [pdBlock('hero', ['title' => ['ar' => str_repeat('ب', 121)]])]]))->toHaveKey('blocks.0.data.title.ar')
        ->and(pdProblems(['blocks' => [pdBlock('hero', ['title' => ['ar' => str_repeat('ب', 120)]])]]))->toBe([])
        ->and(pdProblems(['blocks' => [pdBlock('faq', ['items' => array_fill(0, 21, ['q' => ['ar' => 'س'], 'a' => ['ar' => 'ج']])])]]))->toHaveKey('blocks.0.data.items')
        ->and(pdProblems(['blocks' => [pdBlock('faq', ['items' => [['q' => ['en' => str_repeat('q', 161)]]]])]]))->toHaveKey('blocks.0.data.items.0.q.en')
        ->and(pdProblems(['blocks' => [pdBlock('hero', ['trust' => array_fill(0, 5, ['ar' => 'x'])])]]))->toHaveKey('blocks.0.data.trust')
        ->and(pdProblems(['blocks' => [pdBlock('contact', ['email' => 'not-an-email', 'phone' => '0501234567'])]]))->toHaveKeys(['blocks.0.data.email', 'blocks.0.data.phone'])
        ->and(PageDoc::normalize(['blocks' => [pdBlock('contact', ['phone' => '00966 50-123 4567'])]])['blocks'][0]['data']['phone'])->toBe('+966501234567');
});

it('takes only safe links', function () {
    $link = fn (array $action) => pdProblems(['blocks' => [pdBlock('cta', ['primary' => ['label' => ['ar' => 'اذهب'], 'action' => $action]])]]);

    foreach (['javascript:alert(1)', 'data:text/html,hi', 'http://example.com', 'ftp://x.y', '//evil.com', '/fr/page', 'https://'] as $bad) {
        expect($link(['kind' => 'url', 'url' => $bad]))->toHaveKey('blocks.0.data.primary.action.url');
    }
    foreach (['https://example.com/a?b=c', 'mailto:hello@example.com', 'tel:+966501234567', '/ar/sign-in', '/en'] as $good) {
        expect($link(['kind' => 'url', 'url' => $good]))->toBe([]);
    }
    expect($link(['kind' => 'block', 'block' => 'no']))->toHaveKey('blocks.0.data.primary.action.block')
        ->and(PageDoc::normalize(['blocks' => [pdBlock('cta', ['primary' => ['action' => ['kind' => 'teleport']]])]])['blocks'][0]['data']['primary'])->toBeNull()
        ->and(PageDoc::normalize(['blocks' => [pdBlock('hero', ['secondary' => null])]])['blocks'][0]['data']['secondary'])->toBeNull();
});

it('keeps a list never set apart from one cleared on purpose', function () {
    $doc = PageDoc::normalize(['blocks' => [pdBlock('hero'), pdBlock('hero', ['trust' => []]), pdBlock('steps'), pdBlock('steps', ['items' => []])]]);

    expect($doc['blocks'][0]['data'])->not->toHaveKey('trust')
        ->and($doc['blocks'][1]['data']['trust'])->toBe([])
        ->and($doc['blocks'][2]['data'])->not->toHaveKey('items')
        ->and($doc['blocks'][3]['data']['items'])->toBe([]);
});

it('reads videos from YouTube and Vimeo links, and media by id', function () {
    $video = fn (array $source) => PageDoc::normalize(['blocks' => [pdBlock('video', ['source' => $source])]])['blocks'][0]['data']['source'];
    $id = '0190a8b2-0000-7000-8000-000000000001';

    expect($video(['kind' => 'youtube', 'id' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3']))->toBe(['kind' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'poster' => null])
        ->and($video(['kind' => 'youtube', 'id' => 'https://youtu.be/dQw4w9WgXcQ'])['id'])->toBe('dQw4w9WgXcQ')
        ->and($video(['kind' => 'vimeo', 'id' => 'https://vimeo.com/123456789'])['id'])->toBe('123456789')
        ->and($video(['kind' => 'upload', 'video' => ['media' => $id], 'poster' => $id]))->toBe(['kind' => 'upload', 'video' => ['media' => $id, 'alt' => null], 'poster' => ['media' => $id, 'alt' => null]])
        ->and($video(['kind' => 'upload', 'video' => ['media' => 'not-a-uuid']])['video'])->toBeNull()
        ->and(pdProblems(['blocks' => [pdBlock('video', ['source' => ['kind' => 'youtube', 'id' => 'https://evil.com/x']])]]))->toHaveKey('blocks.0.data.source.id');
});

it('cleans the page settings: fonts, social accounts on their own network, the number', function () {
    $settings = PageDoc::normalize(['settings' => [
        'audience' => 'adults', 'fonts' => ['heading' => 'Readex Pro', 'body' => 'Comic Sans'], 'mood' => 'neon',
        'footer' => ['social' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/academy']], 'links' => [['label' => ['ar' => 'المدونة'], 'url' => 'https://blog.example.com']]],
        'whatsapp' => '+966 50 000 0000', 'floating_whatsapp' => '0',
    ]], ['Readex Pro', 'Cairo'])['settings'];

    expect($settings['audience'])->toBe('adults')
        ->and($settings['fonts'])->toBe(['heading' => 'Readex Pro', 'body' => null])
        ->and($settings['mood'])->toBe('light')
        ->and($settings['whatsapp'])->toBe('+966500000000')
        ->and($settings['floating_whatsapp'])->toBeFalse()
        ->and($settings['footer']['social'])->toBe([['network' => 'instagram', 'url' => 'https://www.instagram.com/academy']]);

    expect(pdProblems(['settings' => ['footer' => ['social' => [['network' => 'x', 'url' => 'https://evil.com/x']]]]]))->toHaveKey('settings.footer.social.0.url')
        ->and(pdProblems(['settings' => ['whatsapp' => '12345']]))->toHaveKey('settings.whatsapp')
        ->and(pdProblems(['settings' => ['footer' => ['links' => array_fill(0, 7, ['label' => ['ar' => 'x'], 'url' => '/ar'])]]]))->toHaveKey('settings.footer.links');
});

it('refuses a page larger than 256 KB', function () {
    $big = array_fill(0, 40, pdBlock('faq', ['items' => array_fill(0, 20, ['q' => ['ar' => str_repeat('س', 160), 'en' => str_repeat('q', 160)], 'a' => ['ar' => str_repeat('ج', 400)]])]));

    expect(pdProblems(['blocks' => $big]))->toHaveKey('blocks');
});
