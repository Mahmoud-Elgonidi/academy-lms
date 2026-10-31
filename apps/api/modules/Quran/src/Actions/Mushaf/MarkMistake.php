<?php

namespace Modules\Quran\Actions\Mushaf;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Models\RecitationMistake;
use Modules\Quran\Mushaf\MushafLesson;
use Modules\Quran\Mushaf\MushafLessons;

/**
 * The teacher marks a word in the lesson's mushaf: wrong, a hesitation, or
 * tajweed. One mark a word a lesson; marking it again changes what it was.
 * The word is its surah, its verse and its place in the verse from 1, and
 * must be there in the platform's copy.
 */
final class MarkMistake
{
    public function __construct(
        private readonly MushafLessons $lessons,
        private readonly QuranApi $quran,
    ) {}

    public function handle(MushafLesson $lesson, int $surah, int $verse, int $word, string $kind, ?CarbonImmutable $now = null): RecitationMistake
    {
        $this->open($lesson, $now);
        Validator::make(['kind' => $kind], ['kind' => ['required', Rule::in(RecitationMistake::KINDS)]], [], ['kind' => __('quran::mushaf.attributes.kind')])->validate();
        self::wordThere($this->quran, $surah, $verse, $word);

        return RecitationMistake::query()->updateOrCreate(
            ['session_id' => $lesson->card->id, 'surah' => $surah, 'verse' => $verse, 'word' => $word],
            ['student_id' => $lesson->studentId(), 'enrollment_id' => $lesson->card->enrollmentId, 'teacher_id' => $lesson->viewerId, 'kind' => $kind],
        );
    }

    /** The teacher may mark now, or why not. */
    public function open(MushafLesson $lesson, ?CarbonImmutable $now = null): void
    {
        if (! $lesson->teaches()) {
            throw new AuthorizationException(__('quran::mushaf.errors.teacher'));
        }
        if (! $this->lessons->marksOpen($lesson, $now ?? CarbonImmutable::now())) {
            throw ValidationException::withMessages(['word' => __('quran::mushaf.errors.closed')]);
        }
    }

    /** The word must be there: its verse in the platform's copy, and that many words in it. */
    public static function wordThere(QuranApi $quran, int $surah, int $verse, int $word): void
    {
        $passage = $surah >= 1 && $verse >= 1 ? $quran->passage(VerseRange::of($surah, $verse, $verse)) : null;
        if ($passage === null || $word < 1 || $word > count($passage->words)) {
            throw ValidationException::withMessages(['word' => __('quran::mushaf.errors.word')]);
        }
    }
}
