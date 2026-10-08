<?php

namespace Modules\Learning\Filament;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\Learning\Templates\TemplateAnswers;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The learning part of the student account in the back office: the program
 * and the roadmap, the latest reports and the homework. Shown on Scheduling's
 * account page through a render hook, so Scheduling does not know Learning.
 */
final class EnrollmentLearning extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public string $enrollmentId;

    /** @return array<string, mixed>|null */
    #[Computed]
    public function roadmap(): ?array
    {
        return app(RoadmapQuery::class)->for($this->enrollmentId, RoadmapQuery::MATERIALS_TEACHERS);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function reports(): array
    {
        $tz = app(ViewerTimezone::class)->effective();

        return SessionReport::query()->where('enrollment_id', $this->enrollmentId)->orderByDesc('session_starts_at')->limit(5)->get()
            ->map(fn (SessionReport $r) => [
                'when' => $r->session_starts_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M'),
                'kind' => $r->kind,
                'status' => $r->status,
                'late' => $r->late,
                'overall' => $r->overall,
                'note' => $r->kind === 'absence' ? $r->absence_note : $r->notes_for_student,
                'answers' => TemplateAnswers::ofReport($r, true),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function homework(): array
    {
        $tz = app(ViewerTimezone::class)->effective();

        return Homework::query()->where('enrollment_id', $this->enrollmentId)->orderByDesc('due_at')->limit(5)->get()
            ->map(fn (Homework $h) => [
                'title' => $h->title,
                'status' => $h->status,
                'due' => $h->due_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M'),
                'grade' => $h->grade,
            ])->all();
    }

    public function attachAction(): Action
    {
        $enrollment = app(EnrollmentApi::class)->find($this->enrollmentId);
        $programs = $enrollment ? app(CurriculumApi::class)->programsFor($enrollment->subjectId) : [];

        return Action::make('attach')
            ->label(fn () => $this->roadmap ? __('learning::progress.change') : __('learning::progress.choose'))
            ->icon(Heroicon::OutlinedMap)
            ->color(fn () => $this->roadmap ? 'gray' : 'primary')
            ->size('sm')
            ->visible(fn () => $programs !== [] && Gate::any([Ability::ManageSessions->value, Ability::ManageCurriculum->value]))
            ->fillForm(['program_id' => $enrollment?->programId])
            ->schema([
                Select::make('program_id')->label(__('learning::progress.program'))->required()->native(false)
                    ->options(collect($programs)->mapWithKeys(fn ($p) => [$p->id => "{$p->name} · ".trans_choice('learning::teacher.lessons_count', $p->lessons, ['n' => $p->lessons])])),
            ])
            ->action(function (array $data) {
                app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, $data['program_id'], (string) auth()->id());
                unset($this->roadmap);
                Notification::make()->success()->title(__('learning::progress.attached'))->send();
            });
    }

    public function render(): View
    {
        return view('learning::filament.enrollment-learning');
    }
}
