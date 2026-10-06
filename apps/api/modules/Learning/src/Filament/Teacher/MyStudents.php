<?php

namespace Modules\Learning\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\RoadmapQuery;
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
        $rows = DB::table('enrollments as e')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.teacher_id', auth()->id())->whereIn('e.status', ['active', 'pending_payment', 'paused'])
            ->orderBy('u.name')
            ->get(['e.id', 'e.status', 'e.student_id', 'u.name as student', 's.name_ar', 's.name_en']);
        $ids = $rows->pluck('id')->all();
        $lastReport = SessionReport::query()->whereIn('enrollment_id', $ids)->whereIn('status', ['submitted', 'reviewed'])
            ->selectRaw('enrollment_id, max(session_starts_at) as at')->groupBy('enrollment_id')->pluck('at', 'enrollment_id');
        $toMark = Homework::query()->whereIn('enrollment_id', $ids)->where('status', 'submitted')
            ->selectRaw('enrollment_id, count(*) as n')->groupBy('enrollment_id')->pluck('n', 'enrollment_id');

        return $rows->map(function (object $r) use ($lastReport, $toMark) {
            $map = app(RoadmapQuery::class)->for($r->id);

            return [
                'id' => $r->id,
                'student' => $r->student,
                'subject' => app()->getLocale() === 'ar' ? $r->name_ar : $r->name_en,
                'paused' => $r->status !== 'active',
                'percent' => $map['percent'] ?? null,
                'here' => $map ? ($map['finished'] ? __('learning::progress.finished') : ($map['current']['title'] ?? null)) : null,
                'last_report' => isset($lastReport[$r->id]) ? CarbonImmutable::parse($lastReport[$r->id])->locale(app()->getLocale())->diffForHumans() : null,
                'to_mark' => (int) ($toMark[$r->id] ?? 0),
                'url' => StudentProgress::getUrl(['enrollment' => $r->id]),
            ];
        })->all();
    }
}
