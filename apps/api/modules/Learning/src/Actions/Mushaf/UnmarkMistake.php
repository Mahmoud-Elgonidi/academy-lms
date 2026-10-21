<?php

namespace Modules\Learning\Actions\Mushaf;

use Carbon\CarbonImmutable;
use Modules\Learning\Models\RecitationMistake;
use Modules\Learning\Mushaf\MushafLesson;

/** The teacher takes a mark off a word in the lesson's mushaf (marked by mistake). */
final class UnmarkMistake
{
    public function __construct(private readonly MarkMistake $mark) {}

    public function handle(MushafLesson $lesson, int $surah, int $verse, int $word, ?CarbonImmutable $now = null): bool
    {
        $this->mark->open($lesson, $now);

        return RecitationMistake::query()->where('session_id', $lesson->card->id)
            ->where('surah', $surah)->where('verse', $verse)->where('word', $word)->delete() > 0;
    }
}
