<?php

namespace Modules\Curriculum\Outline;

use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\SharedKernel\Ai\AiPrompt;

/**
 * What the language model is asked for a curriculum outline: the subject and
 * its levels as the academy names them, the age group, how many lessons, and
 * the goal the academy typed. Nothing about a person.
 */
final class OutlinePrompt
{
    public const AGES = ['kids', 'middle', 'teen', 'adults', 'any'];

    private const AGE_WORDS = [
        'kids' => 'children aged 6 to 9',
        'middle' => 'children aged 10 to 13',
        'teen' => 'teenagers aged 14 to 17',
        'adults' => 'adults',
        'any' => 'learners of any age',
    ];

    /**
     * @param  list<string>  $levels  the subject's levels as the academy names them, in order
     */
    public static function for(string $subject, array $levels, string $age, int $lessons, string $goal): AiPrompt
    {
        $user = [
            'Subject: '.$subject,
            $levels === [] ? 'The academy has not named levels for this subject.' : "The academy's levels for this subject, in order (use them as the outline's levels when they fit the goal):\n- ".implode("\n- ", $levels),
            'Learners: '.(self::AGE_WORDS[$age] ?? self::AGE_WORDS['any']).'.',
            "Lessons in all: about {$lessons}.",
            "The academy's goal, in its own words:\n".$goal,
        ];

        return new AiPrompt(
            system: self::SYSTEM,
            user: implode("\n\n", $user),
            schema: self::schema(),
            tier: 'best',
            effort: 'medium',
            maxTokens: 16000,
            demo: self::demo($levels, $lessons),
        );
    }

    private const SYSTEM = <<<'TXT'
        You draft the outline of a curriculum for an online academy that teaches one to one, often children.
        The academy reviews and edits every line before anything is saved.

        The outline is levels (at most 12), each with lessons, grouped in units when that helps (a level uses units or lessons straight in it, not both). Each lesson has a short title (at most 80 characters) and one objective: what the learner can do after it, in one sentence (at most 200 characters). Levels and units have short titles.
        Give each title in Arabic (clear Modern Standard Arabic) and in English. Write objectives in the language the goal is written in.
        Fit the number of lessons asked for, in a sensible teaching order, from simple to harder, right for the learners' age.
        Use only what a curriculum needs. Never mention a person, a name, a price or a place.
        TXT;

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        $title = ['title_ar' => ['type' => 'string'], 'title_en' => ['type' => 'string']];
        $lesson = ['type' => 'object', 'properties' => $title + ['objective' => ['type' => 'string']], 'required' => ['title_ar', 'title_en', 'objective'], 'additionalProperties' => false];
        $lessons = ['type' => 'array', 'items' => $lesson];

        return [
            'type' => 'object',
            'properties' => [
                'levels' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => $title + [
                            'units' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => $title + ['lessons' => $lessons], 'required' => ['title_ar', 'title_en', 'lessons'], 'additionalProperties' => false]],
                            'lessons' => $lessons,
                        ],
                        'required' => ['title_ar', 'title_en', 'units', 'lessons'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['levels'],
            'additionalProperties' => false,
        ];
    }

    /**
     * The demo's answer (AI_DRIVER=demo): the academy's levels (or two of its own), the lessons shared
     * between them.
     *
     * @param  list<string>  $levels
     * @return array<string, mixed>
     */
    private static function demo(array $levels, int $lessons): array
    {
        $names = array_slice($levels !== [] ? $levels : ['المستوى الأول', 'المستوى الثاني'], 0, OutlineRules::LEVELS_MAX);
        $lessons = max(1, min($lessons, 24));
        $per = (int) ceil($lessons / count($names));
        $n = 0;

        return ['levels' => array_map(fn (string $name) => [
            'title_ar' => $name, 'title_en' => $name, 'units' => [],
            'lessons' => array_map(function () use (&$n) {
                $n++;

                return ['title_ar' => "الدرس {$n}", 'title_en' => "Lesson {$n}", 'objective' => "هدف تجريبي للدرس {$n}."];
            }, range(1, $per)),
        ], $names)];
    }
}
