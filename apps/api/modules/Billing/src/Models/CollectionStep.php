<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\SharedKernel\Tenancy\BelongsToTenant;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A message of the collection timeline that went out, once
 * (docs/architecture/02, section 27, J5a; Domain\Collections\CollectionRules).
 *
 * @property string $id
 * @property ?string $invoice_id
 * @property ?string $subscription_id
 * @property string $payer_id
 * @property string $step
 * @property string $key
 * @property int $recipients
 * @property ?string $sent_by
 * @property CarbonImmutable $sent_at
 */
final class CollectionStep extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = ['invoice_id', 'subscription_id', 'payer_id', 'step', 'key', 'recipients', 'sent_by', 'sent_at'];

    protected function casts(): array
    {
        return ['recipients' => 'integer', 'sent_at' => 'immutable_datetime'];
    }

    /**
     * Claims a step: true when this call wrote it (and should send it), false when it was there already.
     *
     * @param  array<string, mixed>  $row
     */
    public static function claim(string $key, array $row): bool
    {
        return DB::table('collection_steps')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'academy_id' => app(TenantContext::class)->require()->id,
            'key' => $key,
            'recipients' => 0,
            'sent_at' => CarbonImmutable::now(),
        ] + $row) === 1;
    }

    /**
     * @param  list<string>  $invoiceIds
     * @return array<string, array<string, CarbonImmutable>> by invoice id: the steps already sent, with when
     */
    public static function sentFor(array $invoiceIds): array
    {
        $out = [];
        foreach (self::query()->whereIn('invoice_id', $invoiceIds)->get(['invoice_id', 'step', 'sent_at']) as $row) {
            $at = $out[$row->invoice_id][$row->step] ?? null;
            $out[$row->invoice_id][$row->step] = $at === null || $row->sent_at->greaterThan($at) ? $row->sent_at : $at;
        }

        return $out;
    }
}
