<?php

namespace Modules\Safeguarding\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Safeguarding\Enums\ConcernCategory;
use Modules\Safeguarding\Enums\ConcernOutcome;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A concern about a child's safety, read only by the academy's safeguarding
 * leads (safeguarding.handle). What the reporter wrote and the reply they
 * read are encrypted with the application's key. Never deleted.
 *
 * @property string $id
 * @property string $academy_id
 * @property int $number
 * @property string $reporter_id
 * @property string $reporter_side student | guardian | teacher | staff
 * @property ?string $student_id
 * @property ?string $teacher_id
 * @property ?string $session_id
 * @property ConcernCategory $category
 * @property string $body
 * @property bool $urgent
 * @property ConcernStatus $status
 * @property ?string $acknowledged_by
 * @property ?CarbonImmutable $acknowledged_at
 * @property ?CarbonImmutable $escalated_at
 * @property ?ConcernOutcome $outcome
 * @property ?string $reporter_reply
 * @property ?string $closed_by
 * @property ?CarbonImmutable $closed_at
 * @property CarbonImmutable $created_at
 */
final class Concern extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'safeguarding_concerns';

    /** Nobody took a new concern within this long: the leads hear again. */
    public const ACK_MINUTES = 60;

    protected $fillable = ['number', 'reporter_id', 'reporter_side', 'student_id', 'teacher_id', 'session_id', 'category', 'body', 'urgent',
        'status', 'acknowledged_by', 'acknowledged_at', 'escalated_at', 'outcome', 'reporter_reply', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return [
            'category' => ConcernCategory::class,
            'status' => ConcernStatus::class,
            'outcome' => ConcernOutcome::class,
            'body' => 'encrypted',
            'reporter_reply' => 'encrypted',
            'urgent' => 'boolean',
            'number' => 'integer',
            'acknowledged_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ConcernEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ConcernEntry::class)->orderBy('created_at');
    }

    public function reference(): string
    {
        return '#'.$this->number;
    }
}
