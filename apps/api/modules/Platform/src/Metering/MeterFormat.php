<?php

namespace Modules\Platform\Metering;

use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Plans\Meter;

/** How the meters read for people: amounts with their unit, and each ledger line in words. */
final class MeterFormat
{
    public static function amount(Meter $meter, int $n): string
    {
        return trans_choice('platform::metering.units.'.$meter->value, abs($n), ['n' => number_format(abs($n))]);
    }

    /** "+300 minutes", "−12 credits". */
    public static function signed(Meter $meter, int $n): string
    {
        return ($n > 0 ? '+' : ($n < 0 ? '−' : '')).number_format(abs($n));
    }

    /** What used it: a helper's name, a lesson, or nobody known. */
    public static function subject(?string $subject): string
    {
        return match (true) {
            $subject === null || $subject === '' => __('platform::metering.subjects.unknown'),
            $subject === 'lesson' => __('platform::metering.subjects.lesson'),
            default => app(Capabilities::class)->get($subject)?->label() ?? $subject,
        };
    }

    /** @param  array{kind: string, subject: ?string, ref_type: ?string, amount: int, overage: bool}  $line */
    public static function line(array $line): string
    {
        $l = 'platform::metering.lines.';

        return match (true) {
            $line['ref_type'] === 'overage' => __($l.'covered'),
            $line['ref_type'] === 'migration' => __($l.'migration', ['what' => self::subject($line['subject'])]),
            $line['kind'] === 'consume' => self::subject($line['subject']).($line['overage'] ? ' · '.__($l.'overage') : ''),
            $line['kind'] === 'purchase' && $line['ref_type'] === 'topup' => __($l.'purchase'),
            $line['kind'] === 'adjust' && $line['amount'] < 0 && $line['overage'] => __($l.'adjust').' · '.__($l.'overage'),
            default => __($l.$line['kind']),
        };
    }
}
