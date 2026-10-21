<?php

namespace Modules\Learning\Digest;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Actions\Assist\DraftReportFromVoice;
use Modules\Learning\Domain\Journey\LessonStars;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Templates\TemplateAnswers;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LearnerLesson;

/**
 * What a learner's week was, for the note to their guardians: only what the
 * family may read already. Lessons taken and missed, what the teacher said
 * of each lesson (outcomes, words for the family, the academy's questions a
 * family sees), homework, practice, the week's stars (not in the plain
 * journey), and the recording of the week to hear. Never internal notes, the
 * academy's review, ratings, disputes, teachers' KPIs or pay. The child is
 * {child} wherever the teacher wrote their name.
 */
final class WeekFacts
{
    /** Words for the family kept from the week's reports, newest first. */
    public const NOTES = 2;

    public const NOTE_LENGTH = 300;

    /** Lessons named per outcome, and the academy's questions shown. */
    public const TITLES = 5;

    public const ANSWERS = 4;

    /** A recording is the week's to hear when its check or its mark says it went well. */
    public const VOICE_CHECKED = 0.8;

    public const VOICE_GRADE = 4;

    public function __construct(private readonly AttendanceApi $attendance) {}

    /**
     * @return array{
     *     lessons: array{done: int, missed: int},
     *     learned: array{mastered: list<array{ar: string, en: string}>, worked_on: list<array{ar: string, en: string}>, review: list<array{ar: string, en: string}>},
     *     notes: list<string>,
     *     answers: list<array{ar: string, en: string}>,
     *     homework: array{given: int, handed_in: int, marked: int, missed: int},
     *     practice: array{rounds: int, stars: int},
     *     stars: ?int,
     *     voice: ?string
     * }
     */
    public function for(string $studentId, string $name, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $lessons = array_values(array_filter($this->attendance->lessonsOfStudent($studentId, $from, $to), fn (LearnerLesson $l) => $l->startsAt < $to));
        $reports = SessionReport::query()->where('student_id', $studentId)->where('kind', 'lesson')
            ->whereIn('status', ['submitted', 'reviewed'])->where('session_starts_at', '>=', $from)->where('session_starts_at', '<', $to)
            ->orderByDesc('session_starts_at')->get();

        $practice = DB::table('practice_rounds')->where('student_id', $studentId)->where('played_at', '>=', $from)->where('played_at', '<', $to)
            ->selectRaw('count(*) as rounds, coalesce(sum(stars), 0) as stars')->first();
        $lessonStars = 0;
        foreach ($lessons as $l) {
            $lessonStars += LessonStars::for($l->status, $l->startsAt, $l->endsAt, $l->joinedAt, $l->lastLeftAt)['stars'] ?? 0;
        }

        return [
            'lessons' => [
                'done' => count(array_filter($lessons, fn (LearnerLesson $l) => $l->status === 'completed')),
                'missed' => count(array_filter($lessons, fn (LearnerLesson $l) => $l->status === 'student_absent')),
            ],
            'learned' => $this->learned($reports->pluck('id')->all()),
            'notes' => $reports->pluck('notes_for_student')->filter(fn ($n) => trim((string) $n) !== '')->take(self::NOTES)
                ->map(fn ($n) => mb_substr(trim(DraftReportFromVoice::withoutName((string) $n, $name)), 0, self::NOTE_LENGTH))->values()->all(),
            'answers' => $this->answers($reports->first()),
            'homework' => $this->homework($studentId, $from, $to),
            'practice' => ['rounds' => (int) $practice->rounds, 'stars' => (int) $practice->stars],
            'stars' => LearningSettings::current()->journey_style === 'plain' ? null : $lessonStars + (int) $practice->stars,
            'voice' => $this->voice($studentId, $from, $to),
        ];
    }

    /** Nothing happened that week: no lesson, homework or practice to tell of. */
    public static function quiet(array $facts): bool
    {
        return array_sum($facts['lessons']) === 0 && array_sum($facts['homework']) === 0 && $facts['practice']['rounds'] === 0;
    }

    /**
     * Every number the facts hold (counts, and the digits in lesson titles, notes and answers): the only numbers a note may say.
     *
     * @return list<int>
     */
    public static function numbers(array $facts): array
    {
        $numbers = [...array_values($facts['lessons']), ...array_values($facts['homework']), ...array_values($facts['practice'])];
        if ($facts['stars'] !== null) {
            $numbers[] = $facts['stars'];
        }
        $words = [...$facts['notes'], ...array_merge(...array_map('array_values', [...$facts['learned']['mastered'], ...$facts['learned']['worked_on'], ...$facts['learned']['review'], ...$facts['answers']]) ?: [[]])];
        foreach ($words as $text) {
            preg_match_all('/\d+/', WordingCheck::latinDigits((string) $text), $m);
            array_push($numbers, ...array_map('intval', $m[0]));
        }

        return array_values(array_unique(array_map('intval', $numbers)));
    }

