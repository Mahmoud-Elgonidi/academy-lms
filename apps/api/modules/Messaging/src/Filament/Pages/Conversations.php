<?php

namespace Modules\Messaging\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Messaging\Actions\LockConversation;
use Modules\Messaging\Actions\OverseeConversation;
use Modules\Messaging\Actions\SaveMessagingSettings;
use Modules\Messaging\ChatView;
use Modules\Messaging\Live\ConversationsChanged;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\MessagingSettings;
use Modules\Messaging\Queries\OversightBoard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Teachers' and families' conversations, for who oversees them
 * (conversations.oversee; docs/architecture/02, section 17): those with
 * something hidden or an outside app named since anyone last looked first.
 * Opening one is logged, and so is reading what was hidden from a message;
 * who manages them (conversations.manage) pauses one with a reason, lets it
 * go on, and switches messages off for the academy.
 */
final class Conversations extends Page
{
    protected string $view = 'messaging::filament.oversight';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'conversations';

    #[Url]
    public ?string $conversation = null;

    /** all | flagged | locked */
    #[Url]
    public string $tab = 'all';

    #[Url]
    public string $search = '';

    /** @var array<string, string> message id => the words as typed, once revealed (each reveal is logged) */
    public array $revealed = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::OverseeConversations->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('messaging::screens.oversight.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = app(OversightBoard::class)->needingLook();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('messaging::screens.oversight.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('messaging::screens.oversight.lead');
    }

    public function mount(): void
    {
        if ($this->conversation !== null) {
            $this->show($this->conversation);
        }
    }

    public function channel(): string
    {
        return ConversationsChanged::channel(app(TenantContext::class)->require()->id);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function board(): array
    {
        $tab = in_array($this->tab, ['all', 'flagged', 'locked'], true) ? $this->tab : 'all';
        $list = app(OversightBoard::class)->list($tab, $this->search);
        $tz = app(ViewerTimezone::class)->effective();

        return [
            'counts' => $list['counts'],
            'rows' => array_map(fn (array $r) => [
                'id' => $r['conversation']->id,
                'teacher' => $r['teacher'],
                'student' => $r['student'],
                'messages' => $r['conversation']->messages_count,
                'hidden' => $r['conversation']->hidden_count,
                'needsLook' => $r['conversation']->needsLook(),
                'locked' => $r['conversation']->isLocked(),
                'ago' => $r['conversation']->last_message_at?->setTimezone($tz)->locale(app()->getLocale())->diffForHumans(['short' => true]),
            ], $list['rows']),
        ];
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function selected(): ?array
    {
        $c = $this->conversation ? Conversation::query()->find($this->conversation) : null;
        if ($c === null) {
            return null;
        }
        $rules = app(MessagingRules::class);
        $lockedBy = $c->locked_by ? (ChatView::names([$c->locked_by])[$c->locked_by] ?? '') : null;

        return [
            'conversation' => $c,
            'title' => implode(' ↔ ', ChatView::pair($c)),
            'members' => ChatView::members($c, $rules),
            'lines' => ChatView::lines(ChatView::messages($c), '', app(ViewerTimezone::class)->effective(), staff: true),
            'lock' => $c->isLocked() ? __('messaging::screens.oversight.locked_by', [
                'name' => $lockedBy, 'when' => app(LocalTimePresenter::class)->present($c->locked_at)['label'] ?? '', 'reason' => $c->lock_reason,
            ]) : null,
        ];
    }

    public function show(string $id): void
    {
        // Each opening is logged (and a conversation that needed a look has had it).
        app(OverseeConversation::class)->open($id, (string) auth()->id());
        $this->conversation = $id;
        $this->revealed = [];
        unset($this->selected, $this->board);
    }

    public function close(): void
    {
        $this->conversation = null;
        unset($this->selected);
    }

    public function filter(string $tab): void
    {
        $this->tab = $tab;
        unset($this->board);
    }

    public function updatedSearch(): void
    {
        unset($this->board);
    }

    public function reveal(string $messageId): void
    {
        $this->revealed[$messageId] = app(OverseeConversation::class)->original($messageId, (string) auth()->id());
    }

    public function lockAction(): Action
    {
        $l = 'messaging::screens.oversight.actions.';

        return Action::make('lock')->label(__($l.'lock'))->icon(Heroicon::OutlinedPauseCircle)->color('danger')->size('sm')
            ->visible(fn () => Gate::allows(Ability::ManageConversations->value))
            ->modalDescription(__($l.'lock_hint'))->modalWidth(Width::Medium)
            ->schema([Textarea::make('reason')->label(__($l.'reason'))->required()->rows(3)->minLength(5)->maxLength(400)])
            ->action(function (array $data) use ($l) {
                app(LockConversation::class)->lock((string) $this->conversation, (string) $data['reason'], (string) auth()->id());
                unset($this->selected, $this->board);
                Notification::make()->success()->title(__($l.'locked_done'))->send();
            });
    }

    public function unlockAction(): Action
    {
        $l = 'messaging::screens.oversight.actions.';

        return Action::make('unlock')->label(__($l.'unlock'))->icon(Heroicon::OutlinedPlayCircle)->color('gray')->size('sm')
            ->visible(fn () => Gate::allows(Ability::ManageConversations->value))
            ->requiresConfirmation()->modalDescription(__($l.'unlock_hint'))
            ->action(function () use ($l) {
                app(LockConversation::class)->unlock((string) $this->conversation, (string) auth()->id());
                unset($this->selected, $this->board);
                Notification::make()->success()->title(__($l.'unlocked_done'))->send();
            });
    }

    protected function getHeaderActions(): array
    {
        $l = 'messaging::screens.oversight.actions.';

        return [
            Action::make('settings')->label(__($l.'settings'))->icon(Heroicon::OutlinedCog6Tooth)->color('gray')
                ->visible(fn () => Gate::allows(Ability::ManageConversations->value))
                ->modalWidth(Width::Medium)
                ->fillForm(fn () => ['enabled' => MessagingSettings::enabled()])
                ->schema([Toggle::make('enabled')->label(__($l.'enabled'))->helperText(__($l.'enabled_hint'))])
                ->action(function (array $data) use ($l) {
                    app(SaveMessagingSettings::class)->handle((bool) ($data['enabled'] ?? false));
                    Notification::make()->success()->title(__($l.'saved'))->send();
                }),
        ];
    }
}
