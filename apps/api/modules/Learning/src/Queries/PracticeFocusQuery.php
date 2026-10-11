<?php

namespace Modules\Learning\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\Learning\Domain\ProgressCalculator;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;

/**
 * What a learner should practise now (docs/architecture/02, section 18), in
 * this order: homework that asks for practice and is not done yet, the
 * lessons of the last lesson report (two weeks at most), lessons mastered
 * and due for review, and where each course stands. Read each time from the
 * reports, homework and progress, for every course not ended (one waiting
 * for its first payment too); the games turn each into a quest.
 */
final class PracticeFocusQuery
{
    /** The last lesson counts this long. */
    public const LAST_LESSON_DAYS = 14;

    public const REVIEW_MAX = 4;

    /** Homework whose practice still counts: not marked yet (a better round keeps the better result). */
    private const HOMEWORK_STATUSES = ['assigned', 'submitted', 'missed'];

    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
    ) {}

    /** @return list<PracticeFocus> */
    public function for(string $studentId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $courses = array_values(array_filter($this->enrollments->forStudent($studentId),
            fn ($e) => $e->curriculumVersionId !== null && $e->status !== EnrollmentStatus::Ended));
        $trees = [];
        $tree = function (string $versionId) use (&$trees) {
            return $trees[$versionId] ??= $this->curriculum->tree($versionId);
        };
        $title = function (?string $versionId, array $nodeIds) use ($tree): string {
            if ($versionId === null) {
                return '';
            }
            $t = $tree($versionId);

            return implode(app()->getLocale() === 'ar' ? '، ' : ', ', array_map(fn ($id) => $t->nodes[$id]->title(), array_values(array_filter($nodeIds, fn ($id) => $t->has($id)))));
        };
        $version = fn (?string $enrollmentId) => collect($courses)->firstWhere('id', $enrollmentId)?->curriculumVersionId;

        $focus = [];
        $homework = Homework::query()->where('student_id', $studentId)->whereNotNull('practice_nodes')
            ->whereIn('status', self::HOMEWORK_STATUSES)->where('due_at', '>', $now->subDays(3))
            ->orderBy('due_at')->limit(3)->get();
        foreach ($homework as $h) {
            $focus[] = self::ofHomework($h);
        }

        foreach ($courses as $course) {
            $report = SessionReport::query()->where('enrollment_id', $course->id)->where('kind', 'lesson')->whereNotNull('submitted_at')
                ->where('session_starts_at', '>=', $now->subDays(self::LAST_LESSON_DAYS))->latest('session_starts_at')->first();
            $nodes = $report === null ? [] : ReportItem::query()->where('report_id', $report->id)->pluck('node_id')->all();
            if ($nodes !== []) {
                $focus[] = new PracticeFocus('last_lesson', $course->id, $nodes, $title($course->curriculumVersionId, $nodes));
            }

            $review = DB::table('enrollment_progress')->where('enrollment_id', $course->id)->whereNotNull('mastered_at')
                ->where('next_review_at', '<=', $now)->orderBy('next_review_at')->limit(self::REVIEW_MAX)->pluck('node_id')->all();
            if ($review !== []) {
                $focus[] = new PracticeFocus('review', $course->id, $review, $title($course->curriculumVersionId, $review));
            }

            // Where the course stands; before its first report, where it starts (as the roadmap shows it).
            $current = DB::table('enrollment_positions')->where('enrollment_id', $course->id)->value('current_node_id')
                ?? ProgressCalculator::position($tree($course->curriculumVersionId), $this->progress->states($course->id), [], $this->progress->lessonsPerWeek($course->id))->currentNodeId;
            if ($current !== null && ! in_array($current, $nodes, true)) {
                $focus[] = new PracticeFocus('current', $course->id, [$current], $title($course->curriculumVersionId, [$current]));
            }
        }

        return $focus;
    }

    /**
     * One homework of the learner's that asks for practice, as its quest, however late it is and whatever else is
     * open (the journey and the homework page offer each one to play); null when it asks for none or was marked.
     */
    public function homework(string $studentId, string $homeworkId): ?PracticeFocus
    {
        $h = ! Str::isUuid($homeworkId) ? null : Homework::query()->where('student_id', $studentId)->whereNotNull('practice_nodes')
            ->whereIn('status', self::HOMEWORK_STATUSES)->find($homeworkId);

        return $h === null ? null : self::ofHomework($h);
    }

    private static function ofHomework(Homework $h): PracticeFocus
    {
        return new PracticeFocus('homework', $h->enrollment_id, array_values($h->practice_nodes ?? []), $h->title, $h->id, $h->due_at,
            done: $h->practice_result !== null && ($h->accepts !== 'practice' || $h->status !== 'assigned'), game: $h->practice_game);
    }
}
