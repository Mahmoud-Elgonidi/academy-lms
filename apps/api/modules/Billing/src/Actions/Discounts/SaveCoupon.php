<?php

namespace Modules\Billing\Actions\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Plan;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Money\Money;
use Throwable;

/**
 * A coupon made or changed (docs/architecture/02, section 3, "الخصومات").
 * Once it has been used, what it takes off, its currency and its code stay
 * as they were (those already on it were promised that); its name, dates,
 * number of uses, plans and whether it is on can still change.
 */
final class SaveCoupon
{
    /**
     * @param  array{code: string, name?: ?string, kind: string, percent?: float|int|string|null, amount?: float|int|string|null, currency?: ?string,
     *     blocks?: int|string|null, starts_on?: ?string, ends_on?: ?string, max_uses?: int|string|null, plan_ids?: list<string>, with_sibling?: bool, is_active?: bool}  $data
     */
    public function handle(?string $couponId, array $data, ?string $actorId): Coupon
    {
        return DB::transaction(function () use ($couponId, $data, $actorId) {
            $coupon = $couponId === null ? new Coupon(['created_by' => $actorId]) : Coupon::query()->lockForUpdate()->findOrFail($couponId);
            $values = $this->values($data);

            if ($coupon->exists && $coupon->uses() > 0) {
                foreach (['code', 'kind', 'percent_bp', 'amount_minor', 'currency', 'blocks', 'with_sibling'] as $field) {
                    if ($coupon->getAttribute($field) !== $values[$field]) {
                        throw ValidationException::withMessages([$field === 'code' ? 'code' : 'kind' => __('billing::discounts.errors.used_terms')]);
                    }
                }
            }
            if (Coupon::query()->where('code', $values['code'])->when($coupon->exists, fn ($q) => $q->whereKeyNot($coupon->id))->exists()) {
                throw ValidationException::withMessages(['code' => __('billing::discounts.errors.code_taken')]);
            }

            $coupon->fill($values)->save();
            app(AuditTrail::class)->record('billing.coupon_saved', 'coupon', $coupon->id, array_filter([
                'code' => $coupon->code,
                'value' => $coupon->valueLabel(),
                'blocks' => $coupon->blocks,
                'max_uses' => $coupon->max_uses,
                'active' => $coupon->is_active,
            ], fn ($v) => $v !== null));

            return $coupon;
        });
    }

    /** @return array<string, mixed> */
    private function values(array $data): array
    {
        $code = Coupon::normalize((string) ($data['code'] ?? ''));
        $kind = (string) ($data['kind'] ?? '');
        $errors = [];
        if (! preg_match('/^[A-Z0-9_-]{3,32}$/', $code)) {
            $errors['code'] = __('billing::discounts.errors.code');
        }
        if (! in_array($kind, ['percent', 'amount'], true)) {
            $errors['kind'] = __('billing::discounts.errors.kind');
        }

        $percentBp = null;
        $amountMinor = null;
        $currency = null;
        if ($kind === 'percent') {
            $percentBp = (int) round((float) ($data['percent'] ?? 0) * 100);
            if ($percentBp < 1 || $percentBp > 10_000) {
                $errors['percent'] = __('billing::discounts.errors.percent');
            }
        } elseif ($kind === 'amount') {
            $currency = strtoupper((string) ($data['currency'] ?? ''));
            try {
                $amountMinor = Money::parse((string) ($data['amount'] ?? 0), $currency)->minor;
            } catch (Throwable) {
                $field = preg_match('/^[A-Z]{3}$/', $currency) ? 'amount' : 'currency';
                $errors[$field] = __($field === 'amount' ? 'billing::discounts.errors.amount' : 'billing::discounts.errors.currency_required');
            }
            if ($amountMinor !== null && $amountMinor <= 0) {
                $errors['amount'] = __('billing::discounts.errors.amount');
            }
        }

        $blocks = filled($data['blocks'] ?? null) ? (int) $data['blocks'] : null;
        $maxUses = filled($data['max_uses'] ?? null) ? (int) $data['max_uses'] : null;
        if (($blocks !== null && ($blocks < 1 || $blocks > 120)) || ($maxUses !== null && $maxUses < 1)) {
            $errors['blocks'] = __('billing::discounts.errors.counts');
        }
        $starts = filled($data['starts_on'] ?? null) ? CarbonImmutable::parse($data['starts_on'])->toDateString() : null;
        $ends = filled($data['ends_on'] ?? null) ? CarbonImmutable::parse($data['ends_on'])->toDateString() : null;
        if ($starts !== null && $ends !== null && $ends < $starts) {
            $errors['ends_on'] = __('billing::discounts.errors.dates');
        }
        $plans = array_values(array_unique(array_map('strval', $data['plan_ids'] ?? [])));
        if ($plans !== [] && Plan::query()->whereIn('id', $plans)->count() !== count($plans)) {
            $errors['plan_ids'] = __('billing::discounts.errors.plans');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if (mb_strlen($name) > 80) {
            $errors['name'] = __('billing::discounts.errors.name');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'code' => $code,
            'name' => $name === '' ? null : $name,
            'kind' => $kind,
            'percent_bp' => $percentBp,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'blocks' => $blocks,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'max_uses' => $maxUses,
            'plan_ids' => $plans,
            'with_sibling' => (bool) ($data['with_sibling'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
