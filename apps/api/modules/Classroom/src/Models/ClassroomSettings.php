<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Which video provider the academy's lessons use; the platform default when it has no row.
 *
 * @property string $provider livekit | external
 */
final class ClassroomSettings extends Model
{
    use BelongsToTenant;

    public const PROVIDERS = ['livekit', 'external'];

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['provider'];

    public static function current(): self
    {
        return self::query()->first() ?? new self(['provider' => config('classroom.provider', 'livekit')]);
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
