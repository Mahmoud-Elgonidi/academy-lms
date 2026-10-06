<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Money received against an invoice. Append-only (the database refuses
 * UPDATE and DELETE for the app role); a refund is its own record (later).
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $method
 * @property int $amount_minor
 * @property string $currency
 * @property ?string $reference
 * @property CarbonImmutable $received_at
 * @property ?string $recorded_by
 * @property ?string $note
 */
final class Payment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const METHODS = ['bank_transfer', 'instapay', 'wallet', 'cash', 'card_terminal', 'other'];

    public const UPDATED_AT = null;

    protected $fillable = ['invoice_id', 'method', 'amount_minor', 'currency', 'reference', 'received_at', 'recorded_by', 'note'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'received_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }
}
