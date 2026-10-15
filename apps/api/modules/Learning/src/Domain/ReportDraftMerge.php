<?php

namespace Modules\Learning\Domain;

use Modules\Learning\Templates\TemplateAnswers;
use Modules\Learning\Templates\TemplateField;

/**
 * Puts what the teacher said (the model's reading of their voice note) into
 * the report form they have open, without touching what they set
 * themselves: an empty field takes the suggestion, a filled one stays; a
 * lesson keeps its outcome unless it is still today's plan's guess. Lesson
 * keys (L1, L2…) go back to the lessons they stand for, every value is held
 * to the form's own limits, and a value the form would refuse is dropped.
 * Says which fields it filled, which of those the model was not sure of,
 * and what the report still needs. Saves nothing.
 */
final class ReportDraftMerge
{
    public const NOTES_MAX = 3000;

    public const NOTE_MAX = 500;

    public const ABSENCE_MAX = 1000;

    public const TITLE_MAX = 200;

    public const ITEMS_MAX = 10;

    /** What a suggested homework may take (a practice game is the teacher's own choice). */
    public const ACCEPTS = ['any', 'text', 'audio', 'file'];

    /**
     * @param  'lesson'|'absence'  $kind
     * @param  array<string, mixed>  $current  the form as it is now (what PUT teacher/reports/{report} takes)
     * @param  array<string, mixed>  $draft  the model's answer
     * @param  array<string, string>  $lessons  key (L1…) => lesson id
     * @param  array<string, string>  $labels  lesson id => "Level › Unit › Lesson"
     * @param  array<string, string>  $planned  lesson id => today's plan's outcome, for lessons on the form as the plan put them
     * @param  list<TemplateField>  $fields  the subject's questions
     * @return array{report: array<string, mixed>, filled: list<string>, unsure: list<string>, left: list<string>}
     */
    public static function merge(string $kind, array $current, array $draft, array $lessons, array $labels, array $planned, array $fields): array
    {
        $filled = [];
        $take = function (string $field, mixed $now, mixed $suggested) use (&$filled): mixed {
            if (self::blank($now) && ! self::blank($suggested)) {
                $filled[] = $field;

                return $suggested;
            }

            return self::blank($now) ? null : $now;
        };

        $report = ['notes_internal' => $take('notes_internal', self::text($current['notes_internal'] ?? null, self::NOTES_MAX), self::text($draft['notes_internal'] ?? null, self::NOTES_MAX))];
        if ($kind === 'absence') {
            $report['absence_note'] = $take('absence_note', self::text($current['absence_note'] ?? null, self::ABSENCE_MAX), self::text($draft['absence_note'] ?? null, self::ABSENCE_MAX));
            $left = self::blank($report['absence_note']) ? ['absence_note'] : [];

            return ['report' => $report, 'filled' => $filled, 'unsure' => self::unsure($draft, $filled, $lessons), 'left' => $left];
        }

        $report['engagement'] = $take('engagement', self::stars($current['engagement'] ?? null), self::stars($draft['engagement'] ?? null));
        $report['overall'] = $take('overall', self::stars($current['overall'] ?? null), self::stars($draft['overall'] ?? null));
        $report['notes_for_student'] = $take('notes_for_student', self::text($current['notes_for_student'] ?? null, self::NOTES_MAX), self::text($draft['notes_for_student'] ?? null, self::NOTES_MAX));

        [$report['items'], $itemsFilled] = self::items($current['items'] ?? [], $draft['lessons'] ?? [], $lessons, $labels, $planned);
        [$report['answers'], $answersFilled] = self::answers((array) ($current['answers'] ?? []), $draft['answers'] ?? [], $fields);
        $filled = [...$filled, ...$itemsFilled, ...$answersFilled];

        $homework = is_array($current['homework'] ?? null) ? $current['homework'] : null;
        $suggested = is_array($draft['homework'] ?? null) ? self::homework($draft['homework']) : null;
        if (self::blank($homework['title'] ?? null) && $suggested !== null) {
            $homework = $suggested;
            $filled[] = 'homework';
        }
        $report['homework'] = $homework;

        $left = array_keys(array_filter(['engagement' => $report['engagement'] === null, 'overall' => $report['overall'] === null, 'items' => $lessons !== [] && $report['items'] === []]));
        foreach ($fields as $field) {
            if ($field->required && ! array_key_exists($field->key, $report['answers'])) {
                $left[] = 'answers.'.$field->key;
            }
        }

        return ['report' => $report, 'filled' => $filled, 'unsure' => self::unsure($draft, $filled, $lessons), 'left' => $left];
    }

