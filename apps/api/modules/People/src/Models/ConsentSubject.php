<?php

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A student as the consent screens list them: People's read-only view of
 * Identity's accounts (never written from here).
 *
 * @property string $id
 * @property string $name
 */
final class ConsentSubject extends Model
{
    use BelongsToTenant;

    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected static function booted(): void
    {
        self::saving(fn () => false);
        self::deleting(fn () => false);
    }
}
