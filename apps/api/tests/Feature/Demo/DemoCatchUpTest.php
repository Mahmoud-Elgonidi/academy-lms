<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatchUpSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Curriculum\Texts\QuranText;
use Modules\Learning\Contracts\LearningApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Tenancy\TenantContext;

/*
 * A server's demo was made before its lessons could read the Quran (the text
 * comes to the server later, and a server deploys with DEMO_DATA=0 once its
 * demo exists): every deploy brings it up to date. Once the text is there,
 * the Juz' Amma lessons read their surahs, each Quran learner's last lesson
 * has a few marked words, the reports already handed in make their hifz
 * maps, and the demo's plan has the AI helpers' area; each only once. The
 * text here is made up in Tanzil's shape, never the Quran.
 */

/** Tanzil-shaped files with made-up words: 114 surahs, 6,236 verses, 604 pages. */
function catchUpQuranFiles(string $dir): void
{
    $counts = array_fill(1, 113, 55) + [114 => 6236 - 113 * 55];
    $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<quran>\n<suras alias=\"chapters\">\n";
    $text = '';
    foreach ($counts as $surah => $verses) {
        $xml .= "<sura index=\"{$surah}\" ayas=\"{$verses}\" name=\"سورة {$surah}\" tname=\"Sura {$surah}\" />\n";
        foreach (range(1, $verses) as $verse) {
            $text .= "{$surah}|{$verse}|كَلِمَةٌ تَجْرِيبِيَّةٌ أُخْرَى هُنَا رَقْم {$verse}\n";
        }
    }
    $text .= "\n#  A file in the shape of Tanzil's text, with made-up words: not the Quran.\n#  Tanzil Quran Text (Simple, Version Test)\n";
    $place = function (string $tag, string $items, int $count) use ($counts): string {
        $out = "<{$tag}>\n";
        foreach (range(1, $count) as $i) {
            $at = intdiv(($i - 1) * 6236, $count) + 1;
            foreach ($counts as $surah => $verses) {
                if ($at <= $verses) {
                    break;
                }
                $at -= $verses;
            }
            $out .= "<{$items} index=\"{$i}\" sura=\"{$surah}\" aya=\"{$at}\" />\n";
        }

        return $out."</{$tag}>\n";
    };
    File::ensureDirectoryExists($dir);
    File::put($dir.'/'.QuranText::TEXT_FILE, $text);
    File::put($dir.'/'.QuranText::DATA_FILE, $xml."</suras>\n".$place('juzs', 'juz', 30).$place('pages', 'page', 604)."</quran>\n");
}

/** The Quran's text as the server would have it after the deploy fetched it. */
function catchUpTextArrives(): void
{
    $dir = storage_path('framework/testing/quran-catch-up');
    catchUpQuranFiles($dir);
    app()->instance(QuranText::class, new QuranText($dir));
    app()->forgetInstance(CurriculumApi::class);
    app()->forgetInstance(LearningApi::class);
}

function inDemo(callable $read): mixed
{
    return app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('demo'), $read);
}

afterEach(fn () => File::deleteDirectory(storage_path('framework/testing/quran-catch-up')));

it('does nothing on a server without the demo academy', function () {
    $this->artisan('db:seed', ['--class' => DemoCatchUpSeeder::class, '--force' => true])
        ->expectsOutputToContain('No demo academy here')->assertSuccessful();
});

it('brings a demo made before the Quran\'s text up to date, once', function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll']]);
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/none')));
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
    // A demo older than the AI helpers: its plan without their area.
    $demo = app(TenantDirectory::class)->bySlug('demo');
    $before = SaasSubscription::currentFor($demo->id);
    AuditActor::use(new AuditActor('system', null, null));
    app(ChangeAcademyPlan::class)->handle($demo->id, $before->plan_id, 'خطة الديمو قبل المساعدات', $before->limits);
    AuditActor::forget();
    $state = fn () => inDemo(fn () => [
        'texts' => DB::table('curriculum_nodes')->whereNotNull('text_source')->count(),
        'maps' => DB::table('hifz_pages')->distinct()->count('student_id'),
        'reviews' => DB::table('hifz_reviews')->count(),
        'marks' => DB::table('recitation_mistakes')->count(),
        'from_marks' => DB::table('hifz_reviews')->where('source', 'mistakes')->count(),
    ]);
    expect($state())->toBe(['texts' => 0, 'maps' => 0, 'reviews' => 0, 'marks' => 0, 'from_marks' => 0]);

    // Before the text reaches the server: only the plan's area.
    $this->seed(DemoCatchUpSeeder::class);
    expect($state())->toBe(['texts' => 0, 'maps' => 0, 'reviews' => 0, 'marks' => 0, 'from_marks' => 0])
        ->and(SaasSubscription::currentFor($demo->id)->features_on)->toBe(['ai']);

    catchUpTextArrives();
    $this->seed(DemoCatchUpSeeder::class);
    $after = $state();
    expect($after['texts'])->toBe(8)
        ->and($after['maps'])->toBeGreaterThan(0)
        ->and($after['reviews'])->toBeGreaterThan(0)
        ->and($after['marks'])->toBeGreaterThan(0)
        ->and($after['from_marks'])->toBeGreaterThan(0);
    // The marks are words that are there, on the lesson's own passage.
    inDemo(function () {
        $mark = DB::table('recitation_mistakes')->first();
        $words = app(CurriculumApi::class)->passage(TextSource::quran($mark->surah, $mark->verse, $mark->verse))->words;
        expect($mark->word)->toBeLessThanOrEqual(count($words));
    });

    // Again, as on the next deploy: nothing new.
    $subscriptions = SaasSubscription::query()->where('academy_id', $demo->id)->count();
    $this->seed(DemoCatchUpSeeder::class);
    expect($state())->toBe($after)
        ->and(SaasSubscription::query()->where('academy_id', $demo->id)->count())->toBe($subscriptions);
});
