<?php

namespace Modules\Curriculum\Domain;

use Modules\Curriculum\Contracts\DTOs\CurriculumNode;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;

/**
 * Whether a curriculum version is ready to publish, read the same way by the
 * builder's readiness bar and by PublishVersion (pure). Blockers stop the
 * publishing: no lesson at all, or a level or unit with nothing in it.
 * Warnings let it through, said once more in the confirmation: a lesson with
 * no objective, a lesson with no practice for the games, and, in a curriculum
 * that reads the Quran (a lesson of it names a passage), a lesson with no text.
 */
final readonly class PublishReadiness
{
    public const BLOCKER = 'blocker';

    public const WARNING = 'warning';

    /** @param  list<ReadinessItem>  $items  blockers first, then warnings, each in teaching order */
    private function __construct(public array $items, public int $percent) {}

    /**
     * @param  array<string, int>  $practice  lesson id => how many practice items it has
     * @param  array<string, string>  $texts  lesson id => its text's kind (text, quran); a lesson with none is left out
     */
    public static function of(CurriculumTree $tree, array $practice, array $texts): self
    {
        $blockers = [];
        $warnings = [];
        $passed = 0;
        $checks = 0;
        $lessons = $tree->lessons();
        $readsQuran = in_array('quran', $texts, true);

        if ($lessons === []) {
            $blockers[] = new ReadinessItem(self::BLOCKER, 'no_lessons', null);
        }

        foreach ($tree->inOrder() as $node) {
            if ($node->kind === 'lesson') {
                foreach (self::lessonChecks($node, $practice, $texts, $readsQuran) as $key => $ok) {
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
     * @param  array<string, string>  $texts
     * @return array<string, bool> the warning's key => whether the lesson passes it
     */
    private static function lessonChecks(CurriculumNode $lesson, array $practice, array $texts, bool $readsQuran): array
    {
        $checks = [
            'no_objective' => trim((string) $lesson->objectives) !== '',
            'no_practice' => ($practice[$lesson->id] ?? 0) > 0,
        ];
        if ($readsQuran) {
            $checks['no_text'] = isset($texts[$lesson->id]);
        }

        return $checks;
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
