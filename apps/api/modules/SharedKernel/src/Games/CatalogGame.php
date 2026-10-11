<?php

namespace Modules\SharedKernel\Games;

/** A live game in the platform's catalog, at its published version. */
final readonly class CatalogGame
{
    /**
     * @param  list<string>  $stages  the learner looks it is made for (kids, middle, teen, classic)
     * @param  list<ItemKind>  $kinds  the practice items it plays
     */
    public function __construct(
        public string $id,
        public string $key,
        public string $islandKey,
        public string $versionId,
        public int $version,
        public string $titleAr,
        public string $titleEn,
        public string $summaryAr,
        public string $summaryEn,
        public string $howToPlayAr,
        public string $howToPlayEn,
        public array $stages,
        public array $kinds,
        public int $minItems,
        public int $maxItems,
        public int $sort,
    ) {}

    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->titleAr : $this->titleEn;
    }

    public function summary(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->summaryAr : $this->summaryEn;
    }

    public function howToPlay(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->howToPlayAr : $this->howToPlayEn;
    }

    public function plays(ItemKind $kind): bool
    {
        return in_array($kind, $this->kinds, true);
    }

    public function fits(string $stage): bool
    {
        return in_array($stage, $this->stages, true);
    }
}
