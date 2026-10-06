<?php

namespace Modules\Curriculum\Contracts\DTOs;

/** One node of a curriculum tree, as other modules see it. */
final readonly class CurriculumNode
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public string $kind,
        public int $ordinal,
        public string $titleAr,
        public string $titleEn,
        public int $weight = 1,
        public int $estSessions = 1,
        public string $masteryRule = 'mastered_once',
        public ?string $objectives = null,
    ) {}

    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->titleAr : $this->titleEn;
    }
}
