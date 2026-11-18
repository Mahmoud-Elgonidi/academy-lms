<?php

use Illuminate\Support\Facades\Mail;
use Modules\Site\Actions\UploadSiteMedia;
use Modules\Site\Domain\Page\Templates;
use Tests\Support\AcademySite;
use Tests\Support\SitePages;

uses(AcademySite::class, SitePages::class);

/*
 * A page for adults (site-builder.md, 9.2): an academy that trains adults
 * (risk management, languages for work) never speaks of children, parents
 * or families on its page. The training and online courses templates, with
 * every block they hold shown, a teachers block that shows ages and the
 * phones opening, say none of those words in either language, and a
 * teacher who also teaches children shows no child's age group there.
 */

/** The words a page for adults never says, as whole words (Arabic with its attached letters). */
function adultsRefused(string $text): array
{
    $ar = '/(?<![\x{0621}-\x{065F}\x{0671}-\x{06D3}])[وفب]?(?:لل|ال|ل|ك)?(?:ابن|ابنك|ابنتك|ابني|ابنتي|ابنة|أبناء|أبنائك|أبنائكم|طفل|طفلك|طفلة|أطفال|أطفالك|ولي الأمر|ولي أمر|ولية الأمر|ولية أمر|أولياء|أسرة|أسرتك|أسر|عائلة|عائلتك|عائلات|صغار)(?![\x{0621}-\x{065F}\x{0671}-\x{06D3}])/u';
    $en = '/\b(?:child|children|kid|kids|parent|parents|guardian|guardians|family|families|son|sons|daughter|daughters)\b/i';
    preg_match_all($ar, $text, $a);
    preg_match_all($en, $text, $e);

    return [...$a[0], ...$e[0]];
}

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy();
    $this->actingAs($this->owner);
});

it('says nothing of children, parents or families on the training and online courses pages, in either language', function (string $template) {
    $doc = $this->inNoor(fn () => Templates::make($template, 'adults'));
    $blocks = [];
    foreach ($doc['blocks'] as $block) {
        if ($block['type'] === 'hero') {
            $block['variant'] = 'phones';
        }
        $blocks[] = $block;
    }
    // Every live block an academy may add, as it comes, with the ages shown.
    $have = array_column($blocks, 'type');
    foreach (['teachers' => ['show_ages' => true], 'steps' => [], 'app' => [], 'pricing' => [], 'testimonials' => ['items' => [['name' => 'Rana', 'quote' => ['ar' => 'دورة مفيدة جدًا.', 'en' => 'A very useful course.']]]], 'faq' => ['items' => [['q' => ['ar' => 'س؟', 'en' => 'Q?'], 'a' => ['ar' => 'ج.', 'en' => 'A.']]]], 'contact' => [], 'subjects' => []] as $type => $data) {
        if (! in_array($type, $have, true)) {
            $blocks[] = ['type' => $type, 'data' => $data];
        }
    }
    $this->inNoor(fn () => $this->publishPage($blocks, $doc['settings']));

    foreach (['ar', 'en'] as $locale) {
        $data = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => $locale])->assertOk()->json('data');
        $said = json_encode([$data['page'], $data['teachers'], $data['content']], JSON_UNESCAPED_UNICODE);

        expect($data['page']['settings']['audience'])->toBe('adults')
            ->and($data['teachers'])->not->toBe([])
            ->and(adultsRefused($said))->toBe([], "{$template} in {$locale}");
        foreach ($data['teachers'] as $teacher) {
            expect($teacher['age_groups'])->toBe([]);   // they teach kids and teens: nothing about ages here
        }
    }
})->with(['training', 'online_courses']);

it('starts the adult templates with the teachers\' ages off and a cards block of programmes', function () {
    foreach (['online_courses', 'training'] as $key) {
        $doc = $this->inNoor(fn () => Templates::make($key, 'adults'));
        $types = array_column($doc['blocks'], 'type');
        $cards = $doc['blocks'][array_search('cards', $types, true)];
        expect($cards['data']['items'])->toHaveCount(3)
            ->and($cards['hidden'])->toBeFalse();
        foreach ($cards['data']['items'] as $item) {
            expect($item['title']['ar'])->not->toBe('')->and($item['title']['en'])->not->toBe('')
                ->and($item['label']['ar'])->not->toBe('')->and($item['media'])->toBeNull();
        }
        foreach ($doc['blocks'] as $block) {
            if ($block['type'] === 'teachers') {
                expect($block['data']['show_ages'])->toBeFalse();
            }
        }
    }
});

it('keeps every age group on a page for families', function () {
    $this->inNoor(fn () => $this->publishPage([['type' => 'teachers', 'data' => ['show_ages' => true]], ['type' => 'form']], ['audience' => 'families']));

    $teachers = $this->getJson('http://noor.lms.test/api/v1/site')->assertOk()->json('data.teachers');
    expect($teachers)->not->toBe([])->and($teachers[0]['age_groups'])->toBe(['kids', 'teen']);

    // The words the adults' check looks for are there on a families page: the check sees them.
    $kids = $this->inNoor(fn () => json_encode(Templates::make('kids_families'), JSON_UNESCAPED_UNICODE));
    expect(adultsRefused($kids))->toContain('ولي الأمر', 'للأسرة')
        ->and(adultsRefused('Your child, his parents and the family'))->toBe(['child', 'parents', 'family'])
        ->and(adultsRefused('أسرع طريق للأسرة، وطفلك، وولية أمر'))->toBe(['للأسرة', 'وطفلك', 'وولية أمر']);
});

it('shows a cards block\'s own courses with their pictures and buttons', function () {
    $media = $this->inNoor(fn () => app(UploadSiteMedia::class)->handle($this->siteFile('study.webp')));
    $this->inNoor(fn () => $this->publishPage([
        ['type' => 'cards', 'variant' => 'overlay', 'data' => ['columns' => 2, 'title' => ['ar' => 'دوراتنا', 'en' => 'Our courses'], 'items' => [
            ['media' => ['media' => $media->id], 'label' => ['ar' => '6 أسابيع', 'en' => '6 weeks'], 'title' => ['ar' => 'إدارة المخاطر', 'en' => 'Risk management'],
                'link' => ['label' => ['ar' => 'احجز', 'en' => 'Book'], 'action' => ['kind' => 'trial']]],
            ['title' => ['ar' => 'الامتثال والحوكمة', 'en' => 'Compliance and governance'], 'link' => ['label' => [], 'action' => ['kind' => 'block', 'block' => 'contact1']]],
        ]]],
        ['id' => 'contact1', 'type' => 'contact', 'data' => ['email' => 'hello@noor.example']],
        ['type' => 'form'],
    ], ['audience' => 'adults']));

    $cards = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk()->json('data.page.blocks.0');
    expect($cards['type'])->toBe('cards')
        ->and($cards['variant'])->toBe('overlay')
        ->and($cards['data']['columns'])->toBe(2)
        ->and($cards['data']['title'])->toBe('Our courses')
        ->and($cards['data']['items'][0]['title'])->toBe('Risk management')
        ->and($cards['data']['items'][0]['label'])->toBe('6 weeks')
        ->and($cards['data']['items'][0]['media']['url'])->toContain('/media/')
        ->and($cards['data']['items'][0]['link'])->toBe(['label' => 'Book', 'action' => ['kind' => 'trial']])
        ->and($cards['data']['items'][1]['media'])->toBeNull()
        ->and($cards['data']['items'][1]['link'])->toBe(['label' => '', 'action' => ['kind' => 'block', 'block' => 'b-contact1']]);
});
