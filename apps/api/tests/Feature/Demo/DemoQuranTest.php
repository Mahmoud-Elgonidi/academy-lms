<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatchUpSeeder;
use Database\Seeders\E2eResetSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Contracts\LearningApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Texts\QuranText;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Tenancy\TenantContext;

/*
 * The demo's Quran program (docs/architecture/02, section 26): once the
 * server has the Quran's text, the academy holds exams and shows its
 * certificates to families, its Quran learners are on plans of two ready
 * tracks, Yousef's last lessons are recorded, his first surah was examined
 * and passed with a certificate, and the next stop waits to be booked; the
 * same reaches a demo already on a server (DemoCatchUpSeeder), once, and the
 * portal's end-to-end reset hands the Quran tests their fixtures. The text
 * here is made up in Tanzil's shape, never the Quran.
 */

/** Tanzil-shaped files with made-up words: 114 surahs (the last 37 short, six verses each), 6,236 verses, 604 pages, 30 parts. */
function demoQuranFiles(string $dir): void
{
    $counts = [1 => 6236 - 37 * 6 - 76 * 78] + array_fill(2, 76, 78) + array_fill(78, 37, 6);
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

function demoQuranText(bool $there): void
{
    $dir = storage_path('framework/testing/quran-demo');
    if ($there) {
        demoQuranFiles($dir);
    }
    app()->instance(QuranText::class, new QuranText($there ? $dir : base_path('tests/Fixtures/none')));
    app()->forgetInstance(CurriculumApi::class);
    app()->forgetInstance(LearningApi::class);
}

function inQuranDemo(callable $read): mixed
{
    return app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('demo'), $read);
}

/** The demo's Quran as counted. */
function demoQuranState(): array
{
    return inQuranDemo(fn () => [
        'programs' => DB::table('quran_programs')->whereNotNull('preset')->orderBy('preset')->pluck('preset')->all(),
        'plans' => DB::table('quran_plans as p')->join('users as u', 'u.id', '=', 'p.student_id')->where('p.status', 'active')->orderBy('u.email')->pluck('u.email')->all(),
        'results' => DB::table('quran_lesson_results')->count(),
        'exams' => DB::table('quran_exams')->orderBy('created_at')->pluck('status')->all(),
        'certificates' => DB::table('quran_certificates')->count(),
    ]);
}

beforeEach(fn () => SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
    'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll', 'quran']]));

afterEach(fn () => File::deleteDirectory(storage_path('framework/testing/quran-demo')));

it('comes out with its Quran program once the text is there: plans, recorded lessons, a certificate and an exam to book', function () {
    demoQuranText(true);
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();

    $state = demoQuranState();
    expect($state['programs'])->toBe(['full_backward', 'juz_amma_talqin'])
        ->and($state['plans'])->toContain('yousef@demo.test', 'hamza@demo.test')
        ->and($state['results'])->toBeGreaterThanOrEqual(2)
        ->and($state['exams'])->toBe(['passed', 'suggested'])
        ->and($state['certificates'])->toBe(1);

    inQuranDemo(function () {
        $settings = QuranSettings::current();
        expect($settings->exams_enabled)->toBeTrue()->and($settings->certificates_enabled)->toBeTrue()->and($settings->certificates_family)->toBeTrue();
        // Passed with no mistake, examined by another teacher than Yousef's own, in an assessment lesson held in the past.
        $certificate = QuranCertificate::query()->sole();
        $exam = DB::table('quran_exams')->where('status', 'passed')->sole();
        $lesson = DB::table('sessions')->where('id', $exam->session_id)->sole();
        expect($certificate->grade)->toBe('excellent')
            ->and($certificate->revoked_at)->toBeNull()
            ->and($certificate->student_id)->toBe((string) DB::table('users')->where('email', 'yousef@demo.test')->value('id'))
            ->and($lesson->type)->toBe('assessment')
            ->and($lesson->status)->toBe('completed')
            ->and($exam->examiner_id)->not->toBe((string) DB::table('enrollments')->where('id', $exam->enrollment_id)->value('teacher_id'));
        // Every result on a lesson held before now, each recorded once.
        $results = DB::table('quran_lesson_results as r')->join('sessions as s', 's.id', '=', 'r.session_id')->get(['s.status', 's.starts_at']);
        expect($results->pluck('status')->unique()->all())->toBe(['completed']);

        // "Start here" has its Quran step done.
        $progress = app(SetupChecklist::class)->progress();
        $quran = collect($progress->steps())->first(fn ($step) => $step->key() === 'quran.programs');
        expect($quran)->not->toBeNull()->and($progress->isDone($quran))->toBeTrue();
    });

    // The next deploy changes nothing.
    $this->seed(DemoCatchUpSeeder::class);
    expect(demoQuranState())->toBe($state);
});

it('brings a demo made before the Quran\'s text to the same, once', function () {
    demoQuranText(false);
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
    expect(demoQuranState())->toMatchArray(['programs' => ['noorani'], 'plans' => [], 'results' => 0, 'exams' => [], 'certificates' => 0]);

    demoQuranText(true);
    $this->seed(DemoCatchUpSeeder::class);
    $after = demoQuranState();
    expect($after['programs'])->toBe(['full_backward', 'juz_amma_talqin', 'noorani'])
        ->and($after['plans'])->toContain('yousef@demo.test', 'hamza@demo.test')
        ->and($after['results'])->toBeGreaterThanOrEqual(2)
        ->and($after['exams'])->toBe(['passed', 'suggested'])
        ->and($after['certificates'])->toBe(1);

    $this->seed(DemoCatchUpSeeder::class);
    expect(demoQuranState())->toBe($after);
});

it('hands the portal\'s Quran tests a lesson to record now, the family, and the certificate', function () {
    demoQuranText(true);
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();

    $fixtures = function (): array {
        Artisan::call('db:seed', ['--class' => E2eResetSeeder::class, '--force' => true]);
        $line = collect(explode("\n", Artisan::output()))->first(fn (string $l) => str_starts_with($l, 'E2E_FIXTURES '));
        $this->clearTenant();

        return json_decode(substr((string) $line, strlen('E2E_FIXTURES ')), true)['quran'];
    };
    $quran = $fixtures();
    expect($quran)->toMatchArray(['teacher' => 'nour@demo.test', 'guardian' => 'yousef.guardian@demo.test', 'student' => 'yousef@demo.test'])
        ->and($quran['certificate_code'])->toMatch('/^[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/')
        ->and($quran['certificate_number'])->toStartWith('Q-');

    inQuranDemo(function () use ($quran) {
        $lesson = DB::table('sessions')->where('id', $quran['lesson_id'])->sole();
        expect($lesson->enrollment_id)->toBe($quran['enrollment_id'])
            ->and($lesson->status)->toBeIn(['scheduled', 'in_progress'])
            ->and(now()->greaterThanOrEqualTo(CarbonImmutable::parse($lesson->starts_at)->subMinutes(10)))->toBeTrue()
            ->and(DB::table('quran_lesson_results')->where('session_id', $lesson->id)->exists())->toBeFalse();
    });

    // The next run, once the lesson is recorded, has another one.
    inQuranDemo(function () use ($quran) {
        $teacher = (string) DB::table('users')->where('email', 'nour@demo.test')->value('id');
        app(RecordQuranLesson::class)->handle($quran['lesson_id'], $teacher, ['parts' => ['new' => ['mistakes' => ['wrong' => 1]]]]);
    });
    $again = $fixtures();
    expect($again['lesson_id'])->not->toBe($quran['lesson_id'])
        ->and($again['certificate_code'])->toBe($quran['certificate_code']);
});
