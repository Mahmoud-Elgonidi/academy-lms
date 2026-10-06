<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * An event a gateway sent, kept once per account (a unique event id): a
 * delivery repeated by the gateway does nothing the second time.
 *
 * @property string $id
 * @property string $gateway_account_id
 * @property string $event_id
 * @property string $event
 * @property ?string $provider_ref
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $received_at
 * @property ?CarbonImmutable $processed_at
 * @property ?string $error
 */
final class GatewayWebhookItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'gateway_webhook_inbox';

    protected $fillable = ['gateway_account_id', 'event_id', 'event', 'provider_ref', 'payload', 'received_at', 'processed_at', 'error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
