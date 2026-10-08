<?php

namespace Modules\Support\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Support\Contracts\Events\ComplaintValidated;
use Modules\Support\Contracts\Events\TicketAssigned;
use Modules\Support\Contracts\Events\TicketOpened;
use Modules\Support\Contracts\Events\TicketOverdue;
use Modules\Support\Contracts\Events\TicketReplied;
use Modules\Support\Contracts\Events\TicketResolved;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;

/**
 * Support's messages (wording in lang/{ar,en}/notifications.php). A new
 * ticket goes to whoever its kind concerns (a complaint to who oversees the
 * teachers, a bill to who records payments, the rest to everyone who answers
 * tickets); afterwards, to whoever has it. The requester hears the academy's
 * answers and that it is resolved; a teacher hears a complaint confirmed,
 * without the family's name. Nobody hears of their own doing.
 */
final class SendSupportNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly SupportRules $rules,
        private readonly AttendanceApi $lessons,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'support::notifications.audience.';
        $ticket = ['ticket' => '#1024', 'subject' => 'المدرس اتأخر ربع ساعة'];
        foreach ([
            ['ticket_opened', 'staff', $a.'concerned', ['push', 'email'], $ticket + ['requester' => 'خالد', 'kind' => 'شكوى', 'excerpt' => 'الحصة بدأت متأخر…']],
            ['answered', 'lessons', $a.'requester', ['push', 'whatsapp', 'email'], $ticket + ['excerpt' => 'آسفين جدًا، اتكلمنا مع المدرس…']],
            ['requester_replied', 'staff', $a.'assignee', ['push'], $ticket + ['requester' => 'خالد', 'excerpt' => 'تمام، شكرًا…']],
            ['assigned', 'staff', $a.'assignee', ['push'], $ticket + ['requester' => 'خالد']],
            ['resolved', 'lessons', $a.'requester', ['push', 'whatsapp', 'email'], $ticket + ['days' => (string) Ticket::CLOSE_AFTER_DAYS]],
            ['overdue', 'staff', $a.'assignee', ['push', 'email'], $ticket + ['requester' => 'خالد']],
            ['complaint_validated', 'staff', $a.'teacher', ['push', 'email'], ['subject' => 'القرآن', 'at' => 'الأحد 5 أكتوبر · 18:00']],
            ['complaint_validated_general', 'staff', $a.'teacher', ['push', 'email'], []],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "support.{$name}", category: $category,
                title: "support::notifications.{$name}.title", body: "support::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function opened(TicketOpened $event): void
    {
        $ticket = Ticket::query()->find($event->ticketId);
        if ($ticket === null) {
            return;
        }
        $first = TicketMessage::query()->where('ticket_id', $ticket->id)->where('kind', 'message')->orderBy('created_at')->first();
        $recipients = array_values(array_diff($this->concerned($ticket), [$ticket->opened_by]));
        $this->notifications->send(new Message('support.ticket_opened', $recipients, $this->about($ticket) + [
            'requester' => $this->name($ticket->requester_id),
            'kind' => new Translatable('support::tickets.kinds.'.$ticket->kind->value),
            'excerpt' => $this->excerpt($first?->body),
        ], $this->staffScreen($ticket), "ticket-opened:{$ticket->id}"));
    }

    public function replied(TicketReplied $event): void
    {
        $ticket = Ticket::query()->find($event->ticketId);
        $message = TicketMessage::query()->find($event->messageId);
        if ($ticket === null || $message === null) {
            return;
        }
        if ($event->side === 'staff') {
            $this->notifications->send(new Message('support.answered', [$ticket->requester_id], $this->about($ticket) + ['excerpt' => $this->excerpt($message->body)],
                ['screen' => 'ticket', 'id' => $ticket->id], "ticket-answered:{$message->id}"));

            return;
        }
        $recipients = $ticket->assignee_id ? [$ticket->assignee_id] : $this->concerned($ticket);
        $this->notifications->send(new Message('support.requester_replied', $recipients, $this->about($ticket) + [
            'requester' => $this->name($ticket->requester_id),
            'excerpt' => $this->excerpt($message->body),
        ], $this->staffScreen($ticket), "ticket-replied:{$message->id}"));
    }

    public function assigned(TicketAssigned $event): void
    {
        $ticket = Ticket::query()->find($event->ticketId);
        if ($ticket === null || $ticket->assignee_id !== $event->assigneeId) {
            return;
        }
        $this->notifications->send(new Message('support.assigned', [$event->assigneeId], $this->about($ticket) + ['requester' => $this->name($ticket->requester_id)],
            $this->staffScreen($ticket), 'ticket-assigned:'.$ticket->id.':'.$event->assigneeId.':'.Str::ulid()));
    }

    public function resolved(TicketResolved $event): void
    {
        $ticket = Ticket::query()->find($event->ticketId);
        if ($ticket === null) {
            return;
        }
        $this->notifications->send(new Message('support.resolved', [$ticket->requester_id], $this->about($ticket) + ['days' => Ticket::CLOSE_AFTER_DAYS],
            ['screen' => 'ticket', 'id' => $ticket->id], 'ticket-resolved:'.$ticket->id.':'.$ticket->resolved_at?->getTimestamp()));
    }

    public function overdue(TicketOverdue $event): void
    {
        $ticket = Ticket::query()->find($event->ticketId);
        if ($ticket === null || $ticket->first_response_at !== null) {
            return;
        }
        $recipients = $ticket->assignee_id ? [$ticket->assignee_id] : $this->concerned($ticket);
        $this->notifications->send(new Message('support.overdue', $recipients, $this->about($ticket) + ['requester' => $this->name($ticket->requester_id)],
            $this->staffScreen($ticket), "ticket-overdue:{$ticket->id}"));
    }

    public function complaintValidated(ComplaintValidated $event): void
    {
        $lesson = $event->sessionId ? $this->lessons->lesson($event->sessionId) : null;
        if ($lesson === null) {
            $this->notifications->send(new Message('support.complaint_validated_general', [$event->teacherId], [],
                ['screen' => 'my_performance', 'id' => $event->teacherId], "complaint-validated:{$event->ticketId}"));

            return;
        }
        $this->notifications->send(new Message('support.complaint_validated', [$event->teacherId], [
            'subject' => new Localized($lesson->subjectAr, $lesson->subjectEn),
            'at' => $lesson->startsAt,
        ], ['screen' => 'my_performance', 'id' => $event->teacherId], "complaint-validated:{$event->ticketId}"));
    }

    /** @return list<string> who a new ticket of its kind concerns */
    private function concerned(Ticket $ticket): array
    {
        return $this->rules->holders(match ($ticket->kind) {
            TicketKind::Complaint => Ability::DecideComplaints,
            TicketKind::Billing => Ability::RecordPayments,
            default => Ability::HandleTickets,
        });
    }

    /** @return array{ticket: string, subject: string} */
    private function about(Ticket $ticket): array
    {
        return ['ticket' => $ticket->reference(), 'subject' => $ticket->subject];
    }

    /** @return array<string, string> */
    private function staffScreen(Ticket $ticket): array
    {
        return ['screen' => 'support_ticket', 'id' => $ticket->id];
    }

    private function name(string $userId): string
    {
        return (string) DB::table('users')->where('id', $userId)->value('name');
    }

    private function excerpt(?string $body): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $body) ?? ''), 140);
    }
}
