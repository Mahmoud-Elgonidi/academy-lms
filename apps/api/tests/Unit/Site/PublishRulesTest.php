<?php

use Modules\Site\Domain\Page\Facts;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Domain\Page\PublishRules;

/*
 * Whether a draft may go live (Site\Domain\Page\PublishRules, pure): what
 * stops it (too many blocks, a second hero, a required word missing in both
 * languages, a video without its poster, a file that is not the academy's, a
 * button to nowhere, nothing to show) and what is only worth a look (no
 * form, one language only, small pictures, a JPEG logo, testimonials with
 * none, the opening not first, a button to a hidden block).
 */

const PR_IMAGE = '0190a8b2-0000-7000-8000-00000000000a';
const PR_SMALL = '0190a8b2-0000-7000-8000-00000000000b';
const PR_VIDEO = '0190a8b2-0000-7000-8000-00000000000c';
const PR_JPEG = '0190a8b2-0000-7000-8000-00000000000d';

function prFacts(): Facts
{
    return new Facts([
        PR_IMAGE => ['kind' => 'image', 'mime' => 'image/webp', 'width' => 2400, 'height' => 1600],
        PR_SMALL => ['kind' => 'image', 'mime' => 'image/png', 'width' => 400, 'height' => 300],
        PR_VIDEO => ['kind' => 'video', 'mime' => 'video/mp4', 'width' => null, 'height' => null],
        PR_JPEG => ['kind' => 'image', 'mime' => 'image/jpeg', 'width' => 600, 'height' => 240],
    ]);
}

function prDoc(array $blocks, array $settings = []): array
{
    return PageDoc::normalize(['settings' => $settings, 'blocks' => $blocks]);
}

function prHero(array $data = [], array $extra = []): array
{
    return ['type' => 'hero', 'variant' => 'split', 'data' => $data + ['title' => ['ar' => 'عنوان', 'en' => 'Title']]] + $extra;
}

function prForm(): array
{
    return ['type' => 'form', 'data' => []];
}

it('lets a whole page through, worth a look at most', function () {
    $verdict = PublishRules::check(prDoc([prHero(['media' => PR_IMAGE]), prForm()]), prFacts());

    expect($verdict->ok())->toBeTrue()->and($verdict->codes())->toBe([]);
});

it('stops a page with nothing to show, too many blocks, or a second opening', function () {
    expect(PublishRules::check(prDoc([prHero([], ['hidden' => true])]), prFacts())->codes())->toContain('nothing_visible')
        ->and(PublishRules::check(prDoc(array_fill(0, 41, ['type' => 'spacer'])), prFacts())->codes())->toContain('too_many_blocks')
        ->and(PublishRules::check(prDoc([prHero(), prHero(), prForm()]), prFacts())->codes())->toContain('second_of')
        // A hidden second one is a spare, not a second.
        ->and(PublishRules::check(prDoc([prHero(), prHero([], ['hidden' => true]), prForm()]), prFacts())->ok())->toBeTrue();
});

it('stops a required word missing in both languages, and a list under its least', function () {
    $verdict = PublishRules::check(prDoc([
        ['type' => 'hero', 'data' => ['title' => ['ar' => '', 'en' => '']]],
        ['type' => 'faq', 'data' => ['items' => [['q' => ['ar' => 'سؤال'], 'a' => ['ar' => '', 'en' => '']]]]],
        ['type' => 'stats', 'data' => ['items' => [['value' => ['ar' => '1'], 'label' => ['ar' => 'x']]]]],
        ['type' => 'cta', 'data' => ['title' => ['en' => 'Go'], 'primary' => null]],
        prForm(),
    ]), prFacts());

    $fields = array_map(fn ($b) => $b['type'].':'.$b['code'].':'.$b['field'], $verdict->blockers);
    expect($verdict->ok())->toBeFalse()
        ->and($fields)->toContain('hero:required:title', 'faq:required:items.0.a', 'stats:too_few:items', 'cta:required:primary');
});

it('stops a video without a poster, and a file that is not the academy\'s or of the wrong kind', function () {
    expect(PublishRules::check(prDoc([prHero(['video' => PR_VIDEO]), prForm()]), prFacts())->codes())->toContain('poster')
        ->and(PublishRules::check(prDoc([prHero(['video' => PR_VIDEO, 'poster' => PR_IMAGE]), prForm()]), prFacts())->ok())->toBeTrue()
        ->and(PublishRules::check(prDoc([['type' => 'video', 'data' => ['source' => ['kind' => 'upload', 'video' => PR_VIDEO]]], prForm()]), prFacts())->codes())->toContain('poster')
        ->and(PublishRules::check(prDoc([prHero(['media' => PR_VIDEO]), prForm()]), prFacts())->codes())->toContain('media_wrong')
        ->and(PublishRules::check(prDoc([prHero(['media' => '0190a8b2-0000-7000-8000-0000000000ff']), prForm()]), prFacts())->codes())->toContain('media_wrong')
        ->and(PublishRules::check(prDoc([prHero(), prForm()], ['seo' => ['image' => PR_VIDEO]]), prFacts())->codes())->toContain('media_wrong');
});

it('stops a button to a block that is not on the page, and warns of one to a hidden block', function () {
    $doc = prDoc([prHero(['primary' => ['label' => ['ar' => 'اذهب'], 'action' => ['kind' => 'block', 'block' => 'zzzzzzzz']]]), prForm()]);
    expect(PublishRules::check($doc, prFacts())->codes())->toContain('link_block');

    $doc = prDoc([prHero(['primary' => ['label' => ['ar' => 'اذهب'], 'action' => ['kind' => 'block', 'block' => 'faq00001']]]), ['id' => 'faq00001', 'type' => 'faq', 'hidden' => true, 'data' => ['items' => []]], prForm()]);
    expect(PublishRules::check($doc, prFacts()))->ok()->toBeTrue()
        ->and(PublishRules::check($doc, prFacts())->codes())->toContain('link_hidden');
});

it('warns: no form, one language, small pictures, a JPEG logo, no testimonials, the opening not first', function () {
    $codes = PublishRules::check(prDoc([
        ['type' => 'stats', 'data' => ['items' => [['value' => ['ar' => '1'], 'label' => ['ar' => 'واحد']], ['value' => ['ar' => '2', 'en' => '2'], 'label' => ['ar' => 'اثنان', 'en' => 'Two']]]]],
        prHero(['media' => PR_SMALL]),
        ['type' => 'gallery', 'data' => ['items' => [['media' => PR_SMALL], ['media' => PR_IMAGE]]]],
        ['type' => 'logos', 'data' => ['items' => [['media' => PR_JPEG, 'name' => 'A'], ['media' => PR_IMAGE, 'name' => 'B']]]],
        ['type' => 'testimonials', 'data' => ['items' => []]],
    ], ['seo' => ['image' => PR_SMALL]]), prFacts())->codes();

    expect($codes)->toContain('no_form', 'one_language', 'image_small', 'logo_jpeg', 'testimonials_empty', 'hero_not_first', 'share_small')
        ->not->toContain('second_of');
});
