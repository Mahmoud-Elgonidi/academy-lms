<?php

namespace Modules\Announcements\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Announcements\Actions\SendAnnouncement;
use Modules\Announcements\Domain\AnnouncementRules;
use Modules\Announcements\Domain\Audience;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Queries\AudienceMembers;
use Modules\Announcements\Queries\TodaysAnnouncements;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * Announcements (Communication › Announcements; announcements.send: the
 * owner and admins; docs/architecture/02, section 20): a short title and
 * plain words for every family, the guardians, the students (or one stage),
 * one subject's families or the teachers, with push or email besides the
 * inbox. Before sending, the screen shows how many people it reaches now
 * (AudienceMembers, the same reckoning SendAnnouncement uses) and how many
 * announcements are left today, and asks once more. Below, what was sent:
 * when, by whom, for whom and to how many. A notification about one opens
 * it here in full (?announcement=).
 *
 * @property-read Schema $form
 */
final class Announcements extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'announcements::filament.announcements';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 59;

    protected static ?string $slug = 'announcements';

    /** One announcement shown in full above the list (from a notification). */
    #[Url]
    public ?string $announcement = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::SendAnnouncements->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('communication::communication.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('announcements::announcements.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('announcements::announcements.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('announcements::announcements.subtitle');
    }

    public function mount(): void
    {
        // Another academy's announcement, or none: not here.
        abort_if($this->announcement !== null && $this->focused === null, 404);
        $this->form->fill(self::blank());
    }

    /** @return array<string, mixed> */
    private static function blank(): array
    {
        return ['title' => '', 'body' => '', 'audience' => Audience::FAMILIES, 'stage' => 'all', 'subject_id' => null, 'channels' => ['push']];
    }

    public function form(Schema $schema): Schema
    {
        $l = 'announcements::announcements.form.';

        return $schema->statePath('data')->components([
            TextInput::make('title')->label(__($l.'title'))->helperText(__($l.'title_hint', ['max' => AnnouncementRules::TITLE_MAX]))
                ->required()->maxLength(AnnouncementRules::TITLE_MAX),
            Textarea::make('body')->label(__($l.'body'))->helperText(__($l.'body_hint', ['max' => AnnouncementRules::BODY_MAX]))
                ->required()->maxLength(AnnouncementRules::BODY_MAX)->rows(6)->autosize(),
            ToggleButtons::make('audience')->label(__($l.'audience'))->inline()->required()->live()
                ->options(collect(Audience::KINDS)->mapWithKeys(fn (string $k) => [$k => __($l.'audiences.'.$k)])->all())
                ->icons([Audience::FAMILIES => Heroicon::OutlinedHome, Audience::GUARDIANS => Heroicon::OutlinedUsers, Audience::STUDENTS => Heroicon::OutlinedAcademicCap,
                    Audience::SUBJECT => Heroicon::OutlinedBookOpen, Audience::TEACHERS => Heroicon::OutlinedPresentationChartBar])
                ->helperText(fn (Get $get) => in_array($get('audience'), Audience::KINDS, true) ? __($l.'audience_hints.'.$get('audience')) : null),
            ToggleButtons::make('stage')->label(__($l.'stage'))->inline()->live()->grouped()
                ->options(collect(['all', ...Audience::STAGES])->mapWithKeys(fn (string $s) => [$s => __($l.'stages.'.$s)])->all())
                ->visible(fn (Get $get) => $get('audience') === Audience::STUDENTS),
            Select::make('subject_id')->label(__($l.'subject'))->searchable()->required()->live()
                ->options(fn () => app(CatalogApi::class)->subjectNames())
                ->visible(fn (Get $get) => $get('audience') === Audience::SUBJECT),
            CheckboxList::make('channels')->label(__($l.'channels'))->helperText(__($l.'channels_hint'))->columns(2)
                ->options(collect(AnnouncementRules::CHANNELS)->mapWithKeys(fn (string $c) => [$c => __($l.'channel_options.'.$c)])->all()),
        ]);
    }

    /** Who the chosen audience is, if the choice is complete. */
    private function audience(): ?Audience
    {
        $data = $this->data ?? [];
        $kind = (string) ($data['audience'] ?? '');
        $stage = (string) ($data['stage'] ?? 'all');

        try {
            return Audience::of($kind, $kind === Audience::STUDENTS && $stage !== 'all' ? $stage : null, $kind === Audience::SUBJECT ? ($data['subject_id'] ?? null) : null);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** How many people the chosen audience reaches now: the same reckoning as sending. */
    #[Computed]
    public function reach(): int
    {
        $audience = $this->audience();

        return $audience === null ? 0 : app(AudienceMembers::class)->count($audience);
    }

    #[Computed]
    public function left(): int
    {
        return app(TodaysAnnouncements::class)->left();
    }

    #[Computed]
    public function focused(): ?Announcement
    {
        return $this->announcement !== null && Str::isUuid($this->announcement) ? Announcement::query()->find($this->announcement) : null;
    }

    public function sendAction(): Action
    {
        $l = 'announcements::announcements.';

        return Action::make('send')->label(__($l.'send.action'))->icon(Heroicon::OutlinedPaperAirplane)->size('lg')
            ->disabled(fn () => $this->reach === 0 || $this->left === 0)
            // The form is checked before asking, so the question is about something that can go.
            ->mountUsing(fn () => $this->form->validate())
            ->requiresConfirmation()->modalIcon(Heroicon::OutlinedMegaphone)
            ->modalHeading(__($l.'send.heading'))
            ->modalDescription(fn () => __($l.'send.description', ['people' => trans_choice($l.'reach.people', $this->reach, ['n' => $this->reach]), 'audience' => $this->audienceLabel()]))
            ->modalSubmitActionLabel(__($l.'send.submit'))
            ->action(fn () => $this->send());
    }

    private function send(): void
    {
        $l = 'announcements::announcements.';
        $state = $this->form->getState();
        $audience = $this->audience();
        if ($audience === null) {
            $this->addError('data.audience', __($l.'errors.audience'));

            return;
        }

        try {
            $sent = app(SendAnnouncement::class)->handle((string) auth()->id(), (string) $state['title'], (string) $state['body'], $audience, array_values($state['channels'] ?? []));
        } catch (ValidationException $e) {
            // Field errors under their fields; the rest (nobody to reach, the day's announcements used up) as a notice.
            foreach ($e->errors() as $field => $messages) {
                $this->addError(in_array($field, ['title', 'body', 'channels', 'audience', 'subject_id'], true) ? "data.{$field}" : $field, $messages[0]);
            }
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }

        Notification::make()->success()->title(__($l.'send.sent', ['people' => trans_choice($l.'reach.people', $sent->recipients_count, ['n' => $sent->recipients_count])]))->send();
        $this->form->fill(self::blank());
        unset($this->reach, $this->left);
    }

    private function audienceLabel(?Announcement $a = null): string
    {
        $l = 'announcements::announcements.form.';
        $kind = $a?->audience ?? (string) ($this->data['audience'] ?? '');
        $label = in_array($kind, Audience::KINDS, true) ? __($l.'audiences.'.$kind) : '';
        $stage = $a !== null ? $a->stage : (($this->data['stage'] ?? 'all') === 'all' ? null : $this->data['stage']);
        $subject = $a !== null ? $a->subject_id : ($this->data['subject_id'] ?? null);
        $extra = match ($kind) {
            Audience::STUDENTS => $stage !== null ? __($l.'stages.'.$stage) : null,
            Audience::SUBJECT => $subject !== null ? (app(CatalogApi::class)->subjectNames()[$subject] ?? null) : null,
            default => null,
        };

        return $extra === null ? $label : $label.' · '.$extra;
    }

    public function table(Table $table): Table
    {
        $l = 'announcements::announcements.list.';
        $tz = app(ViewerTimezone::class)->effective();
        $channels = 'announcements::announcements.form.channel_options.';

        return $table
            ->query(Announcement::query()
                ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 'announcements.academy_id')->on('u.id', '=', 'announcements.sent_by'))
                ->select('announcements.*', 'u.name as sender'))
            ->defaultSort('announcements.sent_at', 'desc')
            ->heading(__($l.'heading'))
            ->emptyStateHeading(__($l.'empty'))->emptyStateDescription(__($l.'empty_hint'))->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->columns([
                TextColumn::make('sent_at')->label(__($l.'when'))
                    ->state(fn (Announcement $a) => $a->sent_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i'))
                    ->description(fn (Announcement $a) => $a->getAttributes()['sender'] ?? null),
                TextColumn::make('title')->label(__($l.'announcement'))->weight('semibold')->wrap()->searchable('announcements.title')
                    ->description(fn (Announcement $a) => Str::limit(str_replace("\n", ' ', $a->body), 140)),
                TextColumn::make('audience')->label(__($l.'audience'))->badge()->color('gray')
                    ->state(fn (Announcement $a) => $this->audienceLabel($a)),
                TextColumn::make('channels')->label(__($l.'channels'))->badge()->color('primary')
                    ->state(fn (Announcement $a) => [__($l.'inbox'), ...array_map(fn (string $c) => __($channels.$c), $a->channels)]),
                TextColumn::make('recipients_count')->label(__($l.'reached'))->numeric()->alignEnd(),
            ])
            ->recordActions([
                Action::make('read')->label(__($l.'read'))->icon(Heroicon::OutlinedEye)->color('gray')
                    ->modalHeading(fn (Announcement $a) => $a->title)
                    ->modalContent(fn (Announcement $a) => view('announcements::filament.announcement', ['a' => $a, 'meta' => $this->meta($a, $tz)]))
                    ->modalSubmitAction(false)->modalCancelActionLabel(__($l.'close')),
            ])
            ->paginated([10, 25, 50]);
    }

    /** "Sent by Hala, Tue 6 Oct · 10:00, to 42 people." (for the view; not callable from the browser) */
    protected function meta(Announcement $a, ?string $tz = null): string
    {
        $l = 'announcements::announcements.';
        $tz ??= app(ViewerTimezone::class)->effective();
        $name = $a->getAttributes()['sender'] ?? DB::table('users')->where('id', $a->sent_by)->value('name') ?? '—';

        return __($l.'list.sent_by', [
            'name' => $name,
            'when' => $a->sent_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i'),
            'people' => trans_choice($l.'reach.people', $a->recipients_count, ['n' => $a->recipients_count]),
        ]).' '.$this->audienceLabel($a);
    }
}
