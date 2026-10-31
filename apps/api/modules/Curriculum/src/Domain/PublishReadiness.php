<?php

namespace Modules\Curriculum\Domain;

use Modules\Curriculum\Contracts\DTOs\CurriculumNode;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;

/**
 * Whether a curriculum version is ready to publish, read the same way by the
 * builder's readiness bar and by PublishVersion (pure). Blockers stop the
 * publishing: no lesson at all, or a level or unit with nothing in it.
 * Warnings let it through, said once more in the confirmation: a lesson with
 * no objective, and a lesson with no practice for the games.
 */
final readonly class PublishReadiness
{
    public const BLOCKER = 'blocker';

    public const WARNING = 'warning';

    /** @param  list<ReadinessItem>  $items  blockers first, then warnings, each in teaching order */
    private function __construct(public array $items, public int $percent) {}

    /**
     * @param  array<string, int>  $practice  lesson id => how many practice items it has
     */
    public static function of(CurriculumTree $tree, array $practice): self
    {
        $blockers = [];
        $warnings = [];
        $passed = 0;
        $checks = 0;
        $lessons = $tree->lessons();

        if ($lessons === []) {
            $blockers[] = new ReadinessItem(self::BLOCKER, 'no_lessons', null);
        }

        foreach ($tree->inOrder() as $node) {
            if ($node->kind === 'lesson') {
                foreach (self::lessonChecks($node, $practice) as $key => $ok) {
                    $checks++;
                    if ($ok) {
                        $passed++;
                    } else {
                        $warnings[] = new ReadinessItem(self::WARNING, $key, $node->id);
                    }
                }

                continue;
            }
            $checks++;
            if ($tree->lessons($node->id) === []) {
                $blockers[] = new ReadinessItem(self::BLOCKER, 'empty_'.$node->kind, $node->id);
            } else {
                $passed++;
            }
        }

        // Ready counts what is done; a blocker keeps it under a hundred.
        $percent = $lessons === [] ? 0 : (int) floor(100 * $passed / max(1, $checks));
        if ($blockers !== []) {
            $percent = min($percent, 99);
        }

        return new self([...$blockers, ...$warnings], $percent);
    }

    /**
     * @param  array<string, int>  $practice
     * @return array<string, bool> the warning's key => whether the lesson passes it
     */
    private static function lessonChecks(CurriculumNode $lesson, array $practice): array
    {
        return [
            'no_objective' => trim((string) $lesson->objectives) !== '',
            'no_practice' => ($practice[$lesson->id] ?? 0) > 0,
        ];
    }

    public function canPublish(): bool
    {
        return $this->blockers() === [];
    }

    /** @return list<ReadinessItem> */
    public function blockers(): array
    {
        return array_values(array_filter($this->items, fn (ReadinessItem $i) => $i->severity === self::BLOCKER));
    }

    /** @return list<ReadinessItem> */
    public function warnings(): array
    {
        return array_values(array_filter($this->items, fn (ReadinessItem $i) => $i->severity === self::WARNING));
    }
}