    /**
     * @param  mixed  $current  the form's lessons
     * @param  mixed  $said  the model's lessons
     * @param  array<string, string>  $lessons
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $planned
     * @return array{0: list<array{node_id: string, label: ?string, outcome: string, score: ?int, note: ?string}>, 1: list<string>}
     */
    private static function items(mixed $current, mixed $said, array $lessons, array $labels, array $planned): array
    {
        $items = [];
        foreach (is_array($current) ? $current : [] as $item) {
            $id = is_array($item) ? (string) ($item['node_id'] ?? '') : '';
            if ($id === '' || isset($items[$id])) {
                continue;
            }
            $items[$id] = [
                'node_id' => $id,
                'label' => $labels[$id] ?? null,
                'outcome' => in_array($item['outcome'] ?? null, MasteryRule::OUTCOMES, true) ? $item['outcome'] : null,
                'score' => self::stars($item['score'] ?? null),
                'note' => self::text($item['note'] ?? null, self::NOTE_MAX),
            ];
        }

        $filled = [];
        foreach (is_array($said) ? $said : [] as $lesson) {
            $id = is_array($lesson) ? ($lessons[(string) ($lesson['key'] ?? '')] ?? null) : null;
            $outcome = in_array($lesson['outcome'] ?? null, MasteryRule::OUTCOMES, true) ? $lesson['outcome'] : null;
            if ($id === null) {
                continue;
            }
            $score = self::stars($lesson['score'] ?? null);
            $note = self::text($lesson['note'] ?? null, self::NOTE_MAX);
            if (! isset($items[$id])) {
                if ($outcome === null || count($items) >= self::ITEMS_MAX) {
                    continue;
                }
                $items[$id] = ['node_id' => $id, 'label' => $labels[$id] ?? null, 'outcome' => $outcome, 'score' => $score, 'note' => $note];
                $filled[] = 'items.'.$id;

                continue;
            }
            // The plan's guess gives way to what the teacher said; their own choice does not.
            $guess = ($planned[$id] ?? null) !== null && $items[$id]['outcome'] === $planned[$id];
            if ($outcome !== null && ($items[$id]['outcome'] === null || $guess) && $outcome !== $items[$id]['outcome']) {
                $items[$id]['outcome'] = $outcome;
                $filled[] = 'items.'.$id.'.outcome';
            }
            foreach (['score' => $score, 'note' => $note] as $field => $value) {
                if (self::blank($items[$id][$field]) && ! self::blank($value)) {
                    $items[$id][$field] = $value;
                    $filled[] = 'items.'.$id.'.'.$field;
                }
            }
        }

        return [array_values(array_filter($items, fn (array $i) => $i['outcome'] !== null)), $filled];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  mixed  $said  [{key, value}]
     * @param  list<TemplateField>  $fields
     * @return array{0: array<string, int|float|string|bool>, 1: list<string>}
     */
    private static function answers(array $current, mixed $said, array $fields): array
    {
        $given = [];
        foreach (is_array($said) ? $said : [] as $pair) {
            if (is_array($pair) && is_string($pair['key'] ?? null) && array_key_exists('value', $pair)) {
                $given[$pair['key']] = $pair['value'];
            }
        }
        $answers = [];
        $filled = [];
        foreach ($fields as $field) {
            $now = TemplateAnswers::tryOne($field, $current[$field->key] ?? null);
            if ($now !== null) {
                $answers[$field->key] = $now;

                continue;
            }
            $value = TemplateAnswers::tryOne($field, $given[$field->key] ?? null);
            if ($value !== null) {
                $answers[$field->key] = $value;
                $filled[] = 'answers.'.$field->key;
            }
        }

        return [$answers, $filled];
    }

    /**
     * @param  array<string, mixed>  $said
     * @return ?array{title: string, instructions: ?string, accepts: string, due_days: int, practice: bool}
     */
    private static function homework(array $said): ?array
    {
        $title = self::text($said['title'] ?? null, self::TITLE_MAX);
        if ($title === null) {
            return null;
        }
        $days = is_numeric($said['due_days'] ?? null) ? (int) $said['due_days'] : 3;

        return [
            'title' => $title,
            'instructions' => self::text($said['instructions'] ?? null, self::NOTES_MAX),
            'accepts' => in_array($said['accepts'] ?? null, self::ACCEPTS, true) ? $said['accepts'] : 'any',
            'due_days' => max(1, min(30, $days)),
            'practice' => false,
        ];
    }

    /**
     * The fields the model named as unsure, as the form names them, among those it filled.
     *
     * @param  array<string, mixed>  $draft
     * @param  list<string>  $filled
     * @param  array<string, string>  $lessons
     * @return list<string>
     */
    private static function unsure(array $draft, array $filled, array $lessons): array
    {
        $named = [];
        foreach (is_array($draft['unsure'] ?? null) ? $draft['unsure'] : [] as $field) {
            $field = trim((string) $field);
            $named[] = isset($lessons[$field]) ? 'items.'.$lessons[$field] : (str_contains($field, '.') || in_array($field, ['engagement', 'overall', 'notes_for_student', 'notes_internal', 'absence_note', 'homework'], true) ? $field : 'answers.'.$field);
        }

        return array_values(array_filter($filled, fn (string $f) => in_array($f, $named, true)
            || in_array(implode('.', array_slice(explode('.', $f), 0, 2)), $named, true)));
    }

    private static function stars(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value == $value && $value >= 1 && $value <= 5 ? (int) $value : null;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }
}
