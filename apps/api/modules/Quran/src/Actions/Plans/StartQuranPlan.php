<?php

namespace Modules\Quran\Actions\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Quran\Actions\Hifz\MarkMemorized;
use Modules\Quran\Domain\Programs\LessonPortion;
use Modules\Quran\Domain\Programs\MushafLayout;
use Modules\Quran\Domain\Programs\Position;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Domain\Programs\ProgramShape;
use Modules\Quran\Hifz\HifzPages;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Programs\Mushaf;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A course of a Quran subject starts on a program (docs/architecture/02,
 * section 26), or a group of one shares a plan: one live plan each, a new
 * one closing the last. Where the learner stands at the start (placement:
 * parts, surahs or pages they already know, and how well) goes on their
 * hifz map through MarkMemorized; they start at the first verse of the
 * program's order not on a page they have memorized, or where the office
 * says. Their own pace may differ from the program's. A course seated in a
 * group takes its group's plan; an ended course takes none. Audited.
 */
final class StartQuranPlan
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly Mushaf $mushaf,
        private readonly HifzPages $pages,
        private readonly MarkMemorized $mark,
        private readonly AuditTrail $audit,
        private readonly QuranArea $area,
    ) {}

    /**
     * @param  array{placement?: array{juz?: list<int>, surahs?: list<int>, pages?: list<int>, strength?: string}, start?: array{surah?: ?int, verse: int}, pace?: array{new_amount?: ?float, near_portions?: ?int, far_pages?: ?int}}  $options
     */
    public function handle(string $enrollmentId, string $programId, array $options = [], ?string $actorId = null): QuranPlan
    {
        $this->area->require();
        $course = $this->enrollments->find($enrollmentId) ?? throw ValidationException::withMessages(['enrollment_id' => __('quran::programs.errors.course')]);
        if ($course->status === EnrollmentStatus::Ended || $course->endedBy(CarbonImmutable::now()->toDateString())) {
            throw ValidationException::withMessages(['enrollment_id' => __('quran::programs.errors.course_ended')]);
        }
        if (! $this->area->isQuranSubject($course->subjectId)) {
            throw ValidationException::withMessages(['enrollment_id' => __('quran::programs.errors.not_quran')]);
        }
        if (DB::table('group_members')->where('enrollment_id', $enrollmentId)->whereNull('left_on')->exists()) {
            throw ValidationException::withMessages(['enrollment_id' => __('quran::programs.errors.in_group')]);
        }
        $program = $this->program($programId);
        $shape = $this->paced($program, $options['pace'] ?? []);

        return DB::transaction(function () use ($course, $program, $shape, $options, $actorId): QuranPlan {
            $placement = $options['placement'] ?? [];
            if (($placement['juz'] ?? []) !== [] || ($placement['surahs'] ?? []) !== [] || ($placement['pages'] ?? []) !== []) {
                $pages = $this->pages->chosen((array) ($placement['juz'] ?? []), (array) ($placement['surahs'] ?? []), (array) ($placement['pages'] ?? []));
                $this->mark->handle($course->studentId, $pages, (string) ($placement['strength'] ?? 'good'));
            }
            $memorized = HifzPage::query()->where('student_id', $course->studentId)->where('state', 'memorized')->pluck('page')->map(fn ($p) => (int) $p)->all();
            $start = $this->start($shape, $options['start'] ?? null, $memorized);

            QuranPlan::query()->where('enrollment_id', $course->id)->where('status', 'active')->update(['status' => 'closed', 'closed_at' => CarbonImmutable::now()]);
            $plan = QuranPlan::query()->create([
                'program_id' => $program->id, 'enrollment_id' => $course->id, 'student_id' => $course->studentId, 'created_by' => $actorId,
                'finished_at' => $start === null && $shape->hasNew() ? CarbonImmutable::now() : null,
            ] + QuranPlan::positionColumns($start) + $this->paceColumns($options['pace'] ?? []));
            $this->audit->record('quran.plan_started', 'quran_plans', $plan->id, ['program' => $program->name]);

            return $plan->load('program');
        });
    }

    /**
     * A group of a Quran subject shares one plan: one portion a lesson, a grade for each student.
     *
     * @param  array{start?: array{surah?: ?int, verse: int}, pace?: array{new_amount?: ?float, near_portions?: ?int, far_pages?: ?int}}  $options
     */
    public function forGroup(string $groupId, string $programId, array $options = [], ?string $actorId = null): QuranPlan
    {
        $this->area->require();
        $group = DB::table('lesson_groups')->where('id', $groupId)->first(['id', 'subject_id', 'status'])
            ?? throw ValidationException::withMessages(['group_id' => __('quran::programs.errors.course')]);
        if ($group->status === 'ended') {
            throw ValidationException::withMessages(['group_id' => __('quran::programs.errors.course_ended')]);
        }
        if (! $this->area->isQuranSubject((string) $group->subject_id)) {
            throw ValidationException::withMessages(['group_id' => __('quran::programs.errors.not_quran')]);
        }
        $program = $this->program($programId);
        $shape = $this->paced($program, $options['pace'] ?? []);
        $start = $this->start($shape, $options['start'] ?? null, []);

        return DB::transaction(function () use ($groupId, $program, $shape, $start, $options, $actorId): QuranPlan {
            QuranPlan::query()->where('group_id', $groupId)->where('status', 'active')->update(['status' => 'closed', 'closed_at' => CarbonImmutable::now()]);
            $plan = QuranPlan::query()->create([
                'program_id' => $program->id, 'group_id' => $groupId, 'created_by' => $actorId,
                'finished_at' => $start === null && $shape->hasNew() ? CarbonImmutable::now() : null,
            ] + QuranPlan::positionColumns($start) + $this->paceColumns($options['pace'] ?? []));
            $this->audit->record('quran.plan_started', 'quran_plans', $plan->id, ['program' => $program->name]);

            return $plan->load('program');
        });
    }

    private function program(string $programId): QuranProgram
    {
        $program = QuranProgram::query()->find($programId);
        if ($program === null || $program->archived_at !== null) {
            throw ValidationException::withMessages(['program_id' => __('quran::programs.errors.program')]);
        }
        if (! $program->shape()->isNoorani() && $this->mushaf->layout() === null) {
            throw ValidationException::withMessages(['program_id' => __('quran::programs.errors.no_text')]);
        }

        return $program;
    }

    /** @param  array<string, mixed>  $pace */
    private function paced(QuranProgram $program, array $pace): ProgramShape
    {
        $errors = ProgramRules::paceErrors($program->track, $program->unit, array_filter($pace, fn ($v) => $v !== null));
        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(fn (string $why) => __('quran::programs.errors.'.$why), $errors));
        }
        $columns = $this->paceColumns($pace);

        return $program->shape()->withPace($columns['new_amount'], $columns['near_portions'], $columns['far_pages']);
    }

    /**
     * @param  array<string, mixed>  $pace
     * @return array{new_amount: ?float, near_portions: ?int, far_pages: ?int}
     */
    private function paceColumns(array $pace): array
    {
        return [
            'new_amount' => isset($pace['new_amount']) ? (float) $pace['new_amount'] : null,
            'near_portions' => isset($pace['near_portions']) ? (int) $pace['near_portions'] : null,
            'far_pages' => isset($pace['far_pages']) ? (int) $pace['far_pages'] : null,
        ];
    }

    /**
     * @param  ?array{surah?: ?int, verse: int}  $given
     * @param  list<int>  $memorized
     */
    private function start(ProgramShape $shape, ?array $given, array $memorized): ?Position
    {
        $layout = $this->mushaf->layout();
        if ($given !== null && isset($given['verse'])) {
            $at = $shape->isNoorani() ? Position::lesson((int) $given['verse']) : Position::verse((int) ($given['surah'] ?? 0), (int) $given['verse']);
            if ((! $shape->isNoorani() && $layout === null) || ! LessonPortion::holds($shape, $layout ?? new MushafLayout([1 => 1], [1], [1]), $at)) {
                throw ValidationException::withMessages(['start' => __('quran::programs.errors.start')]);
            }

            return $at;
        }
        if ($shape->isNoorani()) {
            return Position::lesson($shape->fromVerse);
        }

        return $layout === null ? null : LessonPortion::start($shape, $layout, $memorized);
    }
}
