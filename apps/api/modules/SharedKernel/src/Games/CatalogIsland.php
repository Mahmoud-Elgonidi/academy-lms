<?php

namespace Modules\SharedKernel\Games;

/** An island of the games world: its look, its music and the creatures a learner collects there. */
final readonly class CatalogIsland
{
    /**
     * @param  array{sky: string, sea: string, land: string, accent: string}  $colors
     * @param  list<CatalogCreature>  $creatures  in the order they hatch
     */
    public function __construct(
        public string $key,
        public string $nameAr,
        public string $nameEn,
        public string $storyAr,
        public string $storyEn,
        public string $emoji,
        public array $colors,
        public string $music,
        public int $sort,
        public array $creatures,
    ) {}

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->nameAr : $this->nameEn;
    }

    public function story(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->storyAr : $this->storyEn;
    }
}
