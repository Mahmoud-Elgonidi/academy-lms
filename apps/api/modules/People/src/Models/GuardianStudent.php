<?php

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $academy_id
 * @property string $guardian_id
 * @property string $student_id
 * @property string $relationship
 * @property bool $is_primary
 */
final class GuardianStudent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'guardian_student';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['guardian_id', 'student_id', 'relationship', 'is_primary', 'receives_reports'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'receives_reports' => 'boolean'];
    }
}
