<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Live\BoardChanged;
use Modules\Scheduling\Queries\Groups\GroupBoard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * Scheduling › Groups (docs/architecture/02, "الحصص الجماعية"): the
 * academy's groups as cards (subject and teacher, weekly times on the
 * viewer's clock, seats, status, the next lesson, lessons held, ages), the
 * counts above them, filters by subject, teacher and status, and the way to
 * make one (Find a teacher in group mode). Lessons held follow the board's
 * signal; nothing polls.
 */
final class Groups extends Page
{
    protected string $view = 'scheduling::filament.groups.list';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = -4;

    protected static ?string $slug = 'groups';

    #[Url]
    public ?string $subject = null;

    #[Url]
    public ?string $teacher = null;

    #[Url]
    public ?string $status = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::FindTeachers->value) || Gate::allows(Ability::ViewLiveBoard->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::group_screens.list.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::group_screens.list.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::group_screens.list.subtitle').' '.ZoneHint::yours();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label(__('scheduling::group_screens.list.create'))->icon(Heroicon::OutlinedPlusCircle)
                ->url(fn () => FindTeacher::getUrl(['mode' => 'group']))
                ->visible(fn () => Gate::allows(Ability::FindTeachers->value)),
        ];
    }

    /** @return array{groups: list<array<string, mixed>>, counts: array{running: int, forming: int, ended: int, free: int}} */
    #[Computed]
    public function board(): array
    {
        $status = in_array($this->status, [LessonGroup::FORMING, LessonGroup::RUNNING, LessonGroup::ENDED], true) ? $this->status : null;

        return app(GroupBoard::class)->list(app(ViewerTimezone::class)->effective(), $this->subject ?: null, $this->teacher ?: null, $status);
    }

    /** @return array<string, string> the subjects that have groups */
    public function subjects(): array
    {
        $name = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        return DB::table('subjects')->whereIn('id', LessonGroup::query()->select('subject_id'))->orderBy($name)->pluck($name, 'id')->all();
    }

    /** @return array<string, string> the teachers who have groups */
    public function teachers(): array
    {
        return DB::table('users')->whereIn('id', LessonGroup::query()->select('teacher_id'))->orderBy('name')->pluck('name', 'id')->all();
    }

    public function clearFilters(): void
    {
        [$this->subject, $this->teacher, $this->status] = [null, null, null];
        unset($this->board);
    }

    public function filtered(): bool
    {
        return filled($this->subject) || filled($this->teacher) || filled($this->status);
    }

    public function liveChannel(): string
    {
        return BoardChanged::channel((string) app(TenantContext::class)->id());
    }

    public function updated(): void
    {
        unset($this->board);
    }
}
