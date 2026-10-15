<?php

namespace Modules\Curriculum\Practice;

use Modules\Curriculum\Contracts\DTOs\Passage;
use Modules\Curriculum\Models\Node;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Games\ItemKind;

/**
 * What the language model is asked for a lesson's practice: the lesson's
 * title, objectives and text (its words numbered, so it quotes them by
 * number and the platform puts in the words as kept), the items already
 * there, and how many more. Nothing about a person: a lesson is all it sees.
 */
final class PracticePrompt
{
    public static function for(Node $lesson, ?Passage $passage, array $existing, int $count): AiPrompt
    {
        $user = [
            'Lesson: '.$lesson->title_ar.' / '.$lesson->title_en,
        ];
        if (trim((string) $lesson->objectives) !== '') {
            $user[] = "Objectives:\n".trim((string) $lesson->objectives);
        }
        if ($passage !== null) {
            $numbered = [];
            foreach ($passage->words as $i => $word) {
                $numbered[] = ($i + 1).' '.$word;
            }
            $user[] = ($passage->source->isQuran() ? 'Text (a passage of the Quran, '.$passage->title.')' : 'Text').", its words numbered:\n".implode("\n", $numbered);
        }
        if ($existing !== []) {
            $user[] = "Items already on the lesson (do not repeat them):\n- ".implode("\n- ", $existing);
        }
        $user[] = "Write up to {$count} items.";

        return new AiPrompt(
            system: self::SYSTEM,
            user: implode("\n\n", $user),
            schema: self::schema(),
            tier: 'best',
            effort: 'low',
            maxTokens: 6000,
            demo: self::demo($passage, $lesson, $count),
        );
    }

    private const SYSTEM = <<<'TXT'
        You write practice items for one lesson of an online academy that teaches one to one, often children.
        A learner plays them in short games after the lesson. A teacher reviews every item before it is saved.

        Kinds of item:
        - choice: a question ("prompt", at most 160 characters) with 2 to 4 answers ("options", at most 60 characters each), "answer" the index of the right one (from 0). One answer is right; the others are plausible but clearly wrong to someone who learned the lesson.
        - order: parts to put in order. Give "parts_from" and "parts_to": the numbers of the first and last word of the text the learner puts back in order (2 to 8 words is best). An optional "prompt" (at most 160 characters) says what to do. Only for a lesson with a text.
        - pair: two things that go together, "left" and "right" (at most 60 characters each): a word and its meaning, a question and its answer.

        Quoting the lesson's text: its words are given numbered. To use them, write {{a-b}} (the numbers of the first and last word; {{a}} for one word) in a prompt, an option, left or right; the platform puts in those exact words. For a passage of the Quran, never write its words yourself, not even one phrase: always quote them by number. Do not explain or translate the Quran; ask about its words, their order, and what comes next.

        Write in the lesson's own language (the language of its text and title; Arabic lessons in clear Modern Standard Arabic). Keep items short and right for the lesson's level. Use only the lesson's own content. Never mention a person, a name or a place outside the lesson. Do not repeat the items already on the lesson. Mix the kinds when the lesson allows.
        TXT;

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => ItemKind::values()],
                            'prompt' => ['type' => 'string'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'answer' => ['type' => 'integer'],
                            'parts_from' => ['type' => 'integer'],
                            'parts_to' => ['type' => 'integer'],
                            'left' => ['type' => 'string'],
                            'right' => ['type' => 'string'],
                        ],
                        'required' => ['kind'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['items'],
            'additionalProperties' => false,
        ];
    }

    /**
     * The demo's answer (AI_DRIVER=demo): the passage's first words to put
     * in order and which word comes next, or a pair from the lesson's title.
     *
     * @return array<string, mixed>
     */
    private static function demo(?Passage $passage, Node $lesson, int $count): array
    {
        $items = [];
        $n = $passage ? count($passage->words) : 0;
        if ($n >= 4) {
            $items[] = ['kind' => 'order', 'prompt' => 'رتّب الكلمات', 'parts_from' => 1, 'parts_to' => min(6, $n)];
            $items[] = ['kind' => 'choice', 'prompt' => 'إيه الكلمة اللي بعد {{1-2}}؟', 'options' => ['{{3}}', '{{4}}'], 'answer' => 0];
        }
        $items[] = ['kind' => 'pair', 'left' => mb_substr($lesson->title_ar, 0, 60), 'right' => mb_substr($lesson->title_en, 0, 60)];

        return ['items' => array_slice($items, 0, $count)];
    }
}
