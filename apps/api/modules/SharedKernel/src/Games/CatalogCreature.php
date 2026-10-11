<?php

namespace Modules\SharedKernel\Games;

/** A creature a learner hatches on an island (common, rare, legendary). */
final readonly class CatalogCreature
{
    public const RARITIES = ['common', 'rare', 'legendary'];

    public function __construct(
        public string $key,
        public string $islandKey,
        public string $nameAr,
        public string $nameEn,
        public string $emoji,
        public string $rarity,
        public int $sort,
    ) {}

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->nameAr : $this->nameEn;
    }
}
