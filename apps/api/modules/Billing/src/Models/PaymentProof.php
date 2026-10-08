<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A payer's proof of paying by hand (a transfer receipt), waiting for the
 * academy, or approved (it recorded payment_id) or turned down with a
 * reason. Never deleted; the file lives on the private disk.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $payer_id
 * @property string $channel family | link
 * @property string $method one of METHODS
 * @property int $amount_minor
 * @property string $currency
 * @property CarbonImmutable $paid_on the day the payer says they paid
 * @property ?string $reference
 * @property ?string $note
 * @property string $file_path
 * @property string $file_name
 * @property string $mime
 * @property int $size
 * @property string $status pending | approved | rejected
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?string $decision_note
 * @property ?string $payment_id
 * @property CarbonImmutable $created_at
 */
final class PaymentProof extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'manual_payment_proofs';

    /** How a family pays by hand and can show it (cash and card machines are recorded at the desk). */
    public const METHODS = ['bank_transfer', 'instapay', 'wallet', 'other'];

    /** Receipts: a photo or a screenshot, or the bank's PDF. */
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

    public const MAX_KB = 10 * 1024;

    public const DISK = 'local';

    protected $fillable = ['invoice_id', 'payer_id', 'channel', 'method', 'amount_minor', 'currency', 'paid_on', 'reference', 'note',
        'file_path', 'file_name', 'mime', 'size', 'status', 'decided_by', 'decided_at', 'decision_note', 'payment_id'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'size' => 'integer',
            'paid_on' => 'immutable_date',
            'decided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** The proof waiting on an invoice, if any. */
    public static function pendingFor(string $invoiceId): ?self
    {
        return self::query()->where('invoice_id', $invoiceId)->where('status', 'pending')->first();
    }

    /** The latest proof on each of these invoices. @return array<string, self> by invoice */
    public static function latestFor(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return self::query()->whereIn('id', DB::table('manual_payment_proofs')->whereIn('invoice_id', $invoiceIds)
            ->selectRaw('distinct on (invoice_id) id')->orderBy('invoice_id')->orderByDesc('created_at'))
            ->get()->keyBy('invoice_id')->all();
    }
}
