<?php

namespace Modules\Support\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\Support\Actions\OpenTicket;
use Modules\Support\Actions\SaveSupportTargets;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Live\TicketsChanged;
use Modules\Support\Models\SupportSettings;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Queries\SupportPeople;
use Modules\Support\Queries\TicketQueue;
use Modules\Support\SupportRules;
use Modules\Support\TicketView;

/**
 * What families and teachers wrote to the academy (docs/architecture/01,
 * section 16), by tab: the ones waiting for an answer (late first answers
 * first), the viewer's own, nobody's, the ones waiting for the family or
 * teacher, complaints to decide, and the finished ones. It follows changes
 * live. Someone may write a ticket down for a family or a teacher, and the
 * owner and admins set the answer targets.
 */
final class Tickets extends Page
{
    protected string $view = 'support::filament.queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'support';

    #[Url]
    public string $tab = 'todo';

    #[Url]
    public ?string $kind = null;

    #[Url]
    public ?string $priority = null;

    #[Url(as: 'q')]
    public string $search = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::HandleTickets->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('support::tickets.queue.nav');
    }

    /** Tickets waiting for the academy's answer. */
    public static function getNavigationBadge(): ?string
    {
        $open = Ticket::query()->where('status', TicketStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Ticket::query()->where('status', TicketStatus::Open)->whereNull('first_response_at')->where('respond_by', '<', now())->exists() ? 'danger' : 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('support::tickets.queue.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('support::tickets.queue.subtitle');
    }

    public function mount(): void
    {
        if (! in_array($this->tab, $this->tabs(), true)) {
            $this->tab = 'todo';
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'tab' && ! in_array($this->tab, $this->tabs(), true)) {
            $this->tab = 'todo';
        }
        unset($this->rows);
    }

    /** @return list<string> the tabs this person sees (complaints to decide: who decides them) */
    public function tabs(): array
    {
        return array_values(array_filter(TicketQueue::TABS, fn (string $t) => $t !== 'complaints' || Gate::allows(Ability::DecideComplaints->value)));
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return app(TicketQueue::class)->counts((string) auth()->id());
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $locale = app()->getLocale();
        $now = CarbonImmutable::now();

        return array_map(function (object $r) use ($tz, $locale, $now) {
            $ticket = (new Ticket)->newFromBuilder($r);

            return [
                'id' => $ticket->id,
                'url' => TicketPage::getUrl(['ticket' => $ticket->id]),
                'reference' => $ticket->reference(),
                'subject' => $ticket->subject,
                'kind' => $ticket->kind->value,
                'kindLabel' => $ticket->kind->label(),
                'priority' => $ticket->priority->value,
                'priorityLabel' => $ticket->priority->label(),
                'status' => $ticket->status->value,
                'statusLabel' => __('support::tickets.staff_statuses.'.$ticket->status->value),
                'complaint' => $ticket->complaint_status,
                'requester' => (string) $r->requester_name,
                'side' => $ticket->side,
                'student' => $r->student_name && $r->student_id !== $ticket->requester_id ? (string) $r->student_name : null,
                'teacher' => $r->teacher_name ? (string) $r->teacher_name : null,
                'assignee' => $r->assignee_name ? (string) $r->assignee_name : null,
                'mine' => $ticket->assignee_id === auth()->id(),
                'target' => TicketView::target($ticket, $now),
                'last' => $ticket->last_message_at->setTimezone($tz)->locale($locale)->diffForHumans(['options' => CarbonInterface::JUST_NOW]),
                'lastSide' => $ticket->last_side,
            ];
        }, app(TicketQueue::class)->list($this->tab, (string) auth()->id(), ['kind' => $this->kind, 'priority' => $this->priority, 'q' => $this->search]));
    }

    /** The academy's own channel, for live updates. */
    public function liveChannel(): string
    {
        return TicketsChanged::channel((string) app(TenantContext::class)->id());
    }

    protected function getHeaderActions(): array
    {
        return [$this->newTicketAction(), $this->targetsAction()];
    }

    public function newTicketAction(): Action
    {
        $l = 'support::tickets.queue.';
        $m = 'support::tickets.mine.';
        $people = app(SupportPeople::class);

        return Action::make('newTicket')->label(__($l.'new'))->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__($l.'new'))->modalDescription(__($l.'new_hint'))->modalWidth('2xl')->modalSubmitActionLabel(__($m.'submit'))
            ->fillForm(['for' => 'family', 'kind' => 'complaint'])
            ->schema([
                Radio::make('for')->label(__($l.'for'))->inline()->live()->required()
                    ->options(['family' => __($l.'for_family'), 'teacher' => __($l.'for_teacher')])
                    ->afterStateUpdated(fn ($set, $state) => $set('kind', $state === 'teacher' ? 'technical' : 'complaint')),
                Grid::make(2)->schema([
                    Select::make('learner')->label(__($l.'learner'))->searchable()->live()->native(false)
                        ->options(fn () => $people->withRole('student'))
                        ->visible(fn (Get $get) => $get('for') === 'family')->required(fn (Get $get) => $get('for') === 'family')
                        ->afterStateUpdated(fn ($set, $state) => $set('requester', $state ? array_key_last($people->familyOf($state)) : null)),
                    Select::make('requester')->label(__($l.'requester'))->helperText(__($l.'requester_hint'))->native(false)
                        ->options(fn (Get $get) => $get('learner') ? $people->familyOf($get('learner')) : [])
                        ->visible(fn (Get $get) => $get('for') === 'family')->required(fn (Get $get) => $get('for') === 'family'),
                    Select::make('teacher')->label(__($l.'teacher_name'))->searchable()->native(false)
                        ->options(fn () => $people->withRole('teacher'))
                        ->visible(fn (Get $get) => $get('for') === 'teacher')->required(fn (Get $get) => $get('for') === 'teacher'),
                ]),
                Grid::make(2)->schema([
                    Select::make('kind')->label(__($m.'kind'))->required()->native(false)->live()
                        ->options(fn (Get $get) => collect($get('for') === 'teacher' ? app(SupportRules::class)->kindsFor('teacher') : TicketKind::cases())
                            ->mapWithKeys(fn (TicketKind $k) => [$k->value => $k->label()])->all()),
                    Select::make('priority')->label(__('support::tickets.page.priority'))->native(false)
                        ->placeholder(fn (Get $get) => TicketKind::tryFrom((string) $get('kind'))?->priority()->label())
                        ->options(collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $p) => [$p->value => $p->label()])->all()),
                ]),
                Select::make('session')->label(__($m.'lesson'))->native(false)->placeholder(__($m.'no_lesson'))
                    ->options(fn (Get $get) => $this->lessonOptions($get('for'), $get('learner'), $get('teacher'))),
                Select::make('about')->label(__($l.'teacher_about'))->helperText(__($l.'teacher_about_hint'))->native(false)->searchable()
                    ->options(fn () => $people->withRole('teacher'))
                    ->visible(fn (Get $get) => $get('for') === 'family' && $get('kind') === 'complaint' && ! $get('session')),
                TextInput::make('subject')->label(__($m.'subject'))->required()->minLength(3)->maxLength(150),
                Textarea::make('body')->label(__($m.'body'))->required()->rows(4)->maxLength(5000),
                FileUpload::make('file')->label(__($m.'file'))->storeFiles(false)->acceptedFileTypes(TicketMessage::MIMES)->maxSize(TicketMessage::MAX_KB),
            ])
            ->action(function (array $data, Action $action) {
                $file = is_array($data['file'] ?? null) ? collect($data['file'])->first() : ($data['file'] ?? null);
                $teacher = ($data['for'] ?? null) === 'teacher';
                try {
                    $ticket = app(OpenTicket::class)->handle(
                        requesterId: (string) ($teacher ? $data['teacher'] : $data['requester']),
                        side: $teacher ? 'teacher' : 'family',
                        kind: $data['kind'] ?? null,
                        subject: $data['subject'] ?? null,
                        body: $data['body'] ?? null,
                        studentId: $teacher ? null : ($data['learner'] ?? null),
                        sessionId: $data['session'] ?? null,
                        file: $file,
                        openedBy: (string) auth()->id(),
                        priority: $data['priority'] ?? null,
                        teacherId: $data['about'] ?? null,
                    );
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('support::tickets.queue.opened', ['ticket' => $ticket->reference()]))->send();
                $this->redirect(TicketPage::getUrl(['ticket' => $ticket->id]), navigate: true);
            });
    }

    public function targetsAction(): Action
    {
        $l = 'support::tickets.targets.';
        $fields = [];
        foreach (TicketPriority::cases() as $p) {
            $fields[] = Grid::make(3)->schema([
                TextInput::make("{$p->value}_label")->hiddenLabel()->default($p->label())->disabled()->dehydrated(false),
                TextInput::make("{$p->value}.respond")->label(__($l.'respond'))->numeric()->integer()->minValue(1)->maxValue(720)->required(),
                TextInput::make("{$p->value}.resolve")->label(__($l.'resolve'))->numeric()->integer()->minValue(1)->maxValue(2160)->required(),
            ]);
        }

        return Action::make('targets')->label(__($l.'title'))->icon(Heroicon::OutlinedClock)->color('gray')
            ->visible(fn () => Gate::allows(Ability::ManageSupport->value))
            ->modalHeading(__($l.'title'))->modalDescription(__($l.'hint'))->modalWidth('2xl')
            ->fillForm(fn () => collect(SupportSettings::targets())->mapWithKeys(fn (array $t, string $p) => [$p => $t, "{$p}_label" => TicketPriority::from($p)->label()])->all())
            ->schema($fields)
            ->action(function (array $data, Action $action) use ($l) {
                abort_unless(Gate::allows(Ability::ManageSupport->value), 403);
                try {
                    app(SaveSupportTargets::class)->handle(collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $p) => [$p->value => $data[$p->value] ?? []])->all());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__($l.'saved'))->send();
            });
    }

    /** @return array<string, string> */
    private function lessonOptions(?string $for, ?string $learner, ?string $teacher): array
    {
        $rules = app(SupportRules::class);
        $tz = app(ViewerTimezone::class)->effective();
        $locale = app()->getLocale();
        $lessons = match (true) {
            $for === 'teacher' && $teacher => $rules->lessonChoices('teacher', null, $teacher),
            $for === 'family' && $learner => $rules->lessonChoices('family', $learner, $learner),
            default => [],
        };

        return collect($lessons)->mapWithKeys(fn ($l) => [$l->id => $l->subject().' · '.$l->startsAt->setTimezone($tz)->locale($locale)->translatedFormat(Clock::pattern('D j M · H:i')).' · '.($for === 'teacher' ? $l->studentName : $l->teacherName)])->all();
    }
}
