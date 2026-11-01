<?php

namespace Modules\Quran\Queries\Board;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Quran\Domain\Programs\Pace;
use Modules\Quran\Exams\Stops;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Programs\JuzStrip;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\Amounts;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Time\Clock;

/**
 * The academy's Quran courses for the back office (Quran › Students and the
 * Quran's home): each course of a Quran subject that has not ended, its
 * student and teacher, its plan (the program, where the learner is on the
 * strip of parts, how much is behind them, their pace and whether they keep
 * it: Domain\Programs\Pace), what they have memorized by part, and their
 * next exam. Read each time; a course in a group shows the group's plan.
 */
final class QuranCourses
{
    public function __construct(
        private readonly Plans $plans,
        private readonly ProgramPresenter $present,
        private readonly JuzStrip $strip,
        private readonly Stops $stops,
        private readonly AttendanceApi $lessons,
    ) {}

    /**
     * @param  array{search?: string, teacher?: string, pace?: string}  $filter  pace: one of Pace::STATUSES, or no_plan
     * @return list<array<string, mixed>>
     */
    public function rows(CarbonImmutable $now, string $timezone, array $filter = []): array
    {
        $courses = $this->courses($filter);
        if ($courses->isEmpty()) {
            return [];
        }
        $ids = $courses->pluck('id')->all();
        $students = $courses->pluck('student_id')->unique()->values()->all();
        $memorized = HifzPage::query()->whereIn('student_id', $students)->where('state', 'memorized')->get(['student_id', 'page'])
            ->groupBy('student_id')->map(fn (Collection $p) => $p->pluck('page')->map(fn ($n) => (int) $n)->all());
        $exams = QuranExam::query()->with('program')->whereIn('enrollment_id', $ids)->whereIn('status', QuranExam::OPEN)
            ->orderByRaw("case when status = 'booked' then 0 else 1 end")->orderBy('created_at')->get()->groupBy('enrollment_id');

        $rows = [];
        foreach ($courses as $c) {
            $plan = $this->plans->forEnrollment((string) $c->id);
            $row = [
                'enrollment' => (string) $c->id,
                'student_id' => (string) $c->student_id,
                'student' => (string) $c->student,
                'teacher_id' => (string) $c->teacher_id,
                'teacher' => (string) $c->teacher,
                'subject' => (string) $c->subject,
                'course_status' => (string) $c->status,
                'in_group' => (bool) $c->in_group,
                'plan' => $plan === null ? null : $this->plan($plan, (string) $c->student_id, $now),
                'shares' => $this->strip->shares($memorized->get($c->student_id, [])),
                'memorized' => count($memorized->get($c->student_id, [])),
                'exam' => ($exam = $exams->get($c->id)?->first()) === null ? null : $this->exam($exam, $timezone),
            ];
            $pace = $row['plan']['pace_status'] ?? 'no_plan';
            if (($filter['pace'] ?? '') !== '' && $filter['pace'] !== $pace) {
                continue;
            }
            $rows[] = $row;
        }
        // Who needs a hand first: behind, then no plan yet, then the rest, by name.
        $rank = ['behind' => 0, 'no_plan' => 1, 'new' => 2, 'on_track' => 3, 'frozen' => 4, 'finished' => 5];
        usort($rows, fn (array $a, array $b) => [$rank[$a['plan']['pace_status'] ?? 'no_plan'], $a['student']] <=> [$rank[$b['plan']['pace_status'] ?? 'no_plan'], $b['student']]);

        return $rows;
    }

    /**
     * One course's plan for its student's page (the office's and the teacher's): the plan, what they have memorized by
     * part and the last lessons' results (with the teacher's notes: never a family's screen).
     *
     * @return array{plan: ?array<string, mixed>, shares: array<int, float>, parts: int, recent: list<array<string, mixed>>, memorized: int}
     */
    public function course(string $enrollmentId, string $studentId, CarbonImmutable $now): array
    {
        $plan = $this->plans->forEnrollment($enrollmentId);
        $memorized = HifzPage::query()->where('student_id', $studentId)->where('state', 'memorized')->pluck('page')->map(fn ($p) => (int) $p)->all();
        $recent = $plan === null ? collect() : QuranLessonResult::query()->where('plan_id', $plan->id)->where('student_id', $studentId)
            ->orderByDesc('recorded_at')->limit(8)->get();

        return [
            'plan' => $plan === null ? null : $this->plan($plan, $studentId, $now),
            'shares' => $this->strip->shares($memorized),
            'parts' => $this->strip->count(),
            'recent' => $recent->reverse()->values()->map(fn (QuranLessonResult $r) => $this->present->result($r))->all(),
            'memorized' => count($memorized),
        ];
    }

