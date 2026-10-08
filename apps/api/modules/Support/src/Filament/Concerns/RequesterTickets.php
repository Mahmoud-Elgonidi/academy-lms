<?php

namespace Modules\Support\Filament\Concerns;

use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\Support\Actions\CloseTicket;
use Modules\Support\Actions\OpenTicket;
use Modules\Support\Actions\ReopenTicket;
use Modules\Support\Actions\ReplyToTicket;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Queries\MyTickets;
use Modules\Support\Queries\TicketDetails;
use Modules\Support\SupportRules;
use Modules\Support\TicketView;

/**
 * A family's or a teacher's own tickets in their panel: the list, one ticket
 * open next to it (the messages both ways and what was done), answering
 * with words or a picture, reopening within the week, closing it, and
 * writing a new one. It follows the person's own live channel.
 */
trait RequesterTickets
{
    /** The ticket open on screen. */
    #[Url]
    public ?string $ticket = null;

    public string $body = '';

    /** @var mixed a picture or PDF chosen for the next message */
    public $attachment = null;

    /** family | teacher */
    abstract protected function side(): string;

    /** The learner a family's tickets are about now; null for a teacher. */
    abstract protected function learnerId(): ?string;

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function mine(): array
    {
        $tz = app(ViewerTimezone::class)->effective();

        return array_map(fn (Ticket $t) => [
            'id' => $t->id,
            'reference' => $t->reference(),
            'subject' => $t->subject,
            'kind' => $t->kind->label(),
            'status' => $t->status->value,
            'statusLabel' => $t->status->label(),
            'yourTurn' => $t->status->value === 'waiting',
            'last' => $t->last_message_at->setTimezone($tz)->locale(app()->getLocale())->diffForHumans(['options' => CarbonInterface::JUST_NOW]),
        ], app(MyTickets::class)->of((string) auth()->id(), $this->learnerId()));
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function opened(): ?array
    {
        $t = $this->ticket ? Ticket::query()->where('requester_id', auth()->id())->find($this->ticket) : null;
        if ($t === null) {
            return null;
        }
        $details = app(TicketDetails::class)->of($t, forRequester: true);
        $tz = app(ViewerTimezone::class)->effective();
        $lesson = $details['lesson'];

        return [
            'id' => $t->id,
            'reference' => $t->reference(),
            'subject' => $t->subject,
            'kind' => $t->kind->label(),
            'status' => $t->status->value,
            'statusLabel' => $t->status->label(),
            'lesson' => $lesson ? $lesson->subject().' · '.$lesson->startsAt->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i') : null,
            'lines' => TicketView::lines($details['messages'], $details['names'], 'requester', $tz),
            'canReply' => $t->status->isActive() || $t->reopenable(),
            'canReopen' => $t->reopenable(),
            'reopenUntil' => $t->reopenable() ? $t->resolved_at?->addDays(Ticket::CLOSE_AFTER_DAYS)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F') : null,
            'canClose' => $t->status->value !== 'closed',
        ];
    }

    public function channel(): string
    {
        return PersonalChannel::name((string) auth()->id());
    }

    public function show(?string $ticket): void
    {
        $this->ticket = $ticket;
        $this->reset('body', 'attachment');
        unset($this->opened);
    }

    public function sendReply(): void
    {
        $this->resetErrorBag();
        $t = $this->ownTicket();
        try {
            app(ReplyToTicket::class)->handle($t, (string) auth()->id(), $this->body, $this->attachment);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->reset('body', 'attachment');
        unset($this->opened, $this->mine);
    }

    public function reopen(): void
    {
        $this->changeOwn(fn (Ticket $t) => app(ReopenTicket::class)->handle($t, (string) auth()->id()), 'support::tickets.mine.reopened');
    }

    public function closeTicket(): void
    {
        $this->changeOwn(fn (Ticket $t) => app(CloseTicket::class)->handle($t, (string) auth()->id()), 'support::tickets.mine.closed');
    }

    public function forgetAttachment(): void
    {
        $this->reset('attachment');
    }

    public function newTicketAction(): Action
    {
        $m = 'support::tickets.mine.';
        $side = $this->side();
        $kinds = app(SupportRules::class)->kindsFor($side, (string) auth()->id());

        return Action::make('newTicket')->label(__($m.'new'))->icon(Heroicon::OutlinedPencilSquare)
            ->visible($kinds !== [])
            ->modalHeading(__($m.'new_heading'))->modalWidth('xl')->modalSubmitActionLabel(__($m.'submit'))
            ->schema([
                Radio::make('kind')->label(__($m.'kind'))->required()
                    ->options(collect($kinds)->mapWithKeys(fn (TicketKind $k) => [$k->value => $k->label()])->all())
                    ->descriptions(collect($kinds)->mapWithKeys(fn (TicketKind $k) => [$k->value => trans()->has('support::tickets.'.$side.'_kind_hints.'.$k->value)
                        ? __('support::tickets.'.$side.'_kind_hints.'.$k->value) : __('support::tickets.kind_hints.'.$k->value)])->all()),
                Select::make('session')->label(__($m.'lesson'))->native(false)->placeholder(__($m.'no_lesson'))
                    ->options(fn () => $this->lessonOptions()),
                TextInput::make('subject')->label(__($m.'subject'))->placeholder(__($m.'subject_placeholder'))->required()->minLength(3)->maxLength(150),
                Textarea::make('body')->label(__($m.'body'))->required()->rows(4)->maxLength(5000),
                FileUpload::make('file')->label(__($m.'file'))->storeFiles(false)->acceptedFileTypes(TicketMessage::MIMES)->maxSize(TicketMessage::MAX_KB),
            ])
            ->action(function (array $data, Action $action) use ($side, $m) {
                $file = is_array($data['file'] ?? null) ? collect($data['file'])->first() : ($data['file'] ?? null);
                try {
                    $ticket = app(OpenTicket::class)->handle((string) auth()->id(), $side, $data['kind'] ?? null, $data['subject'] ?? null, $data['body'] ?? null,
                        $this->learnerId(), $data['session'] ?? null, $file);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__($m.'sent', ['ticket' => $ticket->reference()]))->send();
                $this->show($ticket->id);
                unset($this->mine);
            });
    }

    /** @return array<string, string> */
    private function lessonOptions(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $side = $this->side();

        return collect(app(SupportRules::class)->lessonChoices($side, $this->learnerId(), (string) auth()->id()))
            ->mapWithKeys(fn ($l) => [$l->id => $l->subject().' · '.$l->startsAt->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i').' · '.($side === 'teacher' ? $l->studentName : $l->teacherName)])
            ->all();
    }

    private function ownTicket(): Ticket
    {
        $t = $this->ticket ? Ticket::query()->where('requester_id', auth()->id())->find($this->ticket) : null;
        abort_if($t === null, 404);

        return $t;
    }

    private function changeOwn(Closure $change, string $done): void
    {
        try {
            $change($this->ownTicket());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        unset($this->opened, $this->mine);
        Notification::make()->success()->title(__($done))->send();
    }
}
