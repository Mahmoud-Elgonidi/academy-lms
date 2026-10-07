<?php

namespace Modules\Payroll\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One line of a teacher's pay ledger (docs/architecture/04, "Ledger"): a
 * lesson, the reversal of an earlier line, a bonus or a deduction. Lines
 * are never changed (the database refuses); a correction is a reversal and
 * a new line, in the first month still open.
 *
 * @property string $id
 * @property string $teacher_id
 * @property CarbonImmutable $period
 * @property string $kind lesson | reversal | bonus | deduction
 * @property ?string $session_id
 * @property ?string $reverses_id
 * @property ?CarbonImmutable $lesson_on
 * @property ?string $outcome
 * @property ?int $rule
 * @property ?string $session_type
 * @property ?string $subject_id
 * @property ?int $booked_min
 * @property ?int $pay_pct
 * @property ?string $unit
 * @property ?int $rate_minor
 * @property int $amount_minor
 * @property string $currency
 * @property ?string $rate_card_id
 * @property ?string $note
 * @property ?string $created_by
 * @property CarbonImmutable $created_at
 */
final class TeacherEarning extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    public const ADJUSTMENTS = ['bonus', 'deduction'];

    protected $fillable = [
        'teacher_id', 'period', 'kind', 'session_id', 'reverses_id', 'lesson_on', 'outcome', 'rule', 'session_type', 'subject_id',
        'booked_min', 'pay_pct', 'unit', 'rate_minor', 'amount_minor', 'currency', 'rate_card_id', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'immutable_date',
            'lesson_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'amount_minor' => 'integer',
            'rate_minor' => 'integer',
            'booked_min' => 'integer',
            'pay_pct' => 'integer',
            'rule' => 'integer',
        ];
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    /** Lines not reversed since. */
    public function scopeLive($query)
    {
        return $query->whereNotExists(fn ($q) => $q->from('teacher_earnings as r')->whereColumn('r.academy_id', 'teacher_earnings.academy_id')->whereColumn('r.reverses_id', 'teacher_earnings.id'));
    }
}
