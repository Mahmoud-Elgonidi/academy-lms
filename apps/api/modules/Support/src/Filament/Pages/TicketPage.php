<?php

namespace Modules\Support\Filament\Pages;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\Support\Actions\AssignTicket;
use Modules\Support\Actions\ClassifyTicket;
use Modules\Support\Actions\DecideComplaint;
use Modules\Support\Actions\ReopenTicket;
use Modules\Support\Actions\ReplyToTicket;
use Modules\Support\Actions\ResolveTicket;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Live\TicketsChanged;
use Modules\Support\Models\Ticket;
use Modules\Support\Queries\SupportPeople;
use Modules\Support\Queries\TicketDetails;
use Modules\Support\TicketView;

/**
 * One ticket for the academy: the conversation with the family or teacher
 * (with the academy's internal notes and what changed), the answer box
 * (answer, answer and resolve, or a note only the academy sees), who has it,
 * what it is and how urgent, its targets, and for a complaint the decision:
 * confirmed (it counts in the teacher's numbers) or dismissed, with the
 * academy's reason. It follows changes live.
 */
final class TicketPage extends Page
{
    use WithFileUploads;

    protected string $view = 'support::filament.ticket';

    protected static ?string $slug = 'support/{ticket}';

    protected static bool $shouldRegisterNavigation = false;

    public string $ticketId;

    /** reply | note */
    public string $mode = 'reply';

    public string $body = '';

