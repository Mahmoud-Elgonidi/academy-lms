<?php

namespace Modules\Learning\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Learning\Models\Homework;
use Modules\Learning\Queries\TeacherStudents;
use Modules\SharedKernel\Authorization\Ability;

/** The teacher's students: where each one is on the roadmap, the last report, homework waiting for a mark. */
final class MyStudents extends Page
{
    protected string $view = 'learning::filament.teacher.students';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'students';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::WriteReports->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::teacher.students.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::teacher.students.title');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function students(): array
    {
        return array_map(fn (array $r) => [
            'id' => $r['enrollment_id'],
            'student' => $r['student'],
            'subject' => $r['subject'],
            'paused' => $r['paused'],
            'percent' => $r['percent'],
            'here' => $r['here'],
            'last_report' => $r['last_report_at']?->locale(app()->getLocale())->diffForHumans(),
            'to_mark' => $r['to_mark'],
            'url' => StudentProgress::getUrl(['enrollment' => $r['enrollment_id']]),
        ], app(TeacherStudents::class)->of((string) auth()->id()));
    }
}
