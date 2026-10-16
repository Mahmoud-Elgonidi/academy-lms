<?php

namespace Modules\Learning;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\Mushaf\MarkMistake;
use Modules\Learning\Actions\Mushaf\TurnMushafPage;
use Modules\Learning\Actions\Mushaf\UnmarkMistake;
use Modules\Learning\Actions\RecordPractice;
use Modules\Learning\Actions\SendCheer;
use Modules\Learning\Contracts\DTOs\HomeworkLine;
use Modules\Learning\Contracts\DTOs\PlanLine;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\Learning\Contracts\DTOs\PracticeRecorded;
use Modules\Learning\Contracts\DTOs\PracticeRound;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Domain\PlannedItem;
use Modules\Learning\Domain\TodaysPlan;
use Modules\Learning\Http\V1\Resources\MushafPresenter;
use Modules\Learning\Materials\MaterialLinks;
use Modules\Learning\Models\Homework;
use Modules\Learning\Mushaf\MushafInput;
use Modules\Learning\Mushaf\MushafLessons;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\Learning\Queries\Journey\StarsQuery;
use Modules\Learning\Queries\Mushaf\LessonMushaf;
use Modules\Learning\Queries\PracticeFocusQuery;
use Modules\Learning\Queries\ProgressQuery;

final class LearningService implements LearningApi
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
        private readonly MaterialLinks $materials,
    ) {}

    public function planFor(?string $enrollmentId, CarbonImmutable $lessonStart, bool $forTeacher = true): array
    {
        $versionId = $enrollmentId === null ? null : $this->enrollments->find($enrollmentId)?->curriculumVersionId;
        if ($versionId === null) {
            return [];
        }
        $tree = $this->curriculum->tree($versionId);
        $plan = TodaysPlan::for($tree, $this->progress->states($enrollmentId), $lessonStart);
        $materials = $this->materials->forLessons(array_map(fn (PlannedItem $i) => $i->nodeId, $plan), $forTeacher);

        return array_map(fn (PlannedItem $i) => new PlanLine(
            $i->nodeId,
            $tree->nodes[$i->nodeId]->title(),
            implode(' › ', array_map(fn ($a) => $a->title(), $tree->ancestorsOf($i->nodeId))),
            $i->reason,
            $tree->nodes[$i->nodeId]->objectives,
            $materials[$i->nodeId] ?? [],
        ), $plan);
    }

    public function lastHomework(string $studentId, ?string $enrollmentId, CarbonImmutable $lessonStart): ?HomeworkLine
    {
        $h = Homework::query()->withCount('submissions')->where('student_id', $studentId)
            ->when($enrollmentId !== null, fn ($q) => $q->where('enrollment_id', $enrollmentId))
            ->where('created_at', '<', $lessonStart)
            ->orderByDesc('created_at')->first();

        return $h === null ? null : new HomeworkLine($h->id, $h->title, $h->instructions, $h->status, $h->due_at, $h->grade, $h->feedback, (int) $h->submissions_count);
    }

    public function practiceFocus(string $studentId, ?CarbonImmutable $now = null): array
    {
        return app(PracticeFocusQuery::class)->for($studentId, $now);
    }

    public function homeworkFocus(string $studentId, string $homeworkId): ?PracticeFocus
    {
        return app(PracticeFocusQuery::class)->homework($studentId, $homeworkId);
    }

    public function journeyStations(string $studentId): array
    {
        return app(JourneyMap::class)->stations($studentId);
    }

    public function stars(string $studentId): int
    {
        return app(StarsQuery::class)->total($studentId);
    }

    public function recordPractice(PracticeRound $round): PracticeRecorded
    {
        return app(RecordPractice::class)->handle($round);
    }

    public function cheer(string $fromId, string $studentId, string $kind = 'heart'): void
    {
        app(SendCheer::class)->handle($fromId, $studentId, $kind);
    }

    public function lessonMushaf(string $sessionId, string $viewerId, ?int $page = null): ?array
    {
        $lesson = app(MushafLessons::class)->for($sessionId, $viewerId);
        $shown = $lesson === null ? null : app(LessonMushaf::class)->page($lesson, $page, CarbonImmutable::now());

        return $shown === null ? null : app(MushafPresenter::class)->page($shown);
    }

    public function changeMushaf(string $sessionId, string $viewerId, string $change, array $input): ?array
    {
        if (! in_array($change, MushafInput::CHANGES, true)) {
            throw new InvalidArgumentException("No such change to a mushaf: {$change}");
        }
        $lesson = app(MushafLessons::class)->for($sessionId, $viewerId);
        if ($lesson === null) {
            return null;
        }
        $data = Validator::make($input, MushafInput::rules($change), [], trans('learning::mushaf.attributes'))->validate();
        $at = fn (string $key) => (int) $data[$key];
        match ($change) {
            'turn' => app(TurnMushafPage::class)->handle($lesson, $at('page')),
            'mark' => app(MarkMistake::class)->handle($lesson, $at('surah'), $at('verse'), $at('word'), (string) $data['kind']),
            'unmark' => app(UnmarkMistake::class)->handle($lesson, $at('surah'), $at('verse'), $at('word')),
        };

        return $this->lessonMushaf($sessionId, $viewerId, MushafInput::pageOf($data, app(CurriculumApi::class)));
    }
}
