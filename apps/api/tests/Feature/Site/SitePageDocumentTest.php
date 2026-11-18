<?php

use Illuminate\Support\Arr;
use Modules\Site\Domain\Page\Facts;
use Modules\Site\Domain\Page\LegacyContent;
use Modules\Site\Domain\Page\LegacyImport;
use Modules\Site\Domain\Page\PublishRules;
use Modules\Site\Domain\Page\Templates;
use Modules\Site\Domain\SiteContent;

/*
 * The page an academy had before the builder, as blocks (LegacyImport): the
 * same page in the same order with its old anchors, sections it switched off
 * hidden, the reasons hidden when there are none, "teach" as the form's tab;
 * and back (LegacyContent): GET v1/site's old `content` is exactly what it
 * was (the backwards-compatibility proof). The templates: each publishable,
 * worded in both languages, and none about the Quran.
 */

/** An academy that filled in everything it could. */
function filledContent(): array
{
    $content = SiteContent::defaults();
    $content['hero'] = ['title_ar' => 'أكاديمية الضياء', 'title_en' => 'Light Academy', 'lead_ar' => 'تعلّم معنا', 'lead_en' => ''];
    $content['audience'] = 'adults';
    $content['why'] = ['title_ar' => 'لماذا نحن', 'title_en' => '', 'lead_ar' => '', 'lead_en' => 'Because'];
    $content['features'] = [['title_ar' => 'أ', 'title_en' => 'A', 'text_ar' => 'نص', 'text_en' => '']];
    $content['testimonials'] = [['name' => 'Huda', 'role_ar' => '', 'role_en' => 'Mother', 'quote_ar' => 'رائع', 'quote_en' => 'Great']];
    $content['faqs'] = [['q_ar' => 'س؟', 'q_en' => 'Q?', 'a_ar' => 'ج', 'a_en' => 'A']];
    $content['sections'] = ['subjects', 'pricing', 'testimonials', 'teach'];
    $content['whatsapp'] = '+966500000000';
    $content['seo'] = ['title_ar' => 'بحث', 'title_en' => 'Search', 'description_ar' => '', 'description_en' => ''];

    return SiteContent::normalize($content);
}

it('turns today\'s page into blocks in today\'s order, with their old anchors', function () {
    $doc = LegacyImport::fromContent(SiteContent::defaults());
    $shape = array_map(fn ($b) => [$b['type'], $b['variant'], $b['anchor'] ?? null, $b['style']['tone'], $b['hidden']], $doc['blocks']);

    expect($shape)->toBe([
        ['hero', 'phones', null, 'night', false],
        ['features', 'cards', 'why', 'white', false],
        ['subjects', 'cards', 'subjects', 'paper', false],
        ['teachers', 'cards', 'teachers', 'white', false],
        ['steps', 'line', 'start', 'paper', false],
        ['app', 'phones', 'app', 'night', false],
        ['pricing', 'cards', 'pricing', 'white', false],
        ['testimonials', 'marquee', 'testimonials', 'paper', true],
        ['faq', 'accordion', 'faq', 'white', false],
        ['form', 'split', 'join', 'night', false],
    ])
        ->and($doc['blocks'][0]['data'])->not->toHaveKey('trust')
        ->and($doc['blocks'][0]['data']['kicker'])->toBe(['ar' => '', 'en' => ''])
        ->and($doc['blocks'][1]['data']['items'])->toHaveCount(6)
        ->and(array_column($doc['blocks'][1]['data']['items'], 'icon'))->toBe(['users', 'report', 'calendar', 'map', 'shield', 'heart'])
        ->and($doc['blocks'][9]['data']['teach'])->toBeTrue()
        // The old menu and the small labels (portal's contract change, 9.1).
        ->and(array_map(fn ($b) => $b['nav']['en'], $doc['blocks']))->toBe(['', 'Why us', 'Subjects', 'Teachers', '', '', 'Pricing', '', 'Questions', ''])
        ->and(array_map(fn ($b) => $b['style']['eyebrow'], $doc['blocks']))->toBe([false, true, true, true, true, true, true, true, true, true])
        ->and($doc['settings']['motion'])->toBe('full')
        ->and($doc['settings']['header']['style'])->toBe('overlay')
        // The same content always makes the same page.
        ->and(LegacyImport::fromContent(SiteContent::defaults()))->toBe($doc);
});

it('hides the sections an academy switched off, and the reasons when it has none', function () {
    $content = filledContent();
    $content['features'] = [];
    $doc = LegacyImport::fromContent($content);
    $hidden = [];
    foreach ($doc['blocks'] as $block) {
        $hidden[$block['type']] = $block['hidden'];
    }

    expect($hidden)->toBe(['hero' => false, 'features' => true, 'subjects' => false, 'teachers' => true, 'steps' => true, 'app' => true, 'pricing' => false, 'testimonials' => false, 'faq' => true, 'form' => false])
        ->and($doc['settings']['audience'])->toBe('adults')
        ->and($doc['settings']['whatsapp'])->toBe('+966500000000');
});

it('gives back the old content exactly, in both languages (the round trip)', function () {
    $demo = SiteContent::defaults();
    $demo['testimonials'] = [['name' => 'أم يوسف', 'role_ar' => 'ولية أمر', 'role_en' => 'A mother', 'quote_ar' => 'كلام', 'quote_en' => 'Words']];
    $demo['sections'] = SiteContent::SECTIONS;
    $demo['whatsapp'] = '+966500000000';

    foreach ([SiteContent::defaults(), SiteContent::normalize($demo), filledContent()] as $content) {
        foreach (['ar', 'en'] as $locale) {
            expect(LegacyContent::from(LegacyImport::fromContent($content), $locale))->toBe(SiteContent::localized($content, $locale));
        }
    }
});

it('offers templates that can be published as they are, worded in both languages, none about the Quran', function () {
    foreach (Templates::KEYS as $key) {
        $doc = Templates::make($key, 'adults');
        $verdict = PublishRules::check($doc, new Facts);
        expect($verdict->blockers)->toBe([], $key)
            ->and($doc['blocks'])->not->toBe([]);

        $words = json_encode($doc, JSON_UNESCAPED_UNICODE);
        expect($words)->not->toContain('site::templates')
            ->and(preg_match('/قرآن|القرآن|حفظ|تلاوة|Quran|Qur\'an/u', $words))->toBe(0, $key);
    }
    expect(Templates::make('online_courses')['settings']['audience'])->toBe('adults')
        ->and(Templates::make('blank', 'adults')['settings']['audience'])->toBe('adults')
        ->and(Templates::make('kids_families')['blocks'][0]['variant'])->toBe('phones')
        ->and(Templates::forAudience('adults'))->toBe('online_courses');

    // Every template word exists in both languages.
    expect(array_keys(Arr::dot(trans('site::templates', [], 'ar'))))->toBe(array_keys(Arr::dot(trans('site::templates', [], 'en'))));
});
