<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A teacher or a student as the recording consents screen lists them: the
 * classroom's read-only view of Identity's accounts (never written from here).
 *
 * @property string $id
 * @property string $name
 */
final class RecordedPerson extends Model
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