    /** @var mixed a picture or PDF chosen for the next message */
    public $attachment = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::HandleTickets->value);
    }

    public function mount(string $ticket): void
    {
        $this->ticketId = Ticket::query()->findOrFail($ticket)->id;
    }

    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::query()->findOrFail($this->ticketId);
    }

    public function getTitle(): string|Htmlable
    {
        return __('support::tickets.page.title', ['ticket' => $this->ticket->reference()]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->ticket->subject;
    }

    public function getBreadcrumbs(): array
    {
        return [Tickets::getUrl() => __('support::tickets.queue.nav'), $this->ticket->reference()];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function screen(): array
    {
        $t = $this->ticket;
        $details = app(TicketDetails::class)->of($t, forRequester: false);
        $names = $details['names'];
        $tz = app(ViewerTimezone::class)->effective();
        $locale = app()->getLocale();
        $at = fn (?CarbonImmutable $i) => $i?->setTimezone($tz)->locale($locale)->translatedFormat('j M Y · H:i');
        $lesson = $details['lesson'];

        return [
            'channel' => TicketsChanged::channel((string) app(TenantContext::class)->id()),
            'status' => $t->status->value,
            'statusLabel' => __('support::tickets.staff_statuses.'.$t->status->value),
            'active' => $t->status->isActive(),
            'kind' => $t->kind->value,
            'kindLabel' => $t->kind->label(),
            'priority' => $t->priority->value,
            'priorityLabel' => $t->priority->label(),
            'requester' => $names[$t->requester_id] ?? '',
            'side' => $t->side,
            'student' => $t->student_id && $t->student_id !== $t->requester_id ? ($names[$t->student_id] ?? null) : null,
            'writtenBy' => $t->opened_by !== $t->requester_id ? ($names[$t->opened_by] ?? null) : null,
            'assignee' => $t->assignee_id ? ($names[$t->assignee_id] ?? '') : null,
            'mine' => $t->assignee_id === auth()->id(),
            'lesson' => $lesson ? [
                'subject' => $lesson->subject(),
                'when' => $lesson->startsAt->setTimezone($tz)->locale($locale)->translatedFormat('l j F · H:i'),
                'teacher' => $lesson->teacherName,
                'student' => $lesson->studentName,
                'status' => __('support::tickets.lesson_statuses.'.$lesson->status),
            ] : null,
            'opened' => $at($t->created_at),
            'firstAnswer' => $at($t->first_response_at),
            'target' => TicketView::target($t),
            'complaint' => $t->isComplaint() ? [
                'status' => $t->complaint_status,
                'label' => __('support::tickets.complaint.'.$t->complaint_status),
                'teacher' => $t->teacher_id ? ($names[$t->teacher_id] ?? '') : null,
                'decided' => $t->complaint_decided_at ? __('support::tickets.page.decided', [
                    'decision' => __('support::tickets.complaint.'.$t->complaint_status),
                    'name' => $names[$t->complaint_decided_by] ?? '',
                    'when' => $at($t->complaint_decided_at),
                ]) : null,
                'note' => $t->complaint_note,
                'canDecide' => Gate::allows(Ability::DecideComplaints->value),
            ] : null,
            'lines' => TicketView::lines($details['messages'], $names, 'staff', $tz),
            'canResolve' => $t->status->isActive() && $t->complaint_status !== 'pending',
        ];
    }

    public function send(bool $resolve = false): void
    {
        $this->resetErrorBag();
        $note = $this->mode === 'note';
        $l = 'support::tickets.page.';
        try {
            if ($resolve && ! $note) {
                app(ResolveTicket::class)->handle($this->ticket, (string) auth()->id(), $this->body, $this->attachment);
            } else {
                app(ReplyToTicket::class)->handle($this->ticket, (string) auth()->id(), $this->body, $this->attachment, $note);
            }
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->reset('body', 'attachment');
        $this->refreshTicket();
        Notification::make()->success()->title(match (true) {
            $note => __($l.'noted'),
            $resolve => __($l.'resolved', ['name' => $this->screen['requester'], 'days' => Ticket::CLOSE_AFTER_DAYS]),
            default => __($l.'sent'),
        })->send();
    }

    public function take(): void
    {
        app(AssignTicket::class)->handle($this->ticket, (string) auth()->id(), (string) auth()->id());
        $this->refreshTicket();
        Notification::make()->success()->title(__('support::tickets.page.saved'))->send();
    }

    public function forgetAttachment(): void
    {
        $this->reset('attachment');
    }

    protected function getHeaderActions(): array
    {
        return [$this->markResolvedAction(), $this->reopenAction()];
    }

    public function markResolvedAction(): Action
    {
        return Action::make('markResolved')->label(__('support::tickets.page.resolve_only'))->icon(Heroicon::OutlinedCheckCircle)->color('success')
            ->visible(fn () => $this->screen['canResolve'])
            ->requiresConfirmation()
            ->action(function () {
                $this->attempt(fn () => app(ResolveTicket::class)->handle($this->ticket, (string) auth()->id()));
                Notification::make()->success()->title(__('support::tickets.page.resolved', ['name' => $this->screen['requester'], 'days' => Ticket::CLOSE_AFTER_DAYS]))->send();
            });
    }

    public function reopenAction(): Action
    {
        return Action::make('reopen')->label(__('support::tickets.page.reopen'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
            ->visible(fn () => $this->ticket->status === TicketStatus::Resolved)
            ->action(function () {
                $this->attempt(fn () => app(ReopenTicket::class)->handle($this->ticket, (string) auth()->id()));
                Notification::make()->success()->title(__('support::tickets.page.reopened'))->send();
            });
    }

    public function classifyAction(): Action
    {
        $l = 'support::tickets.page.';

        return Action::make('classify')->label(__($l.'change'))->link()->size('sm')
            ->modalHeading(__($l.'classify'))->modalWidth('md')
            ->fillForm(fn () => ['kind' => $this->ticket->kind->value, 'priority' => $this->ticket->priority->value])
            ->schema([
                Grid::make(2)->schema([
                    Select::make('kind')->label(__($l.'kind'))->required()->native(false)
                        ->options(collect($this->ticket->side === 'teacher' ? [TicketKind::Technical, TicketKind::Billing, TicketKind::Other] : TicketKind::cases())
                            ->mapWithKeys(fn (TicketKind $k) => [$k->value => $k->label()])->all()),
                    Select::make('priority')->label(__($l.'priority'))->required()->native(false)
                        ->options(collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $p) => [$p->value => $p->label()])->all()),
                ]),
            ])
            ->action(function (array $data) use ($l) {
                $this->attempt(fn () => app(ClassifyTicket::class)->handle($this->ticket, TicketKind::from($data['kind']), TicketPriority::from($data['priority']), (string) auth()->id()));
                Notification::make()->success()->title(__($l.'saved'))->send();
            });
    }

    public function assignAction(): Action
    {
        $l = 'support::tickets.page.';

        return Action::make('assign')->label(__($l.'assign'))->link()->size('sm')
            ->modalHeading(__($l.'assign'))->modalWidth('md')
            ->fillForm(fn () => ['assignee' => $this->ticket->assignee_id])
            ->schema([
                Select::make('assignee')->hiddenLabel()->native(false)->searchable()->placeholder(__('support::tickets.queue.nobody'))
                    ->options(fn () => app(SupportPeople::class)->staff()),
            ])
            ->action(function (array $data) use ($l) {
                $this->attempt(fn () => app(AssignTicket::class)->handle($this->ticket, $data['assignee'] ?: null, (string) auth()->id()));
                Notification::make()->success()->title(__($l.'saved'))->send();
            });
    }

    public function decideAction(): Action
    {
        $l = 'support::tickets.page.';

        return Action::make('decide')
            ->label(fn (array $arguments) => __($l.(($arguments['validated'] ?? false) ? 'confirm' : 'dismiss')))
            ->color(fn (array $arguments) => ($arguments['validated'] ?? false) ? 'danger' : 'gray')->size('sm')
            ->visible(fn () => Gate::allows(Ability::DecideComplaints->value) && $this->ticket->isComplaint())
            ->modalHeading(fn (array $arguments) => __($l.(($arguments['validated'] ?? false) ? 'confirm_heading' : 'dismiss_heading')))
            ->modalDescription(fn (array $arguments) => __($l.(($arguments['validated'] ?? false) ? 'confirm_hint' : 'dismiss_hint')))
            ->modalWidth('lg')
            ->schema(fn (array $arguments) => [
                Select::make('teacher')->label(__('support::tickets.queue.teacher_about'))->native(false)->searchable()->required()
                    ->options(fn () => app(SupportPeople::class)->withRole('teacher'))
                    ->visible(fn () => ($arguments['validated'] ?? false) && $this->ticket->teacher_id === null),
                Textarea::make('note')->label(__($l.'reason'))->required()->minLength(DecideComplaint::NOTE_MIN)->maxLength(500)->rows(3),
            ])
            ->action(function (array $data, array $arguments) use ($l) {
                $this->attempt(fn () => app(DecideComplaint::class)->handle($this->ticket, (bool) ($arguments['validated'] ?? false), $data['note'] ?? null, (string) auth()->id(), $data['teacher'] ?? null));
                Notification::make()->success()->title(__($l.'decision_saved'))->send();
            });
    }

    /** Runs a change; a refusal says why and stops there (a dialog stays open). */
    private function attempt(Closure $change): void
    {
        try {
            $change();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            throw new Halt;
        }
        $this->refreshTicket();
    }

    private function refreshTicket(): void
    {
        unset($this->ticket, $this->screen);
    }
}
