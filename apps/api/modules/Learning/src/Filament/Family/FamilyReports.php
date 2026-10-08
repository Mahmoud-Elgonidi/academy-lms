<?php

namespace Modules\Learning\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Templates\TemplateAnswers;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Time\ViewerTimezone;

/** What the teacher wrote after each lesson, for the family: never the internal notes. */
final class FamilyReports extends Page
{
    protected string $view = 'learning::filament.family.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'reports';

    /** Children leave the reports to their guardian; their home tells them how they are doing. */
    public static function shouldRegisterNavigation(): bool
    {
        return FamilyLearning::stage() !== LearnerStage::Kids;
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::family.reports.nav.'.FamilyLearning::stage()->value);
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::family.reports.title');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function reports(): array
    {
        $learner = FamilyLearning::learner();
        if ($learner === null) {
            return [];
        }
        $tz = app(ViewerTimezone::class)->effective();
        $reports = SessionReport::query()->with('items')->where('student_id', $learner->id)->whereIn('status', ['submitted', 'reviewed'])
            ->orderByDesc('session_starts_at')->limit(30)->get();
        $teachers = DB::table('users')->whereIn('id', $reports->pluck('teacher_id')->unique())->pluck('name', 'id');
        $titles = DB::table('curriculum_nodes')->whereIn('id', $reports->flatMap->items->pluck('node_id')->unique())
            ->pluck(app()->getLocale() === 'ar' ? 'title_ar' : 'title_en', 'id');

        return $reports->map(fn (SessionReport $r) => [
            'id' => $r->id,
            'absence' => $r->kind === 'absence',
            'date' => FamilyLearning::date($r->session_starts_at, $tz, 'l j F · H:i'),
            'teacher' => $teachers[$r->teacher_id] ?? null,
            'overall' => $r->overall,
            'engagement' => $r->engagement,
            'note' => $r->kind === 'absence' ? $r->absence_note : $r->notes_for_student,
            'answers' => TemplateAnswers::ofReport($r, false),
            'items' => $r->items->map(fn (ReportItem $i) => [
                'title' => $titles[$i->node_id] ?? '—',
                'outcome' => __('learning::reports.outcomes.'.$i->outcome),
                'tone' => ['mastered' => 'good', 'needs_review' => 'warn'][$i->outcome] ?? null,
                'score' => $i->score,
            ])->all(),
        ])->all();
    }
}
