<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A teacher paid from a card other than the default one.
 *
 * @property string $id
 * @property string $teacher_id
 * @property string $rate_card_id
 */
final class TeacherRateCard extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['teacher_id', 'rate_card_id'];
}
