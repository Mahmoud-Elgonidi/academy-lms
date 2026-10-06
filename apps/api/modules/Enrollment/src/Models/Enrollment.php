<?php

namespace Modules\Enrollment\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $academy_id
 * @property string $student_id
 * @property string $subject_id
 * @property string $teacher_id
 * @property EnrollmentStatus $status
 * @property CarbonImmutable $start_date
 * @property ?string $credit_account_id
 * @property ?string $created_by
 * @property ?CarbonImmutable $ended_at
 */
final class Enrollment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['student_id', 'subject_id', 'teacher_id', 'status', 'start_date', 'credit_account_id', 'created_by'];

    protected $attributes = ['status' => 'pending_payment'];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'start_date' => 'immutable_date',
            'ended_at' => 'immutable_datetime',
        ];
    }

    /** Moves to $to and writes the history line; callers hold the transaction. */
    public function moveTo(EnrollmentStatus $to, ?string $reason, ?string $actorId): void
    {
        $from = $this->status;
        $this->status = $to;
        $this->ended_at = $to === EnrollmentStatus::Ended ? CarbonImmutable::now() : null;
        $this->save();

        $this->logStatus($from, $to, $reason, $actorId);
    }

    /** One line of the append-only status history. */
    public function logStatus(?EnrollmentStatus $from, EnrollmentStatus $to, ?string $reason, ?string $actorId): void
    {
        DB::table('enrollment_status_history')->insert([
            'academy_id' => $this->academy_id,
            'enrollment_id' => $this->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'actor_id' => $actorId,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }

    public function toData(): EnrollmentData
    {
        return new EnrollmentData($this->id, $this->student_id, $this->subject_id, $this->teacher_id, $this->status, $this->start_date, $this->credit_account_id);
    }
}
