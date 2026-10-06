<?php

namespace Modules\Learning\Filament\Teacher;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\SharedKernel\Time\ViewerTimezone;

/** One student of the teacher: the roadmap, and the reports written so far (with the internal notes). */
final class StudentProgress extends Page
{
    protected string $view = 'learning::filament.teacher.student';

    protected static ?string $slug = 'students/{enrollment}';

    protected static bool $shouldRegisterNavigation = false;

    public string $enrollment = '';

    public function mount(string $enrollment): void
    {
        $found = app(EnrollmentApi::class)->find($enrollment);
        abort_unless($found !== null && $found->teacherId === auth()->id() && app(LearningAccess::class)->canSeeEnrollment(auth()->user(), $found), 404);
        $this->enrollment = $enrollment;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->summary['student'] ?? '';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->summary['subject'] ?? null;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        $row = DB::table('enrollments as e')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.id', $this->enrollment)->first(['u.name', 's.name_ar', 's.name_en']);
        $tz = app(ViewerTimezone::class)->effective();

        return [
            'student' => $row?->name,
            'subject' => app()->getLocale() === 'ar' ? $row?->name_ar : $row?->name_en,
            'map' => app(RoadmapQuery::class)->for($this->enrollment),
            'reports' => SessionReport::query()->where('enrollment_id', $this->enrollment)->whereIn('status', ['submitted', 'reviewed', 'due', 'overdue'])
                ->orderByDesc('session_starts_at')->limit(10)->get()
                ->map(fn (SessionReport $r) => [
                    'date' => $r->session_starts_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F'),
                    'status' => $r->status,
                    'overall' => $r->overall,
                    'note' => $r->notes_for_student,
                    'internal' => $r->notes_internal,
                    'url' => WriteReport::getUrl(['report' => $r->id]),
                ])->all(),
        ];
    }
}
