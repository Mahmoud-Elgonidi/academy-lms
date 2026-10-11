<?php

namespace Modules\Platform\Games\Designer;

/** Everything the designer is told for one round of work on a draft. */
final readonly class DesignBrief
{
    /**
     * @param  array{key: string, name_ar: string, name_en: string, story_ar: string, colors: array<string, string>, music: string, creatures: list<string>}  $island
     * @param  list<string>  $stages
     * @param  list<string>  $kinds
     * @param  list<array{role: string, body: string}>  $conversation  the story and every message since, oldest first
     * @param  ?string  $currentHtml  the page as it stands (null: the first round)
     * @param  list<string>  $problems  what the package check or the preview found on the current page
     */
    public function __construct(
        public string $story,
        public string $topic,
        public array $island,
        public array $stages,
        public array $kinds,
        public array $conversation,
        public ?string $currentHtml,
        public array $problems,
    ) {}

    public function isRevision(): bool
    {
        return $this->currentHtml !== null;
    }
}
