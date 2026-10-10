<?php

namespace Modules\Messaging\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A teacher and a student talking on the platform; the student's guardians
 * are always in it (through guardian_student, so a guardian linked later
 * reads it all). Never deleted. Locked by the academy, it is read only for
 * both sides until unlocked.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $teacher_id
 * @property string $student_id
 * @property ?CarbonImmutable $last_message_at
 * @property int $messages_count
 * @property int $hidden_count
 * @property ?CarbonImmutable $flagged_at
 * @property ?CarbonImmutable $reviewed_at
 * @property ?CarbonImmutable $locked_at
 * @property ?string $locked_by
 * @property ?string $lock_reason
 * @property CarbonImmutable $created_at
 */
final class Conversation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['teacher_id', 'student_id', 'last_message_at', 'messages_count', 'hidden_count', 'flagged_at', 'reviewed_at', 'locked_at', 'locked_by', 'lock_reason'];

    protected $attributes = ['messages_count' => 0, 'hidden_count' => 0];

    protected function casts(): array
    {
        return [
            'messages_count' => 'integer',
            'hidden_count' => 'integer',
            'last_message_at' => 'immutable_datetime',
            'flagged_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** Something was hidden or an outside app named since someone overseeing last read it. */
    public function needsLook(): bool
    {
        return $this->flagged_at !== null && ($this->reviewed_at === null || $this->reviewed_at->lessThan($this->flagged_at));
    }
}
