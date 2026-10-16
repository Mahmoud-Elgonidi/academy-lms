<?php

namespace Modules\Learning\Actions\Mushaf;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Models\LessonMushaf;
use Modules\Learning\Mushaf\MushafLesson;

/** The teacher turns the lesson's mushaf to a page: the student's screen follows it. */
final class TurnMushafPage
{
    public function __construct(private readonly CurriculumApi $curriculum) {}

    public function handle(MushafLesson $lesson, int $page): LessonMushaf
    {
        if (! $lesson->teaches()) {
            throw new AuthorizationException(__('learning::mushaf.errors.teacher'));
        }
        if ($this->curriculum->quranPage($page) === null) {
            throw ValidationException::withMessages(['page' => __('learning::mushaf.errors.page')]);
        }

        return LessonMushaf::query()->updateOrCreate(['session_id' => $lesson->card->id], ['page' => $page]);
    }
}
