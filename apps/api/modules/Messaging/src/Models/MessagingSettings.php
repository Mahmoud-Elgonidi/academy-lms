<?php

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Whether the academy's teachers and families write to each other here.
 * Without a row: on.
 *
 * @property bool $enabled
 */
final class MessagingSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function enabled(): bool
    {
        $own = self::query()->value('enabled');

        return $own === null ? true : (bool) $own;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
