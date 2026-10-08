<?php

namespace Modules\Support\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Support\Attachments;
use Modules\Support\Contracts\Events\TicketOpened;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Models\SupportSettings;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;
use Throwable;

/**
 * A family or a teacher writes to the academy: what about (a complaint, a
 * bill, a technical problem, anything else), the learner, maybe a lesson,
 * their words and a picture or PDF. It gets the academy's next number and
 * its answer targets by priority (a complaint starts high); a complaint
 * about a lesson is about that lesson's teacher and waits for the academy's
 * decision. Someone at the academy may write one down for a family or a
 * teacher (a call, a visit), with a priority and the teacher it is about.
 */
final class OpenTicket
{
    public function __construct(
        private readonly SupportRules $rules,
        private readonly FamilyView $family,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  string  $side  family | teacher: the requester's
     * @param  ?string  $openedBy  someone at the academy writing it down; null when the requester writes it
     * @param  ?string  $priority  the academy's choice when it writes it down
     * @param  ?string  $teacherId  the academy's choice: who a complaint is about, when no lesson says
     */
    public function handle(
        string $requesterId,
        string $side,
        ?string $kind,
        ?string $subject,
        ?string $body,
        ?string $studentId = null,
        ?string $sessionId = null,
        ?UploadedFile $file = null,
        ?string $openedBy = null,
        ?string $priority = null,
        ?string $teacherId = null,
    ): Ticket {
        $byStaff = $openedBy !== null && $openedBy !== $requesterId;
        if ($byStaff && ! $this->rules->isStaff($openedBy)) {
            throw new AuthorizationException;
        }
        $kinds = $byStaff ? TicketKind::cases() : $this->rules->kindsFor($side, $requesterId);
        if ($side === 'teacher' && $byStaff) {
            $kinds = $this->rules->kindsFor('teacher');
        }
        if ($kinds === []) {
            throw new AuthorizationException;
        }
        $subject = trim((string) $subject);
        $body = trim((string) $body);
        $l = 'support::tickets.';
        Validator::make(compact('kind', 'subject', 'body', 'priority', 'file'), [
            'kind' => ['required', Rule::in(array_map(fn (TicketKind $k) => $k->value, $kinds))],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'file' => Attachments::rule(),
        ], [], trans($l.'attributes'))->validate();

        if ($side === 'family') {
            if ($studentId === null || ! $this->family->follows($requesterId, $studentId)) {
                throw ValidationException::withMessages(['learner' => __($l.'errors.learner')]);
            }
        } elseif ($side === 'teacher') {
            if (! $this->rules->isTeacher($requesterId)) {
                throw new AuthorizationException;
            }
            $studentId = null;
        } else {
            throw new AuthorizationException;
        }

        $lesson = null;
        if ($sessionId !== null && $sessionId !== '') {
            $lesson = $this->rules->linkableLesson($sessionId, $side, $studentId, $requesterId);
            if ($lesson === null) {
                throw ValidationException::withMessages(['session_id' => __($l.'errors.lesson')]);
            }
        }
        $kind = TicketKind::from($kind);
        $about = null;
        if ($kind === TicketKind::Complaint) {
            $about = $lesson?->teacherId;
            if ($about === null && $byStaff && $teacherId !== null && $teacherId !== '') {
                if (! $this->rules->isTeacher($teacherId)) {
                    throw ValidationException::withMessages(['teacher_id' => __($l.'errors.teacher')]);
                }
                $about = $teacherId;
            }
        }
        $priority = $byStaff && $priority ? TicketPriority::from($priority) : $kind->priority();
        $targets = SupportSettings::targetsFor($priority);

        $academyId = $this->tenants->require()->id;
        $id = (string) Str::uuid7();
        $stored = Attachments::store($file, $academyId, $id);
        try {
            return DB::transaction(function () use ($id, $academyId, $requesterId, $side, $kind, $subject, $body, $studentId, $lesson, $about, $openedBy, $byStaff, $priority, $targets, $stored) {
                // The academy's next number, one at a time.
                DB::statement("select pg_advisory_xact_lock(hashtext('support:number:' || ?))", [$academyId]);
                $number = (int) Ticket::query()->max('number') + 1;
                $now = CarbonImmutable::now();
                $ticket = Ticket::query()->forceCreate([
                    'id' => $id,
                    'number' => $number,
                    'kind' => $kind,
                    'priority' => $priority,
                    'status' => TicketStatus::Open,
                    'subject' => $subject,
                    'requester_id' => $requesterId,
                    'side' => $side,
                    'student_id' => $studentId,
                    'session_id' => $lesson?->id,
                    'teacher_id' => $about,
                    'opened_by' => $byStaff ? $openedBy : $requesterId,
                    'respond_by' => $now->addHours($targets['respond']),
                    'resolve_by' => $now->addHours($targets['resolve']),
                    'last_message_at' => $now,
                    'last_side' => 'requester',
                    'complaint_status' => $kind === TicketKind::Complaint ? 'pending' : null,
                ]);
                TicketMessage::query()->create([
                    'ticket_id' => $ticket->id,
                    'author_id' => $byStaff ? $openedBy : $requesterId,
                    'side' => 'requester',
                    'kind' => 'message',
                    'body' => $body,
                    'data' => $byStaff ? ['written_by_staff' => true] : [],
                ] + $stored);
                DB::afterCommit(fn () => event(new TicketOpened($academyId, $ticket->id)));

                return $ticket;
            });
        } catch (Throwable $e) {
            Attachments::forget($stored);
            throw $e;
        }
    }
}
