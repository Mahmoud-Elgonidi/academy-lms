<?php

namespace Modules\Learning\Templates;

use Illuminate\Validation\ValidationException;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;

/**
 * A report's answers to its template: checked against each question (stars
 * 1–5, a number in its range, one of the choices, yes or no, up to 500
 * characters), stored by key, and read back with the question's words in
 * the reader's language. The family reads only the questions marked for it.
 */
final class TemplateAnswers
{
    public const TEXT_MAX = 500;

    /** @var array<string, ?ReportTemplate> */
    private static array $templates = [];

    /**
     * @param  array<string, mixed>  $input  answers by field key; anything else is dropped
     * @return array<string, int|float|string|bool> what is stored
     */
    public static function check(ReportTemplate $template, array $input): array
    {
        $out = [];
        foreach ($template->fieldList() as $field) {
            $value = $input[$field->key] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if ($value === null || $value === '') {
                if ($field->required) {
                    self::fail($field, 'required');
                }

                continue;
            }
            $out[$field->key] = self::one($field, $value);
        }

        return $out;
    }

    /**
     * The answers to show: each question that was answered, in the template's
     * order, with its words; `text` is the answer written out (for lists and
     * messages), `value` what the form holds.
     *
     * @param  array<string, mixed>  $answers
     * @return list<array{key: string, label: string, type: string, value: int|float|string|bool, text: string, family: bool, unit: ?string, max: ?int}>
     */
    public static function present(?ReportTemplate $template, array $answers, bool $internal, ?string $locale = null): array
    {
        if ($template === null || $answers === []) {
            return [];
        }
        $out = [];
        foreach ($template->fieldList() as $field) {
            if (! array_key_exists($field->key, $answers) || (! $internal && ! $field->family)) {
                continue;
            }
            $value = $answers[$field->key];
            $out[] = [
                'key' => $field->key,
                'label' => $field->label($locale),
                'type' => $field->type,
                'value' => $value,
                'text' => self::text($field, $value, $locale),
                'family' => (bool) $field->family,      // false: only the academy reads it
                'unit' => $field->type === 'number' ? $field->unit($locale) : null,
                'max' => $field->type === 'rating' ? 5 : null,          // stars out of
            ];
        }

        return $out;
    }

    /**
     * A report's answers as the screens show them, its template looked up once per request.
     *
     * @return list<array{key: string, label: string, type: string, value: int|float|string|bool, text: string, family: bool, unit: ?string, max: ?int}>
     */
    public static function ofReport(SessionReport $report, bool $internal): array
    {
        if ($report->template_id === null || ($report->answers ?? []) === []) {
            return [];
        }
        if (! array_key_exists($report->template_id, self::$templates)) {
            self::$templates[$report->template_id] = ReportTemplate::query()->find($report->template_id);
        }

        return self::present(self::$templates[$report->template_id], $report->answers, $internal);
    }

    /** The answer in words: "4/5", "3 pages", the choice's words, yes or no, the text. */
    public static function text(TemplateField $field, mixed $value, ?string $locale = null): string
    {
        return match ($field->type) {
            'rating' => __('learning::templates.answer.rating', ['n' => $value], $locale),
            'number' => trim(self::number($value).' '.($field->unit($locale, is_numeric($value) ? $value + 0 : null) ?? '')),
            'choice' => $field->choices($locale)[$value] ?? (string) $value,
            'yes_no' => __('learning::templates.answer.'.($value ? 'yes' : 'no'), [], $locale),
            default => (string) $value,
        };
    }

    /** One answer as it would be stored, or null when the question does not take it (a suggestion is dropped, not refused). */
    public static function tryOne(TemplateField $field, mixed $value): int|float|string|bool|null
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : self::judge($field, $value)[0];
    }

    private static function one(TemplateField $field, mixed $value): int|float|string|bool
    {
        [$stored, $rule, $extra] = self::judge($field, $value);

        return $stored ?? self::fail($field, $rule, $extra);
    }

    /**
     * The answer as stored, or why the question does not take it.
     *
     * @return array{0: int|float|string|bool|null, 1: string, 2: array<string, int>}
     */
    private static function judge(TemplateField $field, mixed $value): array
    {
        switch ($field->type) {
            case 'rating':
                return ! is_numeric($value) || (int) $value != $value || $value < 1 || $value > 5 ? [null, 'rating', []] : [(int) $value, '', []];
            case 'number':
                if (! is_numeric($value) || ($field->min !== null && $value < $field->min) || ($field->max !== null && $value > $field->max) || abs((float) $value) > 100000) {
                    return [null, 'number', ['min' => $field->min ?? 0, 'max' => $field->max ?? 100000]];
                }
                $number = round((float) $value, 2);

                return [$number == (int) $number ? (int) $number : $number, '', []];
            case 'choice':
                return ! is_string($value) || ! array_key_exists($value, $field->choices('en')) ? [null, 'choice', []] : [$value, '', []];
            case 'yes_no':
                $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                return $bool === null ? [null, 'yes_no', []] : [$bool, '', []];
            default:
                return ! is_scalar($value) || mb_strlen((string) $value) > self::TEXT_MAX ? [null, 'text', ['max' => self::TEXT_MAX]] : [(string) $value, '', []];
        }
    }

    private static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /** @param  array<string, mixed>  $extra */
    private static function fail(TemplateField $field, string $rule, array $extra = []): never
    {
        throw ValidationException::withMessages([
            'answers.'.$field->key => __('learning::templates.errors.answer.'.$rule, ['field' => $field->label()] + $extra),
        ]);
    }
}
