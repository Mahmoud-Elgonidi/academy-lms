<?php

namespace Modules\Learning\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Queries\Hifz\HifzMap;
use Modules\Learning\Queries\Hifz\StudentHifz;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/** One student's hifz for the academy's supervisors: the map, the last two weeks' reviews and the mistakes notebook (read only). */
final class HifzStudent extends Page
{
    protected string $view = 'learning::filament.hifz.student';

    protected static ?string $slug = 'hifz/{enrollment}';

    protected static bool $shouldRegisterNavigation = false;

    public string $enrollment;

    public string $studentId;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewReports->value);
    }

    public function mount(string $enrollment): void
    {
        $course = app(EnrollmentApi::class)->find($enrollment);
        abort_if($course === null || ! app(HifzMap::class)->follows($course), 404);
        $this->enrollment = $enrollment;
        $this->studentId = $course->studentId;
    }

    public function getTitle(): string|Htmlable
    {
        return (string) DB::table('users')->where('id', $this->studentId)->value('name');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $row = DB::table('enrollments as e')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 'e.academy_id')->on('t.id', '=', 'e.teacher_id'))
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.id', $this->enrollment)->first(['t.name', 's.name_ar', 's.name_en']);

        return $row === null ? null : (app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en).' · '.$row->name;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function hifz(): array
    {
        return app(StudentHifz::class)->for($this->studentId, CarbonImmutable::now(), app(ViewerTimezone::class)->effective(), 20);
    }
}