    /** @return array<string, mixed> a plan as the screens show it */
    public function plan(QuranPlan $plan, string $studentId, CarbonImmutable $now): array
    {
        $portion = $this->plans->portion($plan);
        $frozen = $this->plans->frozen($plan);
        $shown = $this->present->plan($plan, $portion, $frozen);
        $shape = $plan->shape();
        $recent = QuranLessonResult::query()->where('plan_id', $plan->id)->where('student_id', $studentId)
            ->where('recorded_at', '>=', $now->subDays(Pace::DAYS))->orderByDesc('recorded_at')->limit(Pace::LESSONS)->get(['advanced']);
        $advanced = $shape->hasNew() ? $recent->where('advanced', true)->count() : $recent->count();

        return [
            'id' => $plan->id,
            'group' => $plan->isGroup(),
            'program_id' => $plan->program_id,
            'program' => $plan->program->name,
            'track' => $plan->program->track,
            'track_label' => $shown['program']['track_label'],
            'position' => $shown['position']['title'] ?? null,
            'juz' => $this->strip->juzOf($plan->position()),
            'progress' => (int) round(100 * (float) $shown['progress']),
            'pace' => Amounts::pace($shape->newAmount, $shape->unit, $shape->nearPortions, $shape->farPages),
            'pace_values' => $shown['pace'],
            'own_pace' => $plan->new_amount !== null || $plan->near_portions !== null || $plan->far_pages !== null,
            'unit' => $shape->unit,
            'frozen' => $frozen,
            'frozen_label' => $shown['frozen_label'],
            'finished' => $shown['finished'],
            'pace_status' => Pace::status($recent->count(), $advanced, $frozen !== null, $shown['finished']),
            'recorded' => $recent->count(),
            'advanced' => $advanced,
            'portion' => $shown['portion'],
            'position_raw' => $plan->position()?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function exam(QuranExam $exam, string $timezone): array
    {
        $lesson = $exam->session_id === null ? null : $this->lessons->lesson($exam->session_id);

        return [
            'id' => $exam->id,
            'label' => $this->stops->label($exam->stop_kind, $exam->stop_number, $exam->program->name),
            'status' => $exam->status,
            'status_label' => __('quran::exams.statuses.'.$exam->status),
            'when' => $lesson === null ? null : $lesson->startsAt->setTimezone($timezone)->locale(app()->getLocale())->translatedFormat(Clock::pattern('D j M، H:i')),
        ];
    }

    /** @param  array{search?: string, teacher?: string}  $filter */
    private function courses(array $filter): Collection
    {
        $name = app()->getLocale() === 'ar' ? 's.name_ar' : 's.name_en';
        $search = trim((string) ($filter['search'] ?? ''));

        return DB::table('enrollments as e')
            ->join('users as st', fn ($j) => $j->on('st.academy_id', '=', 'e.academy_id')->on('st.id', '=', 'e.student_id'))
            ->join('users as te', fn ($j) => $j->on('te.academy_id', '=', 'e.academy_id')->on('te.id', '=', 'e.teacher_id'))
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('s.kind', 'quran')->where('e.status', '!=', 'ended')
            ->when(($filter['teacher'] ?? '') !== '', fn ($q) => $q->where('e.teacher_id', $filter['teacher']))
            ->when($search !== '', fn ($q) => $q->where('st.name', 'ilike', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('st.name')
            ->get(['e.id', 'e.student_id', 'e.teacher_id', 'e.status', 'st.name as student', 'te.name as teacher', DB::raw("{$name} as subject"),
                DB::raw('exists (select 1 from group_members gm where gm.academy_id = e.academy_id and gm.enrollment_id = e.id and gm.left_on is null) as in_group')]);
    }

    /** @return array<string, string> the teachers of the Quran's courses, by name */
    public function teachers(): array
    {
        return $this->courses([])->pluck('teacher', 'teacher_id')->sort()->all();
    }
}
