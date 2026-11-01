<?php

namespace Modules\Quran\Queries\Board;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Quran\Certificates\Certificates;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranProgram;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The Quran's home in the back office (docs/architecture/02, section 26):
 * this month on the academy's clock (new pages memorized, lessons recorded,
 * exams passed and failed), who keeps their plan's pace and who is behind
 * (QuranCourses), the exams waiting and booked, and the latest certificates.
 * Numbers read each time.
 */
final class QuranHome
{
    public const LIST = 5;

    public function __construct(
        private readonly QuranCourses $courses,
        private readonly Certificates $certificates,
        private readonly TenantContext $tenants,
    ) {}

    /** @return array<string, mixed> */
    public function read(CarbonImmutable $now, string $timezone): array
    {
        $month = $now->setTimezone($this->tenants->require()->defaultTimezone)->startOfMonth()->utc();
        $rows = $this->courses->rows($now, $timezone);
        $pace = array_fill_keys(['on_track', 'behind', 'new', 'frozen', 'finished', 'no_plan'], 0);
        foreach ($rows as $row) {
            $pace[$row['plan']['pace_status'] ?? 'no_plan']++;
        }
        $exams = QuranExam::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $decided = QuranExam::query()->where('recorded_at', '>=', $month)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $booked = QuranExam::query()->with('program')->where('status', 'booked')->get()
            ->map(fn (QuranExam $e) => $this->courses->exam($e, $timezone) + ['student' => $this->certificates->name($e->student_id), 'at' => $e->booked_at])
            ->all();

        return [
            'month' => [
                // A page counts the month it came onto a learner's map, once it is memorized.
                'memorized' => HifzPage::query()->where('state', 'memorized')->where('created_at', '>=', $month)->count(),
                'lessons' => QuranLessonResult::query()->where('recorded_at', '>=', $month)->count(),
                'passed' => (int) ($decided['passed'] ?? 0),
                'failed' => (int) ($decided['failed'] ?? 0),
            ],
            'courses' => count($rows),
            'pace' => $pace,
            'behind' => array_slice(array_values(array_filter($rows, fn (array $r) => ($r['plan']['pace_status'] ?? null) === 'behind')), 0, self::LIST),
            'no_plan' => array_slice(array_values(array_filter($rows, fn (array $r) => $r['plan'] === null && ! $r['in_group'])), 0, self::LIST),
            'exams' => ['suggested' => (int) ($exams['suggested'] ?? 0), 'booked' => (int) ($exams['booked'] ?? 0)],
            'booked' => array_slice($booked, 0, self::LIST),
            'programs' => QuranProgram::query()->whereNull('archived_at')->count(),
            'certificates' => QuranCertificate::query()->latest('issued_at')->limit(self::LIST)->get()->map(fn (QuranCertificate $c) => [
                'id' => $c->id,
                'number' => $c->number,
                'student' => $this->certificates->name($c->student_id),
                'portion' => $this->certificates->portion($c),
                'grade' => __('quran::programs.grades.'.$c->grade),
                'day' => $c->issued_at->setTimezone($timezone)->locale(app()->getLocale())->translatedFormat('j M Y'),
                'revoked' => $c->isRevoked(),
            ])->all(),
            'students' => DB::table('quran_plans')->where('status', 'active')->whereNotNull('student_id')->distinct()->count('student_id'),
        ];
    }
}
