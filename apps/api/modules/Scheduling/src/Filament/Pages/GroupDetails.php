<?php

namespace Modules\Scheduling\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Groups\EndGroup;
use Modules\Scheduling\Actions\Groups\SaveGroupCapacity;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Live\BoardChanged;
use Modules\Scheduling\Queries\Groups\GroupBoard;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * One group (docs/architecture/02, "الحصص الجماعية"): its members (a first
 * name and an initial, the day they joined, the lessons they came to), its
 * seats and the students it waits for (SaveGroupCapacity: never below the
 * members, never above the teacher's most), its next lessons, and the ways
 * on: add a student (Find a teacher on this group) and end it (EndGroup,
 * after saying what that does).
 *
 * @property-read Schema $form
 */
final class GroupDetails extends Page
{
    protected string $view = 'scheduling::filament.groups.details';

    protected static ?string $slug = 'groups/{group}';

    protected static bool $shouldRegisterNavigation = false;

    public string $groupId;

    /** @var array<string, mixed> */
    public ?array $seats = [];

    public static function canAccess(): bool
    {
        return Groups::canAccess();
    }

    public function mount(string $group): void
    {
        $this->groupId = LessonGroup::query()->findOrFail($group)->id;
        $this->fillSeats();
    }

    #[Computed]
    public function group(): LessonGroup
    {
        return LessonGroup::query()->findOrFail($this->groupId);
    }

    #[Computed]
    public function card(): array
    {
        return app(GroupBoard::class)->card($this->group, $this->zone());
    }

    #[Computed]
    public function members(): array
    {
        return app(GroupBoard::class)->members($this->group);
    }

    #[Computed]
    public function upcoming(): array
    {
        return app(GroupBoard::class)->upcoming($this->group, $this->zone());
    }

    /** The teacher's most students in one lesson (null: they no longer take groups). */
    #[Computed]
    public function teacherMax(): ?int
    {
        return app(PeopleApi::class)->groupCapacity($this->group->teacher_id);
    }

    public function getTitle(): string|Htmlable
    {
        $card = $this->card;

        return $card['name'] ?: __('scheduling::group_screens.details.title', ['subject' => $card['subject'], 'teacher' => $card['teacher']]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return ZoneHint::yours();
    }

    public function getBreadcrumbs(): array
    {
        return [Groups::getUrl() => __('scheduling::group_screens.list.nav'), $this->card['subject'].' · '.$this->card['teacher']];
    }

    public function canManage(): bool
    {
        return Gate::allows(Ability::ManageSessions->value) && $this->group->status !== LessonGroup::ENDED;
    }

    public function seatsForm(Schema $schema): Schema
    {
        $w = 'scheduling::group_screens.details.seats.';

        return $schema->statePath('seats')->columns(['default' => 1, 'sm' => 2])->components([
            TextInput::make('capacity')->label(__($w.'capacity'))
                ->numeric()->integer()->required()
                ->minValue(fn () => max(GroupRules::MIN_SIZE, $this->card['taken']))->maxValue(fn () => $this->teacherMax ?? GroupRules::MAX_SIZE)
                ->helperText(fn () => $this->teacherMax === null ? __($w.'no_groups') : __($w.'capacity_hint', ['min' => max(GroupRules::MIN_SIZE, $this->card['taken']), 'max' => $this->teacherMax]))
                ->suffix(__($w.'students'))->disabled(fn () => ! $this->canManage()),
            TextInput::make('min_to_start')->label(__($w.'min'))->placeholder(__($w.'first'))
                ->numeric()->integer()->minValue(1)->maxValue(fn (Get $get) => max(1, (int) $get('capacity')))
                ->helperText(__($w.'min_hint'))->suffix(__($w.'students'))
                ->visible(fn () => $this->group->status === LessonGroup::FORMING)->disabled(fn () => ! $this->canManage()),
        ]);
    }

    public function saveSeats(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $data = $this->seatsForm->getState();
        $min = $this->group->status === LessonGroup::FORMING && ($data['min_to_start'] ?? null) !== null && $data['min_to_start'] !== '' ? (int) $data['min_to_start'] : null;
        app(SaveGroupCapacity::class)->handle($this->groupId, (int) $data['capacity'], (string) auth()->id(), $min);
        unset($this->group, $this->card);
        $this->fillSeats();
        Notification::make()->success()->title(__('scheduling::group_screens.details.seats.saved'))->send();
    }

    protected function getHeaderActions(): array
    {
        $w = 'scheduling::group_screens.details.';

        return [
            Action::make('add')->label(__($w.'add'))->icon(Heroicon::OutlinedUserPlus)
                ->url(fn () => FindTeacher::getUrl(['mode' => 'group', 'group' => $this->groupId, 'subject' => $this->group->subject_id, 'return' => self::getUrl(['group' => $this->groupId], panel: 'academy', isAbsolute: false)]))
                ->visible(fn () => $this->canManage() && Gate::allows(Ability::FindTeachers->value) && $this->card['free'] > 0),
            Action::make('end')->label(__($w.'end.action'))->icon(Heroicon::OutlinedStopCircle)->color('danger')->outlined()
                ->visible(fn () => $this->canManage())
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedStopCircle)
                ->modalHeading(__($w.'end.title'))
                ->modalDescription(fn () => __($w.'end.intro'))
                ->modalContent(fn () => view('scheduling::filament.groups.end-effects', ['effects' => $this->endEffects()]))
                ->schema([
                    Textarea::make('reason')->label(__($w.'end.reason'))->helperText(__($w.'end.reason_hint'))->required()->maxLength(500)->rows(2),
                ])
                ->modalSubmitActionLabel(__($w.'end.submit'))
                ->action(function (array $data) {
                    $done = app(EndGroup::class)->handle($this->groupId, (string) $data['reason'], (string) auth()->id());
                    unset($this->group, $this->card, $this->members, $this->upcoming);
                    Notification::make()->success()->title(__('scheduling::group_screens.details.end.done'))
                        ->body(trans_choice('scheduling::group_screens.details.end.done_body', $done['cancelled'], ['n' => $done['cancelled'], 'courses' => $done['courses']]))->send();
                }),
        ];
    }

    /**
     * What ending does, said before it is done: each member's course ends
     * today, the booked lessons left are cancelled with their minutes back,
     * and the weekly times free up.
     *
     * @return list<string>
     */
    public function endEffects(): array
    {
        $w = 'scheduling::group_screens.details.end.effects.';
        $live = count(array_filter($this->members, fn (array $m) => $m['live']));
        $lessons = Session::query()->where('group_id', $this->groupId)
            ->where('status', SessionStatus::Scheduled->value)->where('starts_at', '>', now())->count();

        return [
            trans_choice($w.'courses', $live, ['n' => $live, 'date' => app(CourseDays::class)->today()->locale(app()->getLocale())->translatedFormat('j F')]),
            trans_choice($w.'lessons', $lessons, ['n' => $lessons]),
            __($w.'times'),
            __($w.'billing'),
        ];
    }

    public function liveChannel(): string
    {
        return BoardChanged::channel((string) app(TenantContext::class)->id());
    }

    private function zone(): string
    {
        return app(ViewerTimezone::class)->effective();
    }

    private function fillSeats(): void
    {
        $this->seatsForm->fill(['capacity' => $this->group->capacity, 'min_to_start' => $this->group->min_to_start]);
    }
}
