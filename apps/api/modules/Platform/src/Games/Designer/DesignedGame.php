<?php

namespace Modules\Platform\Games\Designer;

/** What the designer made in one round. */
final readonly class DesignedGame
{
    /**
     * @param  list<string>  $stages
     * @param  list<string>  $kinds
     * @param  array<string, int>  $usage
     */
    public function __construct(
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
        public string $message,
        public string $html,
        public string $servedModel,
        public array $usage = [],
    ) {}
}
