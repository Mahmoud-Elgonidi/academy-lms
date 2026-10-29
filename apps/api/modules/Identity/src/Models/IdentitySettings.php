<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The academy's sign-in rules: whether two-step sign-in is required of the
 * owner, the admins and the accountants (yes while it has no row). Changed
 * only through SaveSecuritySettings; read through TwoFactor::requiredFor.
 *
 * @property bool $require_two_factor
 */
final class IdentitySettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['require_two_factor'];

    protected function casts(): array
    {
        return ['require_two_factor' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self(['require_two_factor' => true]);
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
