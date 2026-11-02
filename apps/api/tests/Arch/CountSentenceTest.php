<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

/*
 * A number and what it counts agree in Arabic: one, two, few (3 to 10) and
 * many (11 and more) read «حصة واحدة», «حصتان», «3 حصص», «11 حصة» (and
 * English one lesson, 2 lessons). The balance, the collection messages, the
 * start flow and «إضافة طفل» (docs/architecture/02, section 27) write their
 * counts through trans_choice (`{1}…|{2}…|[3,10]…|[11,*]…`); this renders
 * each for 1, 2, 3 and 11, and refuses a count before its noun in those files
 * that is not a choice.
 */

/** Translation key => [what each count reads in Arabic (1, 2, 3, 11), in English (1, 2), the other placeholders]. */
const COUNT_SENTENCES = [
    'billing::collections.balance.lessons_left' => [['بقيت حصة واحدة', 'بقيت حصتان', 'بقيت 3 حصص', 'بقيت 11 حصة'], ['One lesson left', '2 lessons left'], []],
    'billing::collections.balance.expiry_soon' => [['صلاحية حصة واحدة في', 'صلاحية حصتين في', 'صلاحية 3 حصص في', 'صلاحية 11 حصة في'], ['One of the lessons expires', '2 of the lessons expire'], ['on' => 'X']],
    'billing::collections.balance.next_invoice' => [['تبقى حصة واحدة،', 'تبقى حصتان،', 'تبقى 3 حصص،', 'تبقى 11 حصة،'], ['when one lesson is left', 'when 2 lessons are left'], ['amount' => 'A', 'on' => 'X']],
    'billing::collections.errors.just_sent' => [['بعد دقيقة واحدة.', 'بعد دقيقتين.', 'بعد 3 دقائق.', 'بعد 11 دقيقة.'], ['in one minute.', 'in 2 minutes.'], []],
    'billing::collections.count.lessons' => [['حصة واحدة', 'حصتان', '3 حصص', '11 حصة'], ['one lesson', '2 lessons'], []],
    'billing::collections.count.lessons_after' => [['حصة واحدة', 'حصتين', '3 حصص', '11 حصة'], ['one lesson', '2 lessons'], []],
    'billing::collections.count.days_late' => [['يومًا واحدًا', 'يومين', '3 أيام', '11 يومًا'], ['one day', '2 days'], []],
    'scheduling::start.why.times_count' => [['اختاروا موعدًا أسبوعيًا واحدًا.', 'اختاروا موعدين أسبوعيين،', 'اختاروا 3 مواعيد أسبوعية،', 'اختاروا 11 موعدًا أسبوعيًا،'], ['Choose one weekly time.', 'Choose 2 weekly times,'], []],
    'scheduling::start.pay.by' => [['بساعة واحدة تُلغى.', 'بساعتين تُلغى.', 'بـ3 ساعات تُلغى.', 'بـ11 ساعة تُلغى.'], ['unpaid one hour before', 'unpaid 2 hours before'], ['when' => 'X']],
    'scheduling::start.renews' => [['حين تبقى حصة واحدة،', 'حين تبقى حصتان،', 'حين تبقى 3 حصص،', 'حين تبقى 11 حصة،'], ['when one lesson is left', 'when 2 lessons are left'], []],
    'people::family.add_child.errors.too_many' => [['في حسابكم ابن واحد،', 'في حسابكم ابنان،', 'في حسابكم 3 أبناء،', 'في حسابكم 11 ابنًا،'], ['has one child,', 'has 2 children,'], []],
];

/** The lang files these sentences live in: a count there is always a choice. */
const COUNT_FILES = ['Billing/lang/ar/collections.php', 'Billing/lang/ar/notifications.php', 'Scheduling/lang/ar/start.php', 'People/lang/ar/family.php'];

/** @return list<array{0: string, 1: string}> every string in a lang file, with its key */
function countSentenceStrings(array $node, string $path = ''): array
{
    $out = [];
    foreach ($node as $key => $value) {
        $here = $path === '' ? (string) $key : $path.'.'.$key;
        if (is_array($value)) {
            array_push($out, ...countSentenceStrings($value, $here));
        } elseif (is_string($value)) {
            $out[] = [$here, $value];
        }
    }

    return $out;
}

function countTranslator(string $locale): Translator
{
    $root = dirname(__DIR__, 2);
    $loader = new FileLoader(new Filesystem, $root.'/lang');
    foreach (['billing' => 'Billing', 'scheduling' => 'Scheduling', 'people' => 'People'] as $namespace => $module) {
        $loader->addNamespace($namespace, $root.'/modules/'.$module.'/lang');
    }

    return new Translator($loader, $locale);
}

it('writes each count sentence for one, two, a few and many, in Arabic and English', function () {
    $ar = countTranslator('ar');
    $en = countTranslator('en');
    foreach (COUNT_SENTENCES as $key => [$arabic, $english, $replace]) {
        foreach ([1, 2, 3, 11] as $i => $count) {
            $text = $ar->choice($key, $count, $replace);
            expect($text)->toContain($arabic[$i])->not->toContain(':count')->not->toContain('|')->not->toContain('{');
        }
        foreach ([1, 2] as $i => $count) {
            expect($en->choice($key, $count, $replace))->toContain($english[$i])->not->toContain(':count')->not->toContain('|');
        }
        expect($en->choice($key, 11, $replace))->toContain('11 ')->not->toContain('|');
    }
});

it('puts no bare number before a counted word in the balance, the collection messages, the start flow or adding a child', function () {
    $root = dirname(__DIR__, 2).'/modules/';
    // ":lessons حصص", ":hours ساعة", ":count من الحصص": a number whose word cannot agree with it.
    $pattern = '/:(?:count|lessons|mark|hours|minutes|days|max|weeks|n)\s+(?:من\s+(?:ال)?)?(?:حصص|حصة|ساعة|ساعات|دقيقة|دقائق|أيام|يومًا|يوم|أبناء|ابنًا|مواعيد|موعدًا)(?![\p{L}])/u';
    $wrong = [];
    foreach (COUNT_FILES as $file) {
        foreach (countSentenceStrings(require $root.$file) as [$key, $text]) {
            if (! str_contains($text, '|') && preg_match($pattern, $text) === 1) {
                $wrong[] = $file.' '.$key;
            }
        }
    }

    expect($wrong)->toBe([]);
});
