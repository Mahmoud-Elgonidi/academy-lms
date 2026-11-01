<?php

namespace Modules\Quran\Programs;

use Modules\Quran\Domain\Programs\MushafLayout;
use Modules\Quran\Texts\QuranText;

/**
 * The mushaf by numbers (Domain\Programs\MushafLayout) from the platform's
 * copy of the Quran: its surahs and verses, where its pages, parts and
 * quarters of a hizb begin, and each verse's words. Null until the copy is
 * in place: no portion is made without it.
 */
final class Mushaf
{
    private ?MushafLayout $layout = null;

    public function __construct(private readonly QuranText $text) {}

    public function layout(): ?MushafLayout
    {
        if ($this->layout !== null || ! $this->text->available()) {
            return $this->layout;
        }
        $starts = $this->text->layout();
        if ($starts['pages'] === [] || $starts['juzs'] === []) {
            return null;
        }

        return $this->layout = new MushafLayout(
            array_column($this->text->surahs(), 'verses', 'number'),
            $starts['pages'],
            $starts['juzs'],
            $starts['quarters'],
            $this->text->wordCounts(),
        );
    }

    /** @return array<int, int> surah => its verses */
    public function surahVerses(): array
    {
        return array_column($this->text->surahs(), 'verses', 'number');
    }
}
