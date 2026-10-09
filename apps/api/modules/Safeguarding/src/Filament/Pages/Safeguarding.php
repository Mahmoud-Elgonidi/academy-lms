<?php

namespace Modules\Safeguarding\Filament\Pages;

use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Safeguarding\Actions\AcknowledgeConcern;
use Modules\Safeguarding\Actions\AddConcernNote;
use Modules\Safeguarding\Actions\ClassifyConcern;
use Modules\Safeguarding\Actions\CloseConcern;
use Modules\Safeguarding\Actions\PauseConcernTeacher;
use Modules\Safeguarding\Actions\ReopenConcern;
use Modules\Safeguarding\Actions\ReportConcern;
use Modules\Safeguarding\Enums\ConcernCategory;
use Modules\Safeguarding\Enums\ConcernOutcome;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Live\ConcernsChanged;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Queries\ConcernBoard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * Concerns about children's safety, for the academy's safeguarding leads
 * only (docs/architecture/02, section 15): the open ones, urgent first, and
 * one in full with every step taken. A lead takes it, writes notes, puts its
 * details right, keeps the teacher it may be about from lessons with minors,
 * and closes it with an outcome and, if they like, a reply the reporter
 * reads. A concern told by phone or in person is written down here.
 */
final class Safeguarding extends Page
{
    protected string $view = 'safeguarding::filament.board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'safeguarding';

