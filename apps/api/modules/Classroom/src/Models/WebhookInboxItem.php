<?php

namespace Modules\Classroom\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A provider webhook, kept once by its event id (docs/architecture/04, "Inbox").
 *
 * @property string $id
 * @property string $provider
 * @property string $event_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $received_at
 * @property ?CarbonImmutable $processed_at
 * @property ?string $error
 */
final class WebhookInboxItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'provider_webhook_inbox';

    protected $fillable = ['provider', 'event_id', 'event', 'payload', 'received_at', 'processed_at', 'error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
