<?php

namespace Modules\Communication\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One message in one person's inbox, already written in their language.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $user_id
 * @property string $template_key
 * @property string $category
 * @property string $title
 * @property string $body
 * @property array<string, string> $action
 * @property ?string $dedupe_key
 * @property bool $urgent
 * @property ?CarbonImmutable $read_at
 * @property CarbonImmutable $created_at
 */
final class Notification extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'template_key', 'category', 'title', 'body', 'action', 'dedupe_key', 'urgent', 'read_at'];

    protected function casts(): array
    {
        return ['action' => 'array', 'urgent' => 'boolean', 'read_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
