<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A course the family started on its own (StartOwnCourse; docs/architecture/02,
 * section 27, J3): who started and pays, the plan and price it began at, the
 * teacher or group, whether the trial's offer went on it, and when the family
 * called it off before paying (CancelOwnStart).
 *
 * @property string $id
 * @property string $enrollment_id
 * @property string $learner_id
 * @property string $started_by
 * @property string $payer_id
 * @property string $plan_id
 * @property string $teacher_id
 * @property ?string $group_id
 * @property int $price_minor
 * @property string $currency
 * @property bool $offer_applied
 * @property int $extra_validity_days days the first block lasts longer for the weeks it skipped (given at the first payment)
 * @property ?CarbonImmutable $cancelled_at
 * @property CarbonImmutable $created_at
 */
final class CourseStart extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'course_starts';

    protected $fillable = ['enrollment_id', 'learner_id', 'started_by', 'payer_id', 'plan_id', 'teacher_id', 'group_id', 'price_minor', 'currency', 'offer_applied', 'cancelled_at', 'extra_validity_days'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'offer_applied' => 'boolean', 'extra_validity_days' => 'integer', 'cancelled_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
