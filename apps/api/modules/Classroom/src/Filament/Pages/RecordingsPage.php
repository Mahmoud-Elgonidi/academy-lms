<?php

namespace Modules\Classroom\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Modules\Classroom\Actions\Recordings\DeleteRecording;
use Modules\Classroom\Actions\Recordings\HoldRecording;
use Modules\Classroom\Actions\Recordings\RecordRecordingConsent;
use Modules\Classroom\Actions\Recordings\ReleaseRecording;
use Modules\Classroom\Actions\Recordings\WatchRecording;
use Modules\Classroom\Actions\Recordings\WithdrawRecordingConsent;
use Modules\Classroom\Contracts\RecordingHolds;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\RecordedPerson;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\RecordingAccessLog;
use Modules\Classroom\Queries\RecordingBoard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * Lesson recordings for the office (docs/architecture/01, section 9): every
 * recording with its lesson, how long it is kept and how often it was
 * opened; watching one logs it. The owner and admins hold one past its days
 * or remove it early, saying why. A second view lists who lessons record and
 * whether each agreed; a consent held on paper is recorded here, and one is
 * withdrawn at someone's request.
 */
final class RecordingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    public const TABS = ['recordings', 'missing', 'agreed'];

    protected string $view = 'classroom::filament.recordings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?int $navigationSort = 43;

    protected static ?string $slug = 'recordings';

    #[Url]
    public string $tab = 'recordings';

    /** @var array{id: string, url: string, media: string, title: string}|null the recording playing now */
    public ?array $playing = null;

    /** @var array<string, string> lessons held by an open complaint, as asked for the rows shown */
    private array $complaints = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewRecordings->value) || Gate::allows(Ability::ManageConsents->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('learning::reports.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('classroom::recordings.office.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('classroom::recordings.office.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $settings = ClassroomSettings::current();

        return $settings->records()
            ? __('classroom::recordings.office.lead', ['mode' => __('classroom::recordings.settings.modes.'.$settings->recording), 'days' => $settings->recording_days])
            : __('classroom::recordings.office.lead_off');
    }

    public function mount(): void
    {
        $this->tab = $this->allowedTab($this->tab);
    }

    /** @return list<string> */
    public function tabs(): array
    {
        return array_values(array_filter(self::TABS, fn (string $t) => $t === 'recordings'
            ? Gate::allows(Ability::ViewRecordings->value)
            : Gate::allows(Ability::ManageConsents->value)));
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return app(RecordingBoard::class)->counts();
    }

    public function updatedTab(): void
    {
        $this->tab = $this->allowedTab($this->tab);
        $this->playing = null;
        $this->resetTable();
    }

    public function settingsUrl(): ?string
    {
        return ClassroomSettingsPage::canAccess() ? ClassroomSettingsPage::getUrl() : null;
    }

    public function table(Table $table): Table
    {
        return $this->tab === 'recordings' ? $this->recordingsTable($table) : $this->peopleTable($table);
    }

    private function recordingsTable(Table $table): Table
    {
        $l = 'classroom::recordings.';
        $tz = app(ViewerTimezone::class)->effective();
        $manage = fn () => Gate::allows(Ability::ManageRecordings->value);

        return $table
            ->query(app(RecordingBoard::class)->recordings())
            ->defaultSort('lesson_at', 'desc')
            ->columns([
                TextColumn::make('lesson_at')->label(__($l.'office.columns.lesson'))->sortable()->weight('semibold')
                    ->state(fn (Recording $r) => CarbonImmutable::parse($r->getAttribute('lesson_at'))->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i')),
                TextColumn::make('student_name')->label(__($l.'office.columns.student'))
                    ->searchable(query: fn (Builder $q, string $search) => $q->where(fn ($q) => $q->where('t.name', 'ilike', "%{$search}%")
                        ->orWhereRaw("exists (select 1 from session_participants p join users u on u.academy_id = p.academy_id and u.id = p.user_id where p.academy_id = s.academy_id and p.session_id = s.id and p.role = 'student' and u.name ilike ?)", ["%{$search}%"]))),
                TextColumn::make('teacher_name')->label(__($l.'office.columns.teacher')),
                TextColumn::make('media')->label(__($l.'office.columns.media'))->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => __($l.'media.'.$state)),
                TextColumn::make('duration_sec')->label(__($l.'office.columns.length'))
                    ->state(fn (Recording $r) => $r->duration_sec !== null ? self::length($r->duration_sec) : '—'),
                TextColumn::make('status')->label(__($l.'office.columns.status'))->badge()
                    ->formatStateUsing(fn (string $state) => __($l.'statuses.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'ready' => 'success', 'failed' => 'danger', 'deleted' => 'gray', default => 'warning',
                    }),
                TextColumn::make('expires_at')->label(__($l.'office.columns.kept'))
                    ->state(fn (Recording $r) => $this->kept($r, $tz))
                    ->description(fn (Recording $r) => $r->held_at !== null ? Str::limit((string) $r->hold_reason, 40) : null)
                    ->tooltip(fn (Recording $r) => $r->held_at !== null ? $r->hold_reason : null)
                    ->color(fn (Recording $r) => $r->held_at !== null || isset($this->complaintsFor($r)[$r->session_id]) ? 'warning' : null),
                TextColumn::make('views')->label(__($l.'office.columns.views'))
                    ->state(fn (Recording $r) => (int) $r->getAttribute('views') > 0 ? trans_choice($l.'office.views', (int) $r->getAttribute('views')) : '—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__($l.'office.columns.status'))
                    ->options(collect(['ready', 'recording', 'failed', 'deleted'])->mapWithKeys(fn ($s) => [$s => __($l.'statuses.'.$s)]))
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        null, '' => $q,
                        'recording' => $q->whereIn('recordings.status', Recording::RUNNING),
                        default => $q->where('recordings.status', $data['value']),
                    }),
                SelectFilter::make('teacher')->label(__($l.'office.columns.teacher'))
                    ->options(fn () => DB::table('users')->whereIn('id', DB::table('sessions')->whereIn('id', Recording::query()->select('session_id'))->select('teacher_id'))->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null) ? $q->where('s.teacher_id', $data['value']) : $q),
            ])
            ->recordActions([
                Action::make('watch')->label(__($l.'office.watch'))->icon(Heroicon::OutlinedPlay)->button()
                    ->visible(fn (Recording $r) => $r->isReady() && Gate::allows(Ability::ViewRecordings->value))
                    ->action(fn (Recording $r) => $this->play($r)),
                ActionGroup::make([
                    Action::make('log')->label(__($l.'office.log'))->icon(Heroicon::OutlinedEye)->color('gray')
                        ->visible(fn (Recording $r) => (int) $r->getAttribute('views') > 0)
                        ->modalHeading(__($l.'office.log_title'))->modalSubmitAction(false)->modalCancelActionLabel(__($l.'office.close'))
                        ->modalContent(fn (Recording $r) => view('classroom::filament.recording-log', ['rows' => $this->openings($r, $tz)])),
                    Action::make('hold')->label(__($l.'office.hold'))->icon(Heroicon::OutlinedLockClosed)->color('warning')
                        ->visible(fn (Recording $r) => $manage() && $r->held_at === null && in_array($r->status, ['ready', 'recording', 'ending', 'starting'], true))
                        ->modalHeading(__($l.'office.hold_title'))->modalDescription(__($l.'office.hold_hint'))
                        ->schema([Textarea::make('reason')->label(__($l.'attributes.reason'))->required()->minLength(HoldRecording::REASON_MIN)->maxLength(300)->rows(3)])
                        ->action(function (Recording $r, array $data) {
                            app(HoldRecording::class)->handle(Recording::query()->findOrFail($r->id), (string) auth()->id(), $data['reason']);
                            Notification::make()->success()->title(__('classroom::recordings.office.held'))->send();
                        }),
                    Action::make('release')->label(__($l.'office.release'))->icon(Heroicon::OutlinedLockOpen)->color('gray')
                        ->visible(fn (Recording $r) => $manage() && $r->held_at !== null)
                        ->requiresConfirmation()->modalHeading(__($l.'office.release_title'))->modalDescription(__($l.'office.release_hint'))
                        ->action(function (Recording $r) {
                            app(ReleaseRecording::class)->handle(Recording::query()->findOrFail($r->id));
                            Notification::make()->success()->title(__('classroom::recordings.office.released'))->send();
                        }),
                    Action::make('remove')->label(__($l.'office.remove'))->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->visible(fn (Recording $r) => $manage() && in_array($r->status, ['ready', 'failed'], true) && $r->held_at === null)
                        ->modalHeading(__($l.'office.remove_title'))->modalDescription(__($l.'office.remove_hint'))->modalSubmitActionLabel(__($l.'office.remove'))
                        ->schema([Textarea::make('reason')->label(__($l.'attributes.reason'))->required()->minLength(HoldRecording::REASON_MIN)->maxLength(300)->rows(3)])
                        ->action(function (Recording $r, array $data, Action $action) {
                            try {
                                app(DeleteRecording::class)->handle(Recording::query()->findOrFail($r->id), (string) auth()->id(), $data['reason']);
                            } catch (ValidationException $e) {
                                Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                                $action->halt();
                            }
                            if ($this->playing !== null && $this->playing['id'] === $r->id) {
                                $this->playing = null;
                            }
                            Notification::make()->success()->title(__('classroom::recordings.office.removed'))->send();
                        }),
                ])->label(__($l.'office.more'))->icon(Heroicon::OutlinedEllipsisVertical)->color('gray'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedFilm)
            ->emptyStateHeading(__($l.'office.empty'))
            ->emptyStateDescription(ClassroomSettings::current()->records() ? __($l.'office.empty_hint') : __($l.'office.lead_off'));
    }

    private function peopleTable(Table $table): Table
    {
        $l = 'classroom::recordings.';
        $tz = app(ViewerTimezone::class)->effective();
        $tab = $this->tab;
        $day = fn ($at) => $at ? CarbonImmutable::parse($at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y') : null;

        return $table
            ->query(app(RecordingBoard::class)->people($tab))
            ->defaultSort(fn ($query) => $query->orderBy('role', 'desc')->orderBy('users.name'))
            ->columns([
                TextColumn::make('name')->label(__($l.'office.columns.person'))->weight('semibold')->searchable('users.name'),
                TextColumn::make('role')->label(__($l.'office.columns.role'))->badge()->color(fn (string $state) => $state === 'teacher' ? 'info' : 'gray')
                    ->formatStateUsing(fn (string $state) => __($l.'roles.'.$state)),
                TextColumn::make('guardians')->label(__($l.'office.columns.guardians'))->wrap()->placeholder('—'),
                TextColumn::make('via')->label(__($l.'office.columns.consent'))->wrap()
                    ->state(fn (RecordedPerson $p) => $tab === 'agreed'
                        ? __($l.'via.'.$p->getAttribute('via')).($p->getAttribute('given_by_name') && $p->getAttribute('via') === 'guardian' ? ' · '.$p->getAttribute('given_by_name') : '').' · '.$day($p->getAttribute('granted_at'))
                        : ($p->getAttribute('withdrawn_at') ? __($l.'office.withdrawn_on', ['date' => $day($p->getAttribute('withdrawn_at'))]) : __($l.'consent.none')))
                    ->description(fn (RecordedPerson $p) => $tab === 'agreed' && $p->getAttribute('via') === 'staff' ? $p->getAttribute('note') : null)
                    ->color(fn (RecordedPerson $p) => $tab === 'agreed' ? 'success' : ($p->getAttribute('withdrawn_at') ? 'danger' : 'warning')),
            ])
            ->recordActions([
                Action::make('paper')->label(__($l.'office.record_consent'))->icon(Heroicon::OutlinedDocumentCheck)
                    ->visible($tab === 'missing')
                    ->modalHeading(fn (RecordedPerson $p) => __($l.'office.record_title', ['name' => $p->name]))
                    ->modalDescription(__($l.'office.record_hint'))
                    ->schema([Textarea::make('note')->label(__($l.'attributes.note'))->required()->minLength(RecordRecordingConsent::NOTE_MIN)->maxLength(300)->rows(3)])
                    ->action(function (RecordedPerson $p, array $data) {
                        app(RecordRecordingConsent::class)->handle($p->id, (string) auth()->id(), $data['note']);
                        Notification::make()->success()->title(__('classroom::recordings.office.recorded'))->send();
                    }),
                Action::make('withdraw')->label(__($l.'consent.withdraw'))->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible($tab === 'agreed')
                    ->modalHeading(fn (RecordedPerson $p) => __($l.'office.withdraw_title', ['name' => $p->name]))
                    ->modalDescription(__($l.'office.withdraw_hint'))
                    ->schema([Textarea::make('note')->label(__($l.'attributes.note'))->required()->minLength(RecordRecordingConsent::NOTE_MIN)->maxLength(300)->rows(3)])
                    ->action(function (RecordedPerson $p, array $data) {
                        app(WithdrawRecordingConsent::class)->handle($p->id, (string) auth()->id(), true, $data['note']);
                        Notification::make()->success()->title(__('classroom::recordings.office.withdrawn'))->send();
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading(__($l.'office.people_empty.'.$tab));
    }

    /** Opens a recording in the player at the top (and logs it). */
    public function play(Recording $recording): void
    {
        $model = Recording::query()->findOrFail($recording->id);
        try {
            $link = app(WatchRecording::class)->handle($model, (string) auth()->id(), request()->ip(), request()->userAgent());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $tz = app(ViewerTimezone::class)->effective();
        $this->playing = [
            'id' => $model->id,
            'url' => $link->url,
            'media' => $model->media,
            'title' => (string) $recording->getAttribute('student_name').' · '.$recording->getAttribute('teacher_name').' · '
                .CarbonImmutable::parse($recording->getAttribute('lesson_at'))->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i'),
        ];
    }

    public function closePlayer(): void
    {
        $this->playing = null;
    }

    private function kept(Recording $r, string $tz): string
    {
        $l = 'classroom::recordings.office.';
        $day = fn (?CarbonImmutable $at) => $at?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y');

        return match (true) {
            $r->status === 'deleted' => __($l.'deleted_on', ['date' => $day($r->deleted_at), 'why' => $r->delete_reason === 'expired' ? __('classroom::recordings.expired') : Str::limit((string) $r->delete_reason, 30)]),
            $r->held_at !== null => __($l.'held_label'),
            isset($this->complaintsFor($r)[$r->session_id]) => $this->complaintsFor($r)[$r->session_id],
            $r->expires_at !== null => __($l.'until', ['date' => $day($r->expires_at)]),
            default => '—',
        };
    }

    /** @return array<string, string> */
    private function complaintsFor(Recording $r): array
    {
        if (! array_key_exists($r->session_id, $this->complaints)) {
            $this->complaints[$r->session_id] = app(RecordingHolds::class)->heldSessions([$r->session_id])[$r->session_id] ?? '';
        }

        return array_filter($this->complaints);
    }

    /** @return list<array{who: string, as: string, when: string, ip: ?string}> */
    private function openings(Recording $r, string $tz): array
    {
        return RecordingAccessLog::query()->where('recording_id', $r->id)->orderByDesc('opened_at')->limit(50)->get()
            ->map(fn (RecordingAccessLog $log) => [
                'who' => (string) DB::table('users')->where('id', $log->user_id)->value('name'),
                'as' => __('classroom::recordings.as.'.$log->as),
                'when' => $log->opened_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y · H:i'),
                'ip' => $log->ip,
            ])->all();
    }

    private function allowedTab(string $tab): string
    {
        $tabs = $this->tabs();

        return in_array($tab, $tabs, true) ? $tab : ($tabs[0] ?? 'recordings');
    }

    public static function length(int $seconds): string
    {
        return $seconds >= 3600 ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60) : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
