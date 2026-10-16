<?php

namespace Modules\Learning\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Actions\Mushaf\MarkMistake;
use Modules\Learning\Actions\Mushaf\TurnMushafPage;
use Modules\Learning\Actions\Mushaf\UnmarkMistake;
use Modules\Learning\Http\V1\Resources\MushafPresenter;
use Modules\Learning\Mushaf\MushafInput;
use Modules\Learning\Mushaf\MushafLesson;
use Modules\Learning\Mushaf\MushafLessons;
use Modules\Learning\Queries\Mushaf\LessonMushaf;
use Modules\Learning\Queries\Mushaf\MistakesBook;
use Modules\People\Contracts\FamilyView;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A Quran lesson's shared mushaf for the apps and the portal: the page open
 * (the teacher turns it, the student's screen follows it), its words, and the
 * words the teacher marks (wrong, hesitation, tajweed); and a learner's
 * mistakes notebook.
 */
final class MushafController
{
    public function __construct(
        private readonly MushafLessons $lessons,
        private readonly LessonMushaf $mushaf,
        private readonly MushafPresenter $present,
    ) {}

    /**
     * A lesson's shared mushaf.
     *
     * The page asked for, else the one the teacher has open, else where the lesson starts; its words, and the words
     * marked on it. For the lesson's teacher, its student and the student's guardians, in a lesson of a course that
     * reads the Quran.
     *
     * @throws NotFoundHttpException no mushaf in this lesson for me
     */
    public function show(Request $request, string $session): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'between:1,604']]);

        return $this->answer($this->lesson($request, $session), $request->integer('page') ?: null);
    }

    /**
     * Turn the lesson's mushaf to a page.
     *
     * The teacher only; the student's screen follows (live).
     */
    public function turn(Request $request, string $session, TurnMushafPage $turn): JsonResponse
    {
        $request->validate(MushafInput::rules('turn'));
        $lesson = $this->lesson($request, $session);
        $turn->handle($lesson, $request->integer('page'));

        return $this->answer($lesson, $request->integer('page'));
    }

    /**
     * Mark a word.
     *
     * The teacher, from the room's opening until the report is handed in: wrong, hesitation or tajweed; marking it
     * again changes what it was. Answers the page (page, else the word's).
     */
    public function mark(Request $request, string $session, MarkMistake $mark, CurriculumApi $curriculum): JsonResponse
    {
        $request->validate(MushafInput::rules('mark'), [], trans('learning::mushaf.attributes'));
        $lesson = $this->lesson($request, $session);
        $mark->handle($lesson, $request->integer('surah'), $request->integer('verse'), $request->integer('word'), (string) $request->input('kind'));

        return $this->answer($lesson, MushafInput::pageOf($request->all(), $curriculum));
    }

    /**
     * Take a mark off a word.
     *
     * The teacher, while words may be marked. Answers the page (page, else the word's).
     */
    public function unmark(Request $request, string $session, UnmarkMistake $unmark, CurriculumApi $curriculum): JsonResponse
    {
        $request->validate(MushafInput::rules('unmark'), [], trans('learning::mushaf.attributes'));
        $lesson = $this->lesson($request, $session);
        $unmark->handle($lesson, $request->integer('surah'), $request->integer('verse'), $request->integer('word'));

        return $this->answer($lesson, MushafInput::pageOf($request->all(), $curriculum));
    }

    /**
     * A learner's mistakes notebook.
     *
     * The words their teachers marked in the last six months, by surah and verse (a guardian passes learner_id).
     */
    public function book(Request $request, FamilyView $family, MistakesBook $book): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $asked = $request->query('learner_id');
        $learner = $family->learner((string) $request->user()?->getAuthIdentifier(), is_string($asked) && $asked !== '' ? $asked : null)?->id
            ?? throw new NotFoundHttpException;

        return response()->json(['data' => ['learner_id' => $learner, ...$this->present->book($book->for($learner, CarbonImmutable::now()))]]);
    }

    private function answer(MushafLesson $lesson, ?int $page): JsonResponse
    {
        $shown = $this->mushaf->page($lesson, $page, CarbonImmutable::now()) ?? throw new NotFoundHttpException;

        return response()->json(['data' => $this->present->page($shown)]);
    }

    private function lesson(Request $request, string $session): MushafLesson
    {
        return $this->lessons->for($session, (string) $request->user()?->getAuthIdentifier()) ?? throw new NotFoundHttpException;
    }
}
