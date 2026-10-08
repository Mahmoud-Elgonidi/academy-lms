<?php

namespace Modules\Support\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\Support\Actions\CloseTicket;
use Modules\Support\Actions\OpenTicket;
use Modules\Support\Actions\ReopenTicket;
use Modules\Support\Actions\ReplyToTicket;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Queries\MyTickets;
use Modules\Support\Queries\TicketDetails;
use Modules\Support\SupportRules;
use Modules\Support\TicketView;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A family's or a teacher's tickets to the academy, for the apps (the same
 * as "Help" in /my and "Support" in /teach): what a new one can be about,
 * writing one, following the answers, answering back, reopening a resolved
 * one within its week, and closing one. A family names the learner
 * (learner_id); a teacher writes for themselves. Each person sees only their
 * own tickets, never the academy's internal notes or decisions.
 */
final class TicketsController
{
    public function __construct(
        private readonly SupportRules $rules,
        private readonly FamilyView $family,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * What a new ticket can be about.
     *
     * The kinds this person may choose (a teacher: technical, pay, anything else; a family: also a complaint, and
     * money only in the classic look; nothing for a child in the kids' look), and the lessons it may be about
     * (the learner's or the teacher's, newest first). A family names the learner with `learner_id` (else the first).
     */
    public function options(Request $request): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        [$side, $learner] = $this->who($request, $me);
        $kinds = [];
        foreach ($this->rules->kindsFor($side, $me) as $k) {
            $kinds[] = ['key' => $k->value, 'label' => $k->label()];
        }
        $lessons = [];
        foreach ($kinds === [] ? [] : $this->rules->lessonChoices($side, $learner?->id, $me) as $l) {
            $lessons[] = $this->lesson($l, $side);
        }

        return response()->json(['data' => [
            /** The learner it would be about; null for a teacher. */
            'learner' => $learner ? ['id' => $learner->id, 'name' => $learner->name] : null,
            /** complaint | billing | technical | other, with each one's words; empty: this person cannot open tickets here. */
            'kinds' => $kinds,
            /** Lessons from the last two months and the next week. */
            'lessons' => $lessons,
        ]]);
    }

    /**
     * My tickets.
     *
     * The tickets this person wrote, the active ones first (open: the academy's turn; waiting: theirs), then the
     * latest. A family may narrow them to one learner with `learner_id`.
     */
    public function index(Request $request, MyTickets $tickets): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $this->who($request, $me);
        $learner = $request->filled('learner_id') ? $this->family->learner($me, (string) $request->query('learner_id')) : null;

        $data = [];
        foreach ($tickets->of($me, $learner?->id) as $t) {
            $data[] = $this->summary($t);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Write to the academy.
     *
     * A family names the learner (`learner_id`); `kind` is one of what "new" offered; `session_id` optionally a lesson
     * from there; `file` optionally a picture or PDF (10 MB). Answers `201` with the ticket. Send an `Idempotency-Key`.
     */
    public function store(Request $request, OpenTicket $open): JsonResponse
    {
        $request->validate([
            /** A family: the learner it is about (one of theirs). */
            'learner_id' => ['nullable', 'string'],
            'kind' => ['required', 'string', 'in:complaint,billing,technical,other'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            /** One of the lessons "new" offered. */
            'session_id' => ['nullable', 'string'],
            /** A picture or PDF. */
            'file' => ['nullable', 'file', 'max:'.TicketMessage::MAX_KB],
        ]);
        $me = (string) $request->user()?->getAuthIdentifier();
        [$side, $learner] = $this->who($request, $me);
        $ticket = $open->handle(
            requesterId: $me,
            side: $side,
            kind: $request->input('kind'),
            subject: $request->input('subject'),
            body: $request->input('body'),
            studentId: $learner?->id,
            sessionId: $request->input('session_id') ?: null,
            file: $request->file('file'),
        );

        return response()->json(['data' => $this->full($ticket->refresh())], 201);
    }

    /**
     * One of my tickets.
     *
     * The ticket with its lines as the requester sees them: the messages both ways and when it was resolved,
     * reopened or closed.
     */
    public function show(Request $request, string $ticket): JsonResponse
    {
        return response()->json(['data' => $this->full($this->mine($request, $ticket))]);
    }

    /**
     * Answer the academy.
     *
     * `body` and/or `file` (a picture or PDF, 10 MB). It is the academy's turn again; a resolved ticket reopens
     * within its week. A closed one takes no more messages (open a new one). Send an `Idempotency-Key`.
     */
    public function reply(Request $request, string $ticket, ReplyToTicket $reply): JsonResponse
    {
        $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            /** A picture or PDF; a message has words, a file or both. */
            'file' => ['nullable', 'file', 'max:'.TicketMessage::MAX_KB],
        ]);
        $mine = $this->mine($request, $ticket);
        $reply->handle($mine, (string) $request->user()?->getAuthIdentifier(), $request->input('body'), $request->file('file'));

        return response()->json(['data' => $this->full($mine->refresh())], 201);
    }

    /**
     * Reopen a resolved ticket.
     *
     * "It is not solved": within a week of the academy resolving it.
     */
    public function reopen(Request $request, string $ticket, ReopenTicket $reopen): JsonResponse
    {
        $mine = $this->mine($request, $ticket);
        $reopen->handle($mine, (string) $request->user()?->getAuthIdentifier());

        return response()->json(['data' => $this->full($mine->refresh())]);
    }

    /**
     * Close my ticket.
     *
     * Solved, or no longer needed. It takes no more messages.
     */
    public function close(Request $request, string $ticket, CloseTicket $close): JsonResponse
    {
        $mine = $this->mine($request, $ticket);
        $close->handle($mine, (string) $request->user()?->getAuthIdentifier());

        return response()->json(['data' => $this->full($mine->refresh())]);
    }

    /** The picture or PDF on one of the messages of my ticket. */
    public function file(Request $request, string $ticket, string $message): StreamedResponse
    {
        $mine = $this->mine($request, $ticket);
        $line = TicketMessage::query()->where('ticket_id', $mine->id)->findOrFail($message);
        abort_unless($line->isPublic() && $line->file_path !== null, 404);

        return Storage::disk(TicketMessage::DISK)->download($line->file_path, $line->file_name, ['Content-Type' => (string) $line->mime]);
    }

    /** @return array{0: string, 1: ?Learner} the requester's side, and the learner a family's ticket is about */
    private function who(Request $request, string $me): array
    {
        if ($this->rules->isTeacher($me)) {
            return ['teacher', null];
        }
        $learner = $this->family->learner($me, $request->input('learner_id') ?: $request->query('learner_id'));
        abort_if($learner === null, 403);

        return ['family', $learner];
    }

    private function mine(Request $request, string $ticket): Ticket
    {
        return Ticket::query()->where('requester_id', (string) $request->user()?->getAuthIdentifier())->findOrFail($ticket);
    }

    private function summary(Ticket $t): array
    {
        return [
            'id' => $t->id,
            /** "#1024": what to quote when calling the academy. */
            'reference' => $t->reference(),
            'subject' => $t->subject,
            /** complaint | billing | technical | other */
            'kind' => ['key' => $t->kind->value, 'label' => $t->kind->label()],
            /** open (the academy's turn) | waiting (yours) | resolved | closed */
            'status' => ['key' => $t->status->value, 'label' => $t->status->label()],
            /** The learner it is about (a family's ticket). */
            'learner' => $t->student_id ? ['id' => $t->student_id, 'name' => (string) ($t->getAttribute('student_name') ?? '')] : null,
            'created_at' => $this->time->present($t->created_at),
            'last_message_at' => $this->time->present($t->last_message_at),
            /** The academy answered last and waits for you. */
            'your_turn' => $t->status === TicketStatus::Waiting,
        ];
    }

    private function full(Ticket $t): array
    {
        $details = app(TicketDetails::class)->of($t, forRequester: true);
        $names = $details['names'];
        $messages = [];
        foreach ($details['messages'] as $m) {
            $messages[] = $this->message($t, $m, $names);
        }

        return [
            'id' => $t->id,
            /** "#1024": what to quote when calling the academy. */
            'reference' => $t->reference(),
            'subject' => $t->subject,
            /** complaint | billing | technical | other */
            'kind' => ['key' => $t->kind->value, 'label' => $t->kind->label()],
            /** open (the academy's turn) | waiting (yours) | resolved | closed */
            'status' => ['key' => $t->status->value, 'label' => $t->status->label()],
            /** The learner it is about (a family's ticket). */
            'learner' => $t->student_id ? ['id' => $t->student_id, 'name' => (string) ($names[$t->student_id] ?? '')] : null,
            'created_at' => $this->time->present($t->created_at),
            'last_message_at' => $this->time->present($t->last_message_at),
            /** The academy answered last and waits for you. */
            'your_turn' => $t->status === TicketStatus::Waiting,
            /** The lesson it is about. */
            'lesson' => $details['lesson'] ? $this->lesson($details['lesson'], $t->side) : null,
            /** Whether a message can still be sent (not closed; a resolved one only within its week). */
            'can_reply' => $t->status->isActive() || $t->reopenable(),
            'can_reopen' => $t->reopenable(),
            'can_close' => $t->status !== TicketStatus::Closed,
            /** Until when a resolved ticket can be reopened. */
            'reopen_until' => $t->reopenable() ? $this->time->present($t->resolved_at?->addDays(Ticket::CLOSE_AFTER_DAYS)) : null,
            /** Oldest first. */
            'messages' => $messages,
        ];
    }

    /** @param  array<string, string>  $names */
    private function message(Ticket $t, TicketMessage $m, array $names): array
    {
        return [
            'id' => $m->id,
            /** requester (you) | staff (the academy) | system */
            'side' => $m->side,
            /** message | event (resolved, reopened, closed) */
            'kind' => $m->kind,
            /** Who wrote it or did it; null for the system, or when the academy wrote your words down for you. */
            'author' => $m->author_id && ! ($m->data['written_by_staff'] ?? false) ? ($names[$m->author_id] ?? null) : null,
            'body' => $m->body,
            /** For an event, what happened in words ("Resolved by Mona"). */
            'event' => $m->kind === 'event' ? TicketView::event($m, $names) : null,
            /** A picture or PDF; download it from its `url` with the same token. */
            'file' => $m->file_path ? [
                'name' => (string) $m->file_name,
                'mime' => (string) $m->mime,
                'size' => (int) $m->size,
                'url' => route('support.tickets.file', ['ticket' => $t->id, 'message' => $m->id], false),
            ] : null,
            'at' => $this->time->present($m->created_at),
        ];
    }

    private function lesson(LessonCard $l, string $side): array
    {
        return [
            'id' => $l->id,
            'subject' => $l->subject(),
            /** The other person in the lesson: the teacher for a family, the student for a teacher. */
            'with' => $side === 'teacher' ? $l->studentName : $l->teacherName,
            'starts_at' => $this->time->present($l->startsAt),
            /** scheduled, in_progress, completed, student_absent, teacher_absent, needs_review, cancelled */
            'status' => $l->status,
        ];
    }
}
