<?php

namespace Modules\Payroll\Filament;

use Modules\Payroll\Support\PayrollMonths;
use Modules\SharedKernel\Time\Clock;

/** How payroll lines read on screen, for the back office and the teacher alike. */
final class PayrollView
{
    /**
     * @param  array<string, mixed>  $row  a lesson from PayrollMonth::statement()
     * @return array<string, mixed>
     */
    public static function lesson(array $row, string $tz): array
    {
        $l = 'payroll::payroll.';
        $line = $row['line'];
        $ar = str_starts_with(app()->getLocale(), 'ar');
        $notes = [];
        if ($row['earlier']) {
            $notes[] = __($l.'statement.earlier', ['month' => PayrollMonths::label($row['lesson_on'])]);
        }
        if ($row['corrected'] && $row['was'] !== null) {
            $notes[] = __($l.'statement.was', ['outcome' => __($l.'outcomes.'.$row['was']['outcome']), 'pct' => self::pct($row['was']['pay_pct'])]);
        }

        return [
            'key' => $row['session_id'],
            'when' => $row['starts_at'] ? $row['starts_at']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('D j M · H:i')) : $row['lesson_on']->translatedFormat('D j M'),
            'student' => (string) $row['student'],
            'subject' => (string) ($ar ? $row['subject_ar'] : $row['subject_en']),
            'type' => $line && $line['type'] !== 'regular' ? __($l.'types.'.$line['type']) : null,
            'outcome' => $line ? __($l.'outcomes.'.$line['outcome']) : __($l.'statement.corrected'),
            'state' => $line['outcome'] ?? 'corrected',
            'share' => match (true) {
                $line === null => null,
                $line['unit'] === 'hour' => __($l.'share', ['min' => $line['booked_min'], 'pct' => self::pct($line['pay_pct'])]),
                $line['pay_pct'] < 100 => __($l.'share_lesson', ['pct' => self::pct($line['pay_pct'])]),
                default => null,
            },
            'rate' => $line && $line['rate'] ? __($l.'units.'.$line['unit'], ['amount' => $line['rate']->format()]) : null,
            'amount' => $row['money']->format(),
            'negative' => $row['money']->minor < 0,
            'notes' => $notes,
            'corrected' => $row['corrected'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row  an adjustment from PayrollMonth::statement()
     * @return array<string, mixed>
     */
    public static function adjustment(array $row, string $tz): array
    {
        $l = 'payroll::payroll.';

        return [
            'id' => $row['id'],
            'kind' => $row['kind'] === 'reversal' ? __($l.'statement.reversal_of', ['kind' => __($l.'kinds.'.$row['of'])]) : __($l.'kinds.'.$row['kind']),
            'state' => $row['kind'] === 'reversal' ? 'reversal' : $row['kind'],
            'note' => (string) $row['note'],
            'amount' => $row['money']->format(),
            'negative' => $row['money']->minor < 0,
            'at' => $row['at']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M'),
            'live' => $row['live'],
            'reversed' => $row['reversed'],
        ];
    }

    /** "50%", kept in reading order inside Arabic text. */
    public static function pct(int $pct): string
    {
        return str_starts_with(app()->getLocale(), 'ar') ? "\u{2066}{$pct}%\u{2069}" : "{$pct}%";
    }

    public static function hours(float $minutes): string
    {
        $hours = round($minutes / 60, 1);

        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.') ?: '0';
    }
}
