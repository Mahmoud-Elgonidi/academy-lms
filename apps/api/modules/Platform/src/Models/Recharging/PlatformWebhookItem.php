<?php

namespace Modules\Platform\Models\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One event from Stripe about the platform's own account, kept once by its
 * id (a repeated delivery does nothing). Platform connection only.
 *
 * @property string $id
 * @property string $event_id
 * @property string $event
 * @property ?string $ref
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $received_at
 * @property ?CarbonImmutable $processed_at
 * @property ?string $error
 */
final class PlatformWebhookItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'pgsql_platform';

    protected $table = 'platform_webhook_inbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
