<?php

namespace Modules\Evaluation\Filament;

use Carbon\CarbonImmutable;
use Modules\Evaluation\Domain\Kpi;
use Modules\SharedKernel\Time\Clock;

/** KPI values and performance events as the screens show them: formatted, against their target, in this locale. */
final class KpiView
{
    /**
     * @param  array<string, array{value: ?float, n: int, meets: ?bool}>  $values
     * @return list<array{key: string, label: string, value: string, target: ?string, n: int, state: string, hint: string, group: string}>
     */
    public static function cards(array $values): array
    {
        $l = 'evaluation::kpis.';

        return array_map(function (Kpi $kpi) use ($values, $l) {
            $v = $values[$kpi->key] ?? ['value' => null, 'n' => 0, 'meets' => null];
            $enough = $v['value'] !== null && $v['n'] >= $kpi->minSample;

            return [
                'key' => $kpi->key,
                'label' => __($l.'names.'.$kpi->key),
                'value' => $enough ? self::format($kpi, $v['value']) : '—',
                'target' => $kpi->target !== null ? __($l.($kpi->higherIsBetter ? 'target_min' : 'target_max'), ['value' => self::format($kpi, $kpi->target)]) : null,
                'n' => $v['n'],
                'state' => match ($v['meets']) {
                    true => 'good', false => 'bad', default => 'none'
                },
                'hint' => $enough ? trans_choice($l.'sample.'.$kpi->key, $v['n'], ['count' => $v['n']]) : __($l.'not_enough'),
                'group' => $kpi->group,
            ];
        }, Kpi::all());
    }

    public static function format(Kpi $kpi, float $value): string
    {
        return match ($kpi->format) {
            'percent' => self::number($value * 100).'%',
            'minutes' => __('evaluation::kpis.minutes', ['count' => self::number($value)]),
            'per100' => __('evaluation::kpis.per100', ['count' => self::number($value)]),
            default => number_format($value, 2),
        };
    }

    /** Whole numbers stay whole (100, 0); others keep one decimal (13.3). */
    private static function number(float $n): string
    {
        return abs($n - round($n)) < 0.05 ? (string) (int) round($n) : number_format($n, 1);
    }

    /** One performance event in words; for the teacher, a rating stays without the family's name. */
    public static function event(array $e, string $tz, bool $anonymousRatings = false): array
    {
        $names = ! ($anonymousRatings && $e['kind'] === 'low_rating');
        $l = 'evaluation::kpis.events.';
        $locale = app()->getLocale();

        return [
            'session' => $e['session_id'],
            'kind' => $e['kind'],
            'text' => __($l.$e['kind'], ['minutes' => $e['minutes'] ?? '', 'stars' => $e['stars'] ?? '']),
            'lesson' => collect([$names ? $e['student'] : null, $locale === 'ar' ? $e['subject_ar'] : $e['subject_en'], CarbonImmutable::parse($e['at'])->setTimezone($tz)->locale($locale)->translatedFormat(Clock::pattern('D j M · H:i'))])->filter()->implode(' · '),
            'comment' => $e['comment'] ?? null,
            'excluded' => $e['excluded'] ? __('evaluation::kpis.excluded', ['reason' => __('evaluation::kpis.reasons.'.$e['excluded']->reason), 'by' => $e['excluded']->by ?? '']) : null,
            'note' => $e['excluded']->note ?? null,
        ];
    }
}
