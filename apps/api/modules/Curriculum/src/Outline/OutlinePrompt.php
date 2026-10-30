<?php

namespace Modules\Curriculum\Outline;

use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\SharedKernel\Ai\AiPrompt;

/**
 * What the language model is asked for a curriculum outline: the subject and
 * its levels as the academy names them, the age group, how many lessons, and
 * the goal the academy typed. Nothing about a person. For the Quran, each
 * lesson is a passage by numbers alone (surah, first and last verse): the
 * platform puts in the names and words from its own copy.
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
     * @param  list<array{number: int, name_ar: string, name_en: string, verses: int}>  $surahs  the platform's surahs, for the demo's answer (Quran only)
     */
    public static function for(string $subject, array $levels, string $age, int $lessons, string $goal, bool $quran, array $surahs = []): AiPrompt
    {
        $user = [
            'Subject: '.$subject,
            $levels === [] ? 'The academy has not named levels for this subject.' : "The academy's levels for this subject, in order (use them as the outline's levels when they fit the goal):\n- ".implode("\n- ", $levels),
            'Learners: '.(self::AGE_WORDS[$age] ?? self::AGE_WORDS['any']).'.',
            "Lessons in all: about {$lessons}.",
            "The academy's goal, in its own words:\n".$goal,
        ];
        if ($quran) {
            $user[] = 'This is a Quran curriculum: each lesson is a passage given only by numbers (surah, from verse, to verse).';
        }

        return new AiPrompt(
            system: $quran ? self::SYSTEM.self::QURAN : self::SYSTEM,
            user: implode("\n\n", $user),
            schema: self::schema($quran),
            tier: 'best',
            effort: 'medium',
            maxTokens: 16000,
            demo: self::demo($levels, $lessons, $quran, $surahs),
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

    private const QURAN = <<<'TXT'


        For the Quran: never write any of the Quran's words, not even a phrase; give each lesson only "surah" (1 to 114), "from" and "to" (verse numbers in that surah). The platform writes the surah's name and words itself. A lesson is a passage a learner can take in one or two lessons. Objectives may say what to do (memorise, revise, recite with a rule of tajweed) but never quote the Quran.
        TXT;

    /** @return array<string, mixed> */
    private static function schema(bool $quran): array
    {
        $title = ['title_ar' => ['type' => 'string'], 'title_en' => ['type' => 'string']];
        $lesson = $quran
            ? ['type' => 'object', 'properties' => ['surah' => ['type' => 'integer'], 'from' => ['type' => 'integer'], 'to' => ['type' => 'integer'], 'objective' => ['type' => 'string']], 'required' => ['surah', 'from', 'to', 'objective'], 'additionalProperties' => false]
            : ['type' => 'object', 'properties' => $title + ['objective' => ['type' => 'string']], 'required' => ['title_ar', 'title_en', 'objective'], 'additionalProperties' => false];
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
     * between them; for the Quran, the last surahs a lesson each.
     *
     * @param  list<string>  $levels
     * @param  list<array{number: int, name_ar: string, name_en: string, verses: int}>  $surahs
     * @return array<string, mixed>
     */
    private static function demo(array $levels, int $lessons, bool $quran, array $surahs): array
    {
        $names = array_slice($levels !== [] ? $levels : ['المستوى الأول', 'المستوى الثاني'], 0, OutlineRules::LEVELS_MAX);
        $lessons = max(1, min($lessons, 24));
        if ($quran) {
            $passages = array_slice(array_reverse($surahs), 0, $lessons);
            $per = (int) ceil(max(1, count($passages)) / count($names));

            return ['levels' => array_values(array_filter(array_map(fn (string $name, int $i) => [
                'title_ar' => $name, 'title_en' => $name, 'units' => [],
                'lessons' => array_map(fn (array $s) => ['surah' => $s['number'], 'from' => 1, 'to' => $s['verses'], 'objective' => 'الحفظ والتلاوة الصحيحة.'], array_slice($passages, $i * $per, $per)),
            ], $names, array_keys($names)), fn (array $l) => $l['lessons'] !== []))];
        }
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
