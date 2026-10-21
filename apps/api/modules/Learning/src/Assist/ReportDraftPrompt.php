<?php

namespace Modules\Learning\Assist;

use Modules\Learning\Domain\MasteryRule;
use Modules\Learning\Templates\TemplateField;
use Modules\SharedKernel\Ai\AiPrompt;

/**
 * What the language model is asked to turn a teacher's voice note into a
 * report draft: what the teacher said (the student named {child}), the
 * course's lessons by key (L1, L2…, never their ids), and the subject's own
 * questions. Nothing else about anyone: no name, no history, no family.
 */
final class ReportDraftPrompt
{
    /**
     * @param  'lesson'|'absence'  $kind
     * @param  array<string, string>  $lessons  key => "Level › Unit › Lesson"
     * @param  list<TemplateField>  $fields
     */
    public static function for(string $kind, string $said, array $lessons, array $fields): AiPrompt
    {
        $user = [$kind === 'absence'
            ? 'Report: the student did not come to this lesson (an absence report).'
            : 'Report: a lesson that took place.'];
        if ($kind === 'lesson' && $lessons !== []) {
            $user[] = "The course's lessons (key: level › unit › lesson):\n".implode("\n", array_map(fn ($k, $l) => "{$k}: {$l}", array_keys($lessons), $lessons));
        }
        if ($kind === 'lesson' && $fields !== []) {
            $user[] = "The academy's own questions (key: question [kind]):\n".implode("\n", array_map(fn (TemplateField $f) => $f->key.': '.$f->label().' ['.self::kind($f).']', $fields));
        }
        $user[] = "What the teacher said after the lesson:\n\"\"\"\n{$said}\n\"\"\"";

        return new AiPrompt(
            system: $kind === 'absence' ? self::ABSENCE : self::LESSON,
            user: implode("\n\n", $user),
            schema: $kind === 'absence' ? self::absenceSchema() : self::lessonSchema(array_keys($lessons), $fields),
            tier: 'fast',
            effort: 'low',
            maxTokens: 3000,
            demo: self::demo($kind, array_keys($lessons)),
        );
    }

    private const COMMON = <<<'TXT'
        You turn what a teacher said, just after a one-to-one online lesson, into a draft of the lesson report. The teacher reads the draft and changes what they like before sending it; nothing is sent as you write it.

        Use only what the teacher said. Leave out (do not include) every field the teacher did not speak to: never guess, never add praise or advice they did not give. The student is called {child}: write {child} wherever you name them.

        Write in the language the teacher spoke (Arabic in clear, simple Arabic as an Egyptian teacher would write it; English in plain English). Keep it short.

        "unsure": the fields you filled but are not sure of (their names as in this answer: "overall", "notes_for_student", "homework", a lesson's key such as "L2", a question's key).
        TXT;

    private const LESSON = self::COMMON.<<<'TXT'


        Fields:
        - lessons: the lessons the teacher said they worked on, by key, each with "outcome": introduced (met for the first time), practiced (worked on, not yet secure), mastered (secure, can do it alone), needs_review (struggled or forgot); "score" 1-5 only if the teacher rated it; "note" a few words about that lesson if they said any.
        - engagement and overall: 1 to 5, only if the teacher said how attentive the student was (engagement) or how the lesson went (overall); excellent 5, good 4, fair 3, weak 2, very weak 1.
        - notes_for_student: a few kind sentences to the student and their family: what went well and what to practise, from what the teacher said.
        - notes_internal: only what the teacher meant for the academy alone (scheduling, payment, a worry, behaviour they did not want the family to read).
        - answers: answers to the academy's questions the teacher spoke to, by the question's key: a number 1-5 for stars, a number for a number, the choice's key for a choice, "true" or "false" for yes or no, words for text.
        - homework: only if the teacher set one: "title", "instructions", "accepts" (any, text, audio or file: how the student hands it in), "due_days" (days to hand it in).
        TXT;

    private const ABSENCE = self::COMMON.<<<'TXT'


        Fields:
        - absence_note: what the teacher said about the absence, for the family (that {child} did not come, and what to do next if they said).
        - notes_internal: only what the teacher meant for the academy alone.
        TXT;

    private static function kind(TemplateField $field): string
    {
        return match ($field->type) {
            'rating' => 'stars 1-5',
            'number' => 'number'.($field->min !== null || $field->max !== null ? ' '.($field->min ?? '').'-'.($field->max ?? '') : ''),
            'choice' => 'one of: '.implode(', ', array_map(fn ($k, $l) => "{$k} ({$l})", array_keys($field->choices()), $field->choices())),
            'yes_no' => 'yes or no',
            default => 'text, up to 500 characters',
        };
    }

    /**
     * @param  list<string>  $keys
     * @param  list<TemplateField>  $fields
     * @return array<string, mixed>
     */
    private static function lessonSchema(array $keys, array $fields): array
    {
        $properties = [
            'engagement' => ['type' => 'integer'],
            'overall' => ['type' => 'integer'],
            'notes_for_student' => ['type' => 'string'],
            'notes_internal' => ['type' => 'string'],
            'homework' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'instructions' => ['type' => 'string'],
                    'accepts' => ['type' => 'string', 'enum' => ReportDraftMerge::ACCEPTS],
                    'due_days' => ['type' => 'integer'],
                ],
                'required' => ['title'],
                'additionalProperties' => false,
            ],
            'unsure' => ['type' => 'array', 'items' => ['type' => 'string']],
        ];
        if ($keys !== []) {
            $properties['lessons'] = ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'enum' => $keys],
                    'outcome' => ['type' => 'string', 'enum' => MasteryRule::OUTCOMES],
                    'score' => ['type' => 'integer'],
                    'note' => ['type' => 'string'],
                ],
                'required' => ['key', 'outcome'],
                'additionalProperties' => false,
            ]];
        }
        if ($fields !== []) {
            $properties['answers'] = ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'enum' => array_map(fn (TemplateField $f) => $f->key, $fields)],
                    'value' => ['type' => 'string'],
                ],
                'required' => ['key', 'value'],
                'additionalProperties' => false,
            ]];
        }

        return ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
    }

    /** @return array<string, mixed> */
    private static function absenceSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'absence_note' => ['type' => 'string'],
                'notes_internal' => ['type' => 'string'],
                'unsure' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * The demo's answer (AI_DRIVER=demo): the first lesson practised, a good lesson, a word to the family.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function demo(string $kind, array $keys): array
    {
        if ($kind === 'absence') {
            return ['absence_note' => '{child} ماحضرش الحصة النهارده، ومستنيينه الحصة الجاية.', 'unsure' => []];
        }

        return array_filter([
            'lessons' => $keys === [] ? null : [['key' => $keys[0], 'outcome' => 'practiced', 'note' => 'قرأ كويس، ومحتاج يراجع الآخر.']],
            'engagement' => 4,
            'overall' => 4,
            'notes_for_student' => 'برافو يا {child}! قريت كويس النهارده. راجع آخر جزء كل يوم لحد الحصة الجاية.',
            'unsure' => ['overall'],
        ], fn ($v) => $v !== null);
    }
}
