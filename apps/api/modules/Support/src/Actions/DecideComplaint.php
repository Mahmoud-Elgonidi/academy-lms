<?php

namespace Modules\Support\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Support\Contracts\Events\ComplaintValidated;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;

/**
 * Whoever oversees the teachers decides a complaint: confirmed (it counts in
 * the teacher's complaints KPI from then on, and the teacher hears it, without
 * the family's name) or dismissed, always with the academy's reason, which
 * the family never sees. A decision can be changed later, again with a
 * reason; each one is in the activity log.
 */
final class DecideComplaint
{
    public const NOTE_MIN = 5;

    public function __construct(
        private readonly SupportRules $rules,
        private readonly AuditTrail $audit,
        private readonly TenantContext $tenants,
    ) {}

    public function handle(Ticket $ticket, bool $validated, ?string $note, string $actorId, ?string $teacherId = null): Ticket
    {
        if (! $this->rules->decidesComplaints($actorId)) {
            throw new AuthorizationException;
        }
        $l = 'support::tickets.';
        if (! $ticket->isComplaint()) {
            throw ValidationException::withMessages(['ticket' => __($l.'errors.not_complaint')]);
        }
        $note = trim((string) $note);
        Validator::make(['note' => $note], ['note' => ['required', 'string', 'min:'.self::NOTE_MIN, 'max:500']], [], ['note' => __($l.'attributes.complaint_note')])->validate();
        $teacher = $ticket->teacher_id;
        if ($teacher === null && $teacherId !== null && $teacherId !== '') {
            if (! $this->rules->isTeacher($teacherId)) {
                throw ValidationException::withMessages(['teacher_id' => __($l.'errors.teacher')]);
            }
            $teacher = $teacherId;
        }
        if ($validated && $teacher === null) {
            throw ValidationException::withMessages(['teacher_id' => __($l.'errors.teacher_needed')]);
        }
        $decision = $validated ? 'validated' : 'dismissed';
        if ($ticket->complaint_status === $decision && $ticket->teacher_id === $teacher) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $decision, $note, $actorId, $teacher, $validated) {
            $was = $ticket->complaint_status;
            $ticket->forceFill([
                'teacher_id' => $teacher,
                'complaint_status' => $decision,
                'complaint_decided_by' => $actorId,
                'complaint_decided_at' => CarbonImmutable::now(),
                'complaint_note' => $note,
            ])->save();
            TicketMessage::event($ticket, 'complaint', $actorId, ['decision' => $decision, 'from' => $was, 'note' => $note]);
            $this->audit->record('support.complaint_'.$decision, 'ticket', $ticket->id, [
                'ticket' => $ticket->reference(),
                'teacher' => $teacher === null ? null : (string) DB::table('users')->where('id', $teacher)->value('name'),
                'from' => $was,
                'note' => $note,
            ]);
            if ($validated) {
                $academyId = $this->tenants->require()->id;
                $session = $ticket->session_id;
                DB::afterCommit(fn () => event(new ComplaintValidated($academyId, $ticket->id, (string) $teacher, $session)));
            }

            return $ticket;
        });
    }
}
