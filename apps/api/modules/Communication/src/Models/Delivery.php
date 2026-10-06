<?php

namespace Modules\Communication\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One notification on one outside channel.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $notification_id
 * @property string $channel email | whatsapp | push
 * @property string $status held | queued | sent | failed | skipped
 * @property ?string $address
 * @property ?string $provider
 * @property ?string $provider_message_id
 * @property ?string $error
 * @property int $attempts
 * @property ?CarbonImmutable $not_before
 * @property ?CarbonImmutable $sent_at
 */
final class Delivery extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUSES = ['held', 'queued', 'sent', 'failed', 'skipped'];

    protected $table = 'notification_deliveries';

    protected $fillable = ['notification_id', 'channel', 'status', 'address', 'provider', 'provider_message_id', 'error', 'attempts', 'not_before', 'sent_at'];

    protected $attributes = ['status' => 'queued', 'attempts' => 0];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'not_before' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Notification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
