<?php

namespace Modules\Learning\Filament\Teacher;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\Hifz\ClearHifzPages;
use Modules\Learning\Actions\Hifz\MarkMemorized;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\Hifz\HifzMap;
use Modules\Learning\Queries\Hifz\StudentHifz;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One student of the teacher: the roadmap, the reports written so far (with
 * the internal notes), and the hifz map (once the platform has the Quran's
 * text): where each page stands, the last reviews, and saying which pages
 * the student already knows or taking pages off.
 */
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

    /**
     * The student's hifz map, the last two weeks' reviews and the mistakes notebook (the words their teachers marked in
     * lessons' mushaf); null for a course the map does not follow (HifzMap::follows).
     *
     * @return ?array{map: array<string, mixed>, recent: list<array{page: int, grade: string, source: string, at: string}>, book: array<string, mixed>, tz: string}
     */
    #[Computed]
    public function hifz(): ?array
    {
        $course = app(EnrollmentApi::class)->find($this->enrollment);
        if ($course === null || ! app(HifzMap::class)->follows($course)) {
            return null;
        }

        return app(StudentHifz::class)->for($course->studentId, CarbonImmutable::now(), app(ViewerTimezone::class)->effective());
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        $h = 'learning::hifz.';
        $choice = fn () => [
            Select::make('juz')->label(__($h.'attributes.juz'))->multiple()->native(false)
                ->options(collect(range(1, 30))->mapWithKeys(fn (int $j) => [$j => __($h.'juz', ['juz' => $j])])->all()),
            Select::make('surahs')->label(__($h.'attributes.surahs'))->multiple()->searchable()->native(false)
                ->options(collect(app(CurriculumApi::class)->surahs())->mapWithKeys(fn (array $s) => [$s['number'] => $s['number'].'. '.$s['name_ar'].' · '.$s['name_en']])->all()),
            TagsInput::make('pages')->label(__($h.'attributes.pages'))->placeholder('582'),
        ];
        $pages = fn (array $data) => app(HifzPages::class)->chosen(
            array_map('intval', $data['juz'] ?? []), array_map('intval', $data['surahs'] ?? []), array_map('intval', array_filter($data['pages'] ?? [], 'is_numeric')),
        );

        return [
            Action::make('hifzMark')->label(__($h.'mark.action'))->icon(Heroicon::OutlinedBookOpen)
                ->visible(fn () => $this->hifz !== null)
                ->modalHeading(__($h.'mark.heading'))->modalDescription(__($h.'mark.hint'))
                ->schema([...$choice(), Radio::make('strength')->label(__($h.'attributes.strength'))->options(trans($h.'marks'))->default('good')->required()])
                ->action(function (array $data) use ($pages, $h): void {
                    $student = app(EnrollmentApi::class)->find($this->enrollment)->studentId;
                    $count = app(MarkMemorized::class)->handle($student, $this->picked($pages, $data), (string) $data['strength']);
                    unset($this->hifz);
                    Notification::make()->success()->title(trans_choice($h.'mark.done', $count, ['count' => $count]))->send();
                }),
            Action::make('hifzClear')->label(__($h.'clear.action'))->icon(Heroicon::OutlinedMinusCircle)->color('gray')
                ->visible(fn () => ($this->hifz['map']['counts']['memorized'] ?? 0) + ($this->hifz['map']['counts']['learning'] ?? 0) > 0)
                ->modalHeading(__($h.'clear.heading'))->modalDescription(__($h.'clear.hint'))
                ->schema($choice())
                ->action(function (array $data) use ($pages, $h): void {
                    $student = app(EnrollmentApi::class)->find($this->enrollment)->studentId;
                    $count = app(ClearHifzPages::class)->handle($student, $this->picked($pages, $data));
                    unset($this->hifz);
                    Notification::make()->success()->title(trans_choice($h.'clear.done', $count, ['count' => $count]))->send();
                }),
        ];
    }

    /**
     * The pages chosen in a form, or why not (under its fields).
     *
     * @return list<int>
     */
    private function picked(Closure $pages, array $data): array
    {
        try {
            $chosen = $pages($data);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["mountedActions.0.data.{$k}" => $m])->all());
        }

        return $chosen !== [] ? $chosen : throw ValidationException::withMessages(['mountedActions.0.data.juz' => __('learning::hifz.errors.none')]);
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
            'map' => app(RoadmapQuery::class)->for($this->enrollment, RoadmapQuery::MATERIALS_TEACHERS),
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
