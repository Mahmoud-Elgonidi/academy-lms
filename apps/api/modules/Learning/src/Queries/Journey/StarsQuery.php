<?php

namespace Modules\Learning\Queries\Journey;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Domain\Journey\LessonStars;
use Modules\Learning\Domain\Journey\Stars;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LearnerLesson;

/**
 * A student's stars (Domain\Journey\Stars), counted each time from what
 * happened: their lessons (on time, late, stayed to the end), their practice
 * rounds, their homework, the lessons the teacher marked mastered and the
 * week's chests they opened. Nothing is stored but the chests.
 */
final class StarsQuery
{
    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly EnrollmentApi $enrollments,
    ) {}

    /**
     * @return array{total: int, lessons: int, practice: int, homework: int, mastered: int, chest: int,
     *     by_lesson: array<string, array{stars: int, late_min: ?int, on_time: bool, stayed: bool, starts_at: CarbonImmutable, enrollment_id: ?string, status: string}>,
     *     lessons_list: list<LearnerLesson>}
     */
    public function for(string $studentId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        // Every lesson the student ever had (and the next two weeks, for the missions): the read goes through their own
        // participations (session_participants by user), so it grows with their history, not the academy's; an
        // enrolment's start would drop a trial before it and change the totals (JourneyEdgesTest measures both).
        $lessons = $this->attendance->lessonsOfStudent($studentId, CarbonImmutable::parse('2000-01-01', 'UTC'), $now->addDays(15));

        $byLesson = [];
        $fromLessons = 0;
        foreach ($lessons as $l) {
            $s = LessonStars::for($l->status, $l->startsAt, $l->endsAt, $l->joinedAt, $l->lastLeftAt);
            if ($s === null) {
                continue;
            }
            $byLesson[$l->id] = $s + ['starts_at' => $l->startsAt, 'enrollment_id' => $l->enrollmentId, 'status' => $l->status];
            $fromLessons += $s['stars'];
        }

        $homework = 0;
        $handed = DB::table('homework as h')->where('h.student_id', $studentId)->whereIn('h.status', ['submitted', 'reviewed'])
            ->selectRaw('h.due_at, (select min(s.submitted_at) from homework_submissions s where s.homework_id = h.id) as first_at')->get();
        foreach ($handed as $h) {
            $homework += Stars::homework(CarbonImmutable::parse($h->due_at), $h->first_at === null ? null : CarbonImmutable::parse($h->first_at));
        }

        $courses = array_map(fn (EnrollmentData $e) => $e->id, $this->enrollments->forStudent($studentId));
        $mastered = $courses === [] ? 0 : DB::table('enrollment_progress')->whereIn('enrollment_id', $courses)->whereNotNull('mastered_at')->count();
        $practice = (int) DB::table('practice_rounds')->where('student_id', $studentId)->sum('stars');
        $chests = ChallengeClaim::query()->where('student_id', $studentId)->where('challenge', 'chest')->count();

        $parts = [
            'lessons' => $fromLessons,
            'practice' => $practice,
            'homework' => $homework,
            'mastered' => $mastered * Stars::MASTERED,
            'chest' => $chests * Stars::CHEST,
        ];

        return ['total' => array_sum($parts)] + $parts + ['by_lesson' => $byLesson, 'lessons_list' => $lessons];
    }

    public function total(string $studentId): int
    {
        return $this->for($studentId)['total'];
    }
}
