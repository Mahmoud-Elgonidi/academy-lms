<?php

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * What the academy knows of a guardian beyond their account: the country
 * they live in (a family's price region when the learner has none).
 *
 * @property string $user_id
 * @property ?string $country
 */
final class GuardianProfile extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'country'];
}
