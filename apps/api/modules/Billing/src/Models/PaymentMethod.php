<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A payer's saved card at one of the academy's gateway accounts
 * (docs/architecture/02, section 27, J5b): only the gateway's own references
 * to it (encrypted, never shown, logged or exported), and what may be shown:
 * brand, last four digits, expiry. Live until revoked; one live per payer and
 * gateway account.
 *
 * @property string $id
 * @property string $payer_id
 * @property string $gateway_account_id
 * @property string $gateway
 * @property array<string, string> $tokens
 * @property string $brand
 * @property string $last4
 * @property int $exp_month
 * @property int $exp_year
 * @property string $mandate_version
 * @property string $mandate_hash
 * @property ?string $checkout_id
 * @property ?CarbonImmutable $expiry_noticed_at
 * @property ?CarbonImmutable $revoked_at
 * @property ?string $revoked_reason payer | replaced | gateway_off | erased | bank
 * @property CarbonImmutable $created_at
 */
final class PaymentMethod extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** The card's own reference at the gateway: never in an answer, a log line, an export or the audit. */
    protected $hidden = ['tokens', 'mandate_hash'];

    protected $fillable = [
        'payer_id', 'gateway_account_id', 'gateway', 'tokens', 'brand', 'last4', 'exp_month', 'exp_year',
        'mandate_version', 'mandate_hash', 'checkout_id', 'expiry_noticed_at', 'revoked_at', 'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'encrypted:array', 'exp_month' => 'integer', 'exp_year' => 'integer',
            'expiry_noticed_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    /** Lets the card go here: revoked, its references at the gateway dropped (what was shown stays, for the history). */
    public function revoke(string $reason): void
    {
        $this->forceFill(['revoked_at' => CarbonImmutable::now(), 'revoked_reason' => $reason, 'tokens' => []])->save();
    }

    /** @return BelongsTo<GatewayAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GatewayAccount::class, 'gateway_account_id');
    }

    /** The card's last moment: the end of its expiry month (UTC; a gateway refuses it from the next month). */
    public function expiresAt(): CarbonImmutable
    {
        return CarbonImmutable::create($this->exp_year, $this->exp_month, 1, 0, 0, 0, 'UTC')->endOfMonth();
    }

    public function expiresBefore(CarbonImmutable $moment): bool
    {
        return $this->expiresAt()->lessThan($moment);
    }

    /** "Visa ****4242" */
    public function label(): string
    {
        return __('billing::cards.brands.'.$this->brandKey()).' ****'.$this->last4;
    }

    public function brandKey(): string
    {
        $brand = strtolower($this->brand);

        return in_array($brand, ['visa', 'mastercard', 'mada', 'amex', 'discover', 'jcb', 'unionpay', 'diners'], true) ? $brand : 'card';
    }

    /**
     * What anyone may read about the card: brand, last four, expiry (never its references).
     *
     * @return array{id: string, brand: string, brand_label: string, last4: string, exp_month: int, exp_year: int, label: string}
     */
    public function shown(): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brandKey(),
            'brand_label' => __('billing::cards.brands.'.$this->brandKey()),
            'last4' => $this->last4,
            'exp_month' => $this->exp_month,
            'exp_year' => $this->exp_year,
            'label' => $this->label(),
        ];
    }

    /** The payer's live card that can pay in this currency: on an account that is on, takes it and still offered here. */
    public static function liveFor(string $payerId, string $currency): ?self
    {
        return self::query()->live()->with('account')->where('payer_id', $payerId)->orderByDesc('created_at')->get()
            ->first(fn (self $m) => $m->account !== null && $m->account->is_active && $m->account->takes($currency));
    }
}
