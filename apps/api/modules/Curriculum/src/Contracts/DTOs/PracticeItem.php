<?php

namespace Modules\Curriculum\Contracts\DTOs;

use Modules\SharedKernel\Games\ItemKind;

/** A lesson's practice item, in the shape a game is handed. */
final readonly class PracticeItem
{
    /**
     * @param  list<string>  $options  choice
     * @param  list<string>  $parts  order, in the right order
     */
    public function __construct(
        public string $id,
        public string $nodeId,
        public ItemKind $kind,
        public ?string $prompt,
        public array $options = [],
        public ?int $answer = null,
        public array $parts = [],
        public ?string $left = null,
        public ?string $right = null,
    ) {}

    /** What the game page gets (resources/games/bridge.js): the shape, never the lesson. */
    public function forRound(): array
    {
        return match ($this->kind) {
            ItemKind::Choice => ['id' => $this->id, 'kind' => 'choice', 'prompt' => (string) $this->prompt, 'options' => $this->options, 'answer' => (int) $this->answer],
            ItemKind::Order => ['id' => $this->id, 'kind' => 'order', 'prompt' => (string) $this->prompt, 'parts' => $this->parts],
            ItemKind::Pair => ['id' => $this->id, 'kind' => 'pair', 'left' => (string) $this->left, 'right' => (string) $this->right],
        };
    }

    /** A short line for lists (the builder, the teacher's view of what was practised). */
    public function summary(): string
    {
        return match ($this->kind) {
            ItemKind::Choice => trim((string) $this->prompt).' → '.($this->options[(int) $this->answer] ?? ''),
            ItemKind::Order => trim(($this->prompt ? $this->prompt.': ' : '').implode(' · ', $this->parts)),
            ItemKind::Pair => $this->left.' ↔ '.$this->right,
        };
    }
}
