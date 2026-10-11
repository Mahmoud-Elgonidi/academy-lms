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
        public array $look = [],
        public string $storyAr = '',
        public string $storyEn = '',
    ) {}

    /** How it is drawn (resources/views/games/creature): kind and colours, and the pitch of its voice. */
    public function kind(): string
    {
        return (string) ($this->look['kind'] ?? 'bird');
    }

    public function story(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->storyAr : $this->storyEn;
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->nameAr : $this->nameEn;
    }
}
