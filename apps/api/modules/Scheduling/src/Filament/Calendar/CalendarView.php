<?php

namespace Modules\Scheduling\Filament\Calendar;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Queries\Calendar\CalendarRange;

/**
 * The calendar's lessons as the office reads them: on the viewer's clock,
 * placed by minutes of their day, in the current language; and the time
 * shaded behind them (shifts, time off, holidays) cut into the same days.
 */
final class CalendarView
{
    public function __construct(
        private readonly CalendarRange $range,
        private readonly CarbonImmutable $now,
    ) {}

    /** @return array<string, mixed> */
    public function lesson(object $r): array
    {
        $tz = $this->range->timezone;
        $start = $r->starts_at->setTimezone($tz);
        $end = $r->ends_at->setTimezone($tz);
        $from = $start->hour * 60 + $start->minute;
        $l = 'scheduling::calendar.';

        return [
            'id' => (string) $r->id,
            'date' => $start->toDateString(),
            'start' => $from,
            'end' => min(1440, $from + max(1, (int) $r->minutes)),
            'time' => $start->format('H:i').' – '.$end->format('H:i'),
            'from' => $start->format('H:i'),
            'minutes' => (int) $r->minutes,
            'subject' => (string) (app()->getLocale() === 'ar' ? $r->subject_ar : $r->subject_en),
            'teacher' => (string) $r->teacher,
            'teacherId' => (string) $r->teacher_id,
            'student' => (string) ($r->student ?? ''),
            'status' => (string) $r->status,
            'statusLabel' => __($l.'statuses.'.$r->status),
            'type' => $r->type === 'regular' ? null : (string) $r->type,
            'typeLabel' => $r->type === 'regular' ? null : __($l.'types.'.$r->type),
            'unpaid' => $r->funding_status === 'unfunded' && in_array($r->status, ['scheduled', 'in_progress'], true),
            'substitute' => $r->original_teacher !== null,
            'now' => $this->now->betweenIncluded($r->starts_at, $r->ends_at) && in_array($r->status, ['scheduled', 'in_progress'], true),
        ];
    }

    /**
     * Intervals cut into one day of the range: the minutes of that day they cover.
     *
     * @param  list<array{0: int, 1: int, 2?: string}>  $intervals  Unix seconds, and a label
     * @return list<array{start: int, end: int, label: ?string}>
     */
    public function onDay(array $intervals, CarbonImmutable $day): array
    {
        $dayStart = $this->range->instant($day)->getTimestamp();
        $dayEnd = $this->range->instant($day->addDay())->getTimestamp();
        $out = [];
        foreach ($intervals as $i) {
            $a = max($i[0], $dayStart);
            $b = min($i[1], $dayEnd);
            if ($b <= $a) {
                continue;
            }
            $out[] = ['start' => $a === $dayStart ? 0 : $this->minute($a), 'end' => $b === $dayEnd ? 1440 : $this->minute($b), 'label' => $i[2] ?? null];
        }

        return array_values(array_filter($out, fn (array $s) => $s['end'] > $s['start']));
    }

    /**
     * The hours the grid shows: from the earliest lesson or shift to the latest, at least
     * six hours, and 08:00–20:00 for an empty range.
     *
     * @param  list<array{start: int, end: int}>  $spans  minutes of a day
     * @return array{from: int, to: int}
     */
    public static function axis(array $spans): array
    {
        if ($spans === []) {
            return ['from' => 8, 'to' => 20];
        }
        $from = intdiv(min(array_column($spans, 'start')), 60);
        $to = (int) ceil(max(array_column($spans, 'end')) / 60);
        $to = min(24, max($to, $from + 1));
        while ($to - $from < 6) {
            $to < 24 && $to++;
            $to - $from < 6 && $from > 0 && $from--;
        }

        return ['from' => $from, 'to' => $to];
    }

    public function dayName(CarbonImmutable $day, string $format = 'l j F'): string
    {
        return $day->locale(app()->getLocale())->translatedFormat($format);
    }

    public function isToday(CarbonImmutable $day): bool
    {
        return $day->toDateString() === $this->now->setTimezone($this->range->timezone)->toDateString();
    }

    /** Minutes into today on the viewer's clock (the "now" line). */
    public function nowMinute(): int
    {
        return $this->minute($this->now->getTimestamp());
    }

    private function minute(int $timestamp): int
    {
        $t = CarbonImmutable::createFromTimestamp($timestamp, $this->range->timezone);

        return $t->hour * 60 + $t->minute;
    }
}
