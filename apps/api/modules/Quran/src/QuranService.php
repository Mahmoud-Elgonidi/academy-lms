<?php

namespace Modules\Quran;

use Modules\Quran\Contracts\DTOs\QuranPage;
use Modules\Quran\Contracts\DTOs\QuranPassage;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Models\LessonPassage;
use Modules\Quran\Texts\QuranText;

final class QuranService implements QuranApi
{
    public function __construct(private readonly QuranText $text) {}

    public function available(): bool
    {
        return $this->text->available();
    }

    public function surahs(): array
    {
        return $this->text->surahs();
    }

    public function passage(VerseRange $source): ?QuranPassage
    {
        return $this->text->passage($source);
    }

    public function quranPages(): array
    {
        return $this->text->pages();
    }

    public function quranPagesOf(VerseRange $source): array
    {
        return $this->text->pagesOf($source->surah, $source->from, $source->to);
    }

    public function quranPage(int $page): ?QuranPage
    {
        $found = $this->text->page($page);

        return $found === null ? null : new QuranPage($found['page'], $found['juz'], $found['verses'], $found['words'], $found['surahs'], $this->text->notice());
    }

    public function textSources(array $nodeIds): array
    {
        if ($nodeIds === []) {
            return [];
        }

        return LessonPassage::query()->whereIn('node_id', $nodeIds)->get()
            ->mapWithKeys(fn (LessonPassage $p) => [$p->node_id => $p->range()])->all();
    }
}