    #[Url]
    public ?string $concern = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::HandleSafeguarding->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('safeguarding::screens.office.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = Concern::query()->where('status', ConcernStatus::New->value)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getTitle(): string|Htmlable
    {
        return __('safeguarding::screens.office.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('safeguarding::screens.office.lead');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function board(): array
    {
        $lists = app(ConcernBoard::class)->lists();
        $this->concern ??= $lists['open'][0]['id'] ?? null;

        return $lists + ['channel' => ConcernsChanged::channel(app(TenantContext::class)->require()->id)];
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function selected(): ?array
    {
        return $this->concern ? app(ConcernBoard::class)->details($this->concern) : null;
    }

    public function show(string $id): void
    {
        $this->concern = $id;
        unset($this->selected);
    }

    public function when(?CarbonInterface $at): ?string
    {
        return $at === null ? null : app(LocalTimePresenter::class)->present($at)['label'] ?? null;
    }

    /** The lesson's day on the viewer's clock (the calendar opens on it). */
    public function day(CarbonInterface $at): string
    {
        return substr((string) app(LocalTimePresenter::class)->present($at)['local'], 0, 10);
    }

    protected function getHeaderActions(): array
    {
        $l = 'safeguarding::screens.office.';
        $f = 'safeguarding::concerns.fields.';

        return [
            Action::make('record')->label(__($l.'actions.record'))->icon(Heroicon::OutlinedPencilSquare)
                ->modalDescription(__($l.'actions.record_hint'))->modalWidth(Width::Large)
                ->schema([
                    Select::make('student')->label(__($f.'student'))->searchable()->native(false)
                        ->getSearchResultsUsing(fn (string $search) => $this->people('student', $search))
                        ->getOptionLabelUsing(fn ($value) => DB::table('users')->where('id', $value)->value('name')),
                    Select::make('teacher')->label(__($f.'teacher'))->searchable()->native(false)
                        ->getSearchResultsUsing(fn (string $search) => $this->people('teacher', $search))
                        ->getOptionLabelUsing(fn ($value) => DB::table('users')->where('id', $value)->value('name')),
                    Select::make('category')->label(__($f.'category'))->required()->native(false)->options($this->categories()),
                    Textarea::make('body')->label(__($f.'body'))->required()->rows(5)->maxLength(5000)->helperText(__($l.'actions.record_body_hint')),
                    Toggle::make('urgent')->label(__($f.'urgent')),
                ])
                ->action(function (array $data) use ($l) {
                    $concern = app(ReportConcern::class)->handle((string) auth()->id(), 'staff', $data['student'] ?? null, (string) $data['category'], (string) $data['body'],
                        (bool) ($data['urgent'] ?? false), null, $data['teacher'] ?? null);
                    $this->show($concern->id);
                    unset($this->board);
                    Notification::make()->success()->title(__($l.'done.recorded', ['number' => $concern->reference()]))->send();
                }),
        ];
    }

    public function takeAction(): Action
    {
        return Action::make('take')->label(__('safeguarding::screens.office.actions.take'))->icon(Heroicon::OutlinedHandRaised)->size('sm')
            ->action(function () {
                app(AcknowledgeConcern::class)->handle((string) $this->concern, (string) auth()->id());
                $this->refreshAll();
            });
    }

    public function noteAction(): Action
    {
        return Action::make('note')->label(__('safeguarding::screens.office.actions.note'))->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('gray')->size('sm')
            ->modalWidth(Width::Large)
            ->schema([Textarea::make('body')->label(__('safeguarding::concerns.fields.note'))->required()->rows(5)->maxLength(5000)])
            ->action(function (array $data) {
                app(AddConcernNote::class)->handle((string) $this->concern, (string) $data['body'], (string) auth()->id());
                $this->refreshAll();
            });
    }

    public function classifyAction(): Action
    {
        $f = 'safeguarding::concerns.fields.';

        return Action::make('classify')->label(__('safeguarding::screens.office.actions.classify'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')->size('sm')
            ->modalWidth(Width::Large)
            ->fillForm(fn () => ($c = $this->selected['concern'] ?? null) ? ['category' => $c->category->value, 'student' => $c->student_id, 'teacher' => $c->teacher_id, 'urgent' => $c->urgent] : [])
            ->schema([
                Select::make('category')->label(__($f.'category'))->required()->native(false)->options($this->categories()),
                Select::make('student')->label(__($f.'student'))->searchable()->native(false)
                    ->getSearchResultsUsing(fn (string $search) => $this->people('student', $search))
                    ->getOptionLabelUsing(fn ($value) => DB::table('users')->where('id', $value)->value('name')),
                Select::make('teacher')->label(__($f.'teacher'))->searchable()->native(false)
                    ->getSearchResultsUsing(fn (string $search) => $this->people('teacher', $search))
                    ->getOptionLabelUsing(fn ($value) => DB::table('users')->where('id', $value)->value('name')),
                Toggle::make('urgent')->label(__($f.'urgent')),
            ])
            ->action(function (array $data) {
                app(ClassifyConcern::class)->handle((string) $this->concern, (string) $data['category'], $data['student'] ?? null, $data['teacher'] ?? null, (bool) ($data['urgent'] ?? false), (string) auth()->id());
                $this->refreshAll();
            });
    }

    public function pauseAction(): Action
    {
        $l = 'safeguarding::screens.office.actions.';

        return Action::make('pause')->label(__($l.'pause'))->icon(Heroicon::OutlinedPauseCircle)->color('danger')->size('sm')
            ->modalDescription(__($l.'pause_hint'))->modalWidth(Width::Medium)
            ->schema([Textarea::make('reason')->label(__('safeguarding::concerns.fields.reason'))->required()->rows(2)->minLength(5)->maxLength(400)])
            ->action(function (array $data) {
                app(PauseConcernTeacher::class)->pause((string) $this->concern, (string) $data['reason'], (string) auth()->id());
                $this->refreshAll();
            });
    }

    public function resumeAction(): Action
    {
        $l = 'safeguarding::screens.office.actions.';

        return Action::make('resume')->label(__($l.'resume'))->icon(Heroicon::OutlinedPlayCircle)->color('gray')->size('sm')
            ->modalDescription(__($l.'resume_hint'))->modalWidth(Width::Medium)
            ->schema([Textarea::make('reason')->label(__('safeguarding::concerns.fields.reason'))->required()->rows(2)->minLength(5)->maxLength(400)])
            ->action(function (array $data) {
                app(PauseConcernTeacher::class)->resume((string) $this->concern, (string) $data['reason'], (string) auth()->id());
                $this->refreshAll();
            });
    }

    public function closeAction(): Action
    {
        $l = 'safeguarding::screens.office.actions.';
        $f = 'safeguarding::concerns.fields.';

        return Action::make('close')->label(__($l.'close'))->icon(Heroicon::OutlinedCheckCircle)->color('success')->size('sm')
            ->modalDescription(__($l.'close_hint'))->modalWidth(Width::Large)
            ->schema([
                Radio::make('outcome')->label(__($f.'outcome'))->required()->options(ConcernOutcome::options()),
                Textarea::make('note')->label(__($l.'close_note'))->required()->rows(4)->minLength(5)->maxLength(5000),
                Textarea::make('reply')->label(__($f.'reply'))->helperText(__($l.'reply_hint'))->rows(3)->maxLength(2000)
                    ->visible(fn () => ($this->selected['concern']->reporter_side ?? 'staff') !== 'staff'),
            ])
            ->action(function (array $data) use ($l) {
                app(CloseConcern::class)->handle((string) $this->concern, (string) $data['outcome'], $data['reply'] ?? null, (string) $data['note'], (string) auth()->id());
                $this->refreshAll();
                Notification::make()->success()->title(__($l.'closed'))->send();
            });
    }

    public function reopenAction(): Action
    {
        $l = 'safeguarding::screens.office.actions.';

        return Action::make('reopen')->label(__($l.'reopen'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')->size('sm')
            ->modalWidth(Width::Medium)
            ->schema([Textarea::make('reason')->label(__('safeguarding::concerns.fields.reason'))->required()->rows(3)->minLength(5)->maxLength(5000)])
            ->action(function (array $data) {
                app(ReopenConcern::class)->handle((string) $this->concern, (string) $data['reason'], (string) auth()->id());
                $this->refreshAll();
            });
    }

    /** @return array<string, string> */
    private function categories(): array
    {
        return collect(ConcernCategory::cases())->mapWithKeys(fn (ConcernCategory $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    private function people(string $role, string $search): array
    {
        return DB::table('users as u')->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('r.role', $role)->where('u.status', 'active')->where('u.name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
            ->orderBy('u.name')->limit(20)->pluck('u.name', 'u.id')->all();
    }

    private function refreshAll(): void
    {
        unset($this->board, $this->selected);
    }
}
