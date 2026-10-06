<?php

namespace Modules\Scheduling\Queries\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Scheduling's read-only view of the enrollments table, for the enrolled
 * students screens (docs/architecture/04, rule 2: reads may cross modules,
 * writes go through EnrollmentApi). Saving it is refused.
 *
 * @property string $id
 * @property string $student_id
 * @property string $subject_id
 * @property string $teacher_id
 * @property EnrollmentStatus $status
 * @property ?string $credit_account_id
 * @property CarbonImmutable $created_at
 */
final class EnrolledStudent extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'enrollments';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['status' => EnrollmentStatus::class, 'start_date' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('Enrollments change through EnrollmentApi.');
    }
}
