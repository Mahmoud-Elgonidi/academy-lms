<?php

namespace Modules\Payroll\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\RateLine;
use Modules\Payroll\Models\RateCard;
use Modules\Payroll\Models\TeacherRateCard;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Money\Money;

/**
 * Creates or updates a rate card: its currency, its prices (by subject and
 * kind of lesson, by the hour or by the lesson) and the teachers paid from
 * it. A teacher is on one card at a time; the default card covers the rest.
 * Lines already in the ledger keep the price they were written at.
 */
final class SaveRateCard
{
    public const CURRENCIES = ['SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'JOD', 'EGP', 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'MYR', 'TRY', 'PKR', 'INR'];

    public const SESSION_TYPES = ['regular', 'trial', 'makeup', 'extra', 'assessment'];

    /**
     * @param  array{name: string, currency: string, is_default?: bool, lines?: list<array{subject_id?: ?string, session_type?: ?string, unit: string, amount: string|int|float|null}>, teachers?: list<string>}  $data
     */
    public function handle(?RateCard $card, array $data): RateCard
    {
        $name = trim((string) ($data['name'] ?? ''));
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages(['name' => __('payroll::rates.errors.name')]);
        }
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw ValidationException::withMessages(['currency' => __('payroll::rates.errors.currency')]);
        }

        $lines = [];
        foreach ($data['lines'] ?? [] as $row) {
            if (($row['amount'] ?? null) === null || $row['amount'] === '') {
                continue;
            }
            $type = ($row['session_type'] ?? null) ?: null;
            if ($type !== null && ! in_array($type, self::SESSION_TYPES, true)) {
                throw ValidationException::withMessages(['lines' => __('payroll::rates.errors.type')]);
            }
            $money = Money::parse($row['amount'], $currency);
            if ($money->minor < 0 || ! in_array($row['unit'] ?? null, RateLine::UNITS, true)) {
                throw ValidationException::withMessages(['lines' => __('payroll::rates.errors.amount')]);
            }
            $key = (($row['subject_id'] ?? null) ?: '*').'|'.($type ?? '*');
            if (isset($lines[$key])) {
                throw ValidationException::withMessages(['lines' => __('payroll::rates.errors.twice')]);
            }
            $lines[$key] = ['subject_id' => ($row['subject_id'] ?? null) ?: null, 'session_type' => $type, 'unit' => $row['unit'], 'amount_minor' => $money->minor];
        }

        return DB::transaction(function () use ($card, $name, $currency, $data, $lines) {
            $first = ! RateCard::query()->when($card, fn ($q) => $q->whereKeyNot($card->id))->exists();
            $default = $first || (bool) ($data['is_default'] ?? false) || (bool) $card?->is_default;
            if ($default) {
                RateCard::query()->where('is_default', true)->when($card, fn ($q) => $q->whereKeyNot($card->id))->update(['is_default' => false]);
            }

            $card ??= new RateCard;
            $card->fill(['name' => $name, 'currency' => $currency, 'is_default' => $default])->save();

            $card->lines()->delete();
            foreach ($lines as $line) {
                $card->lines()->create($line);
            }

            if (array_key_exists('teachers', $data)) {
                $teachers = array_values(array_unique(array_filter($data['teachers'] ?? [])));
                TeacherRateCard::query()->where('rate_card_id', $card->id)->whereNotIn('teacher_id', $teachers)->delete();
                foreach ($teachers as $teacherId) {
                    TeacherRateCard::query()->updateOrCreate(['teacher_id' => $teacherId], ['rate_card_id' => $card->id]);
                }
            }

            app(AuditTrail::class)->record('payroll.rates_saved', 'rate_card', $card->id, ['name' => $name, 'currency' => $currency, 'default' => $default, 'lines' => count($lines)]);

            return $card->load('lines');
        });
    }

    /** A card other than the default one goes; its teachers fall back to the default card. */
    public function delete(RateCard $card): void
    {
        if ($card->is_default) {
            throw ValidationException::withMessages(['card' => __('payroll::rates.errors.delete_default')]);
        }
        $card->delete();
    }
}
