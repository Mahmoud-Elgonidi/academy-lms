<?php

namespace Modules\Support\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Enums\TicketStatus;

/**
 * A family's or a teacher's ticket to the academy: what it is about (and the
 * learner, lesson and teacher), whose turn it is, who at the academy has it,
 * and the targets for the first answer and the solution. A complaint carries
 * the academy's decision. Never deleted.
 *
 * @property string $id
 * @property int $number
 * @property TicketKind $kind
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property string $subject
 * @property string $requester_id
 * @property string $side family | teacher
 * @property ?string $student_id
 * @property ?string $session_id
 * @property ?string $teacher_id
 * @property string $opened_by
 * @property ?string $assignee_id
 * @property CarbonImmutable $respond_by
 * @property CarbonImmutable $resolve_by
 * @property ?CarbonImmutable $first_response_at
 * @property ?CarbonImmutable $resolved_at
 * @property ?CarbonImmutable $closed_at
 * @property CarbonImmutable $last_message_at
 * @property string $last_side requester | staff
 * @property ?CarbonImmutable $overdue_alerted_at
 * @property ?string $complaint_status pending | validated | dismissed
 * @property ?string $complaint_decided_by
 * @property ?CarbonImmutable $complaint_decided_at
 * @property ?string $complaint_note
 * @property CarbonImmutable $created_at
 */
final class Ticket extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** A resolved ticket closes by itself after a quiet week; until then the requester may reopen it. */
    public const CLOSE_AFTER_DAYS = 7;

    protected $fillable = ['number', 'kind', 'priority', 'status', 'subject', 'requester_id', 'side', 'student_id', 'session_id', 'teacher_id',
        'opened_by', 'assignee_id', 'respond_by', 'resolve_by', 'first_response_at', 'resolved_at', 'closed_at', 'last_message_at', 'last_side',
        'overdue_alerted_at', 'complaint_status', 'complaint_decided_by', 'complaint_decided_at', 'complaint_note'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'kind' => TicketKind::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'respond_by' => 'immutable_datetime',
            'resolve_by' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'overdue_alerted_at' => 'immutable_datetime',
            'complaint_decided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /** "#1024", as people quote it. */
    public function reference(): string
    {
        return '#'.$this->number;
    }

    /** The first answer is late: none yet, and its target has passed. */
    public function responseOverdue(?CarbonImmutable $now = null): bool
    {
        return $this->status->isActive() && $this->first_response_at === null && $this->respond_by->lessThan($now ?? CarbonImmutable::now());
    }

    public function resolutionOverdue(?CarbonImmutable $now = null): bool
    {
        return $this->status->isActive() && $this->resolve_by->lessThan($now ?? CarbonImmutable::now());
    }

    /** The requester may reopen a resolved ticket for a week; a closed one is done (they open a new one). */
    public function reopenable(?CarbonImmutable $now = null): bool
    {
        return $this->status === TicketStatus::Resolved
            && $this->resolved_at !== null && $this->resolved_at->addDays(self::CLOSE_AFTER_DAYS)->greaterThan($now ?? CarbonImmutable::now());
    }

    public function isComplaint(): bool
    {
        return $this->kind === TicketKind::Complaint;
    }
}