    /**
     * The lessons the week's reports speak of, by outcome, in both languages.
     *
     * @param  list<string>  $reportIds
     * @return array{mastered: list<array{ar: string, en: string}>, worked_on: list<array{ar: string, en: string}>, review: list<array{ar: string, en: string}>}
     */
    private function learned(array $reportIds): array
    {
        $rows = $reportIds === [] ? collect() : DB::table('report_items as i')->join('curriculum_nodes as n', fn ($j) => $j->on('n.academy_id', '=', 'i.academy_id')->on('n.id', '=', 'i.node_id'))
            ->whereIn('i.report_id', $reportIds)->get(['i.outcome', 'n.id', 'n.title_ar', 'n.title_en']);
        $of = fn (array $outcomes) => $rows->whereIn('outcome', $outcomes)->unique('id')->take(self::TITLES)
            ->map(fn ($r) => ['ar' => (string) $r->title_ar, 'en' => (string) ($r->title_en ?: $r->title_ar)])->values()->all();

        return ['mastered' => $of(['mastered']), 'worked_on' => $of(['introduced', 'practiced']), 'review' => $of(['needs_review'])];
    }

    /**
     * The academy's own questions a family sees, from the week's last report, as "question: answer" in both languages.
     *
     * @return list<array{ar: string, en: string}>
     */
    private function answers(?SessionReport $report): array
    {
        $template = $report === null ? null : ReportTemplate::forReport($report);
        if ($template === null) {
            return [];
        }
        $in = fn (string $locale) => collect(TemplateAnswers::present($template, $report->answers ?? [], false, $locale))
            ->filter(fn (array $a) => trim((string) ($a['text'] ?? '')) !== '')->keyBy('key')->map(fn (array $a) => $a['label'].': '.$a['text']);
        [$ar, $en] = [$in('ar'), $in('en')];

        return $ar->keys()->take(self::ANSWERS)->map(fn (string $key) => ['ar' => $ar[$key], 'en' => $en[$key] ?? $ar[$key]])->values()->all();
    }

    /** @return array{given: int, handed_in: int, marked: int, missed: int} */
    private function homework(string $studentId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $in = fn (string $column) => fn ($q) => $q->where($column, '>=', $from)->where($column, '<', $to);
        $mine = fn () => DB::table('homework')->where('student_id', $studentId);

        return [
            'given' => $mine()->where($in('created_at'))->count(),
            'handed_in' => $mine()->whereExists(fn ($q) => $q->from('homework_submissions as s')->whereColumn('s.homework_id', 'homework.id')->where($in('s.submitted_at')))->count(),
            'marked' => $mine()->where($in('reviewed_at'))->count(),
            'missed' => $mine()->where('status', 'missed')->where($in('due_at'))->count(),
        ];
    }

    /** The recording of the week a guardian may hear: the best recited one handed in, when its check or mark says it went well. */
    private function voice(string $studentId, CarbonImmutable $from, CarbonImmutable $to): ?string
    {
        $best = DB::table('homework_submissions as s')
            ->join('homework as h', fn ($j) => $j->on('h.academy_id', '=', 's.academy_id')->on('h.id', '=', 's.homework_id'))
            ->leftJoin('submission_checks as c', fn ($j) => $j->on('c.academy_id', '=', 's.academy_id')->on('c.submission_id', '=', 's.id')->where('c.status', 'ready'))
            ->where('h.student_id', $studentId)->whereNotNull('s.file_path')->where(fn ($q) => HomeworkSubmission::whereRecording($q, 's.mime'))
            ->where('s.submitted_at', '>=', $from)->where('s.submitted_at', '<', $to)
            ->get(['s.id', 's.submitted_at', 'h.grade', 'c.result'])
            ->map(function ($row) {
                $summary = json_decode((string) $row->result, true)['summary'] ?? null;
                $checked = is_array($summary) && ($summary['words'] ?? 0) > 0 ? $summary['ok'] / $summary['words'] : null;
                $good = ($checked !== null && $checked >= self::VOICE_CHECKED) || ($row->grade !== null && (int) $row->grade >= self::VOICE_GRADE);

                return $good ? ['id' => $row->id, 'score' => max($checked ?? 0, $row->grade !== null ? $row->grade / 5 : 0), 'at' => $row->submitted_at] : null;
            })->filter()->sortBy([['score', 'desc'], ['at', 'desc']])->first();

        return $best['id'] ?? null;
    }
}
