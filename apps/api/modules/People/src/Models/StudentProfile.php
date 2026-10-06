<?php

namespace Modules\People\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $user_id
 * @property ?CarbonImmutable $birth_date
 * @property ?string $stage
 */
final class StudentProfile extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'birth_date', 'stage'];

    protected function casts(): array
    {
        return ['birth_date' => 'immutable_date'];
    }
}
