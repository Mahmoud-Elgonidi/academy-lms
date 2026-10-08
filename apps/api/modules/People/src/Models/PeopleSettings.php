<?php

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The academy's rules about its students: under which age a guardian must
 * agree to a child's account (18 when it has no row; 16 or 13 where the law
 * the academy works under allows).
 *
 * @property int $consent_age
 */
final class PeopleSettings extends Model
{
    use BelongsToTenant;

    public const CONSENT_AGES = [18, 16, 13];

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['consent_age'];

    protected function casts(): array
    {
        return ['consent_age' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self(['consent_age' => 18]);
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
