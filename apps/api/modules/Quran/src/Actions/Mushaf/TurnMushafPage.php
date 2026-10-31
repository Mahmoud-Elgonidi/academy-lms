<?php

namespace Modules\Quran\Actions\Mushaf;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Models\LessonMushaf;
use Modules\Quran\Mushaf\MushafLesson;

/** The teacher turns the lesson's mushaf to a page: the student's screen follows it. */
final class TurnMushafPage
{
    public function __construct(private readonly QuranApi $quran) {}

    public function handle(MushafLesson $lesson, int $page): LessonMushaf
    {
        if (! $lesson->teaches()) {
            throw new AuthorizationException(__('quran::mushaf.errors.teacher'));
        }
        if ($this->quran->quranPage($page) === null) {
            throw ValidationException::withMessages(['page' => __('quran::mushaf.errors.page')]);
        }

        return LessonMushaf::query()->updateOrCreate(['session_id' => $lesson->card->id], ['page' => $page]);
    }
}
