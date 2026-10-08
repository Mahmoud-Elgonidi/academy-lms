<?php

namespace Modules\Learning\Filament\Teacher;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Domain\MasteryRule;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ReportFormQuery;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\Learning\Templates\TemplateField;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The report form, filled in with today's plan (docs/architecture/02,
 * section 6, step 2): what was covered with an outcome and a score per
 * lesson, two ratings, notes, the subject's own questions (its report
 * template), and optional homework. Same Action as the API.
 *
 * @property-read Schema $form
 */
final class WriteReport extends Page
{
    protected string $view = 'learning::filament.teacher.write-report';

    protected static ?string $slug = 'reports/{report}';

    protected static bool $shouldRegisterNavigation = false;

    public SessionReport $sessionReport;

    public ?array $data = [];

    private ?ReportTemplate $template = null;

    private bool $templateRead = false;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::WriteReports->value);
    }

    public function mount(string $report): void
    {
        $this->sessionReport = SessionReport::query()->findOrFail($report);
        abort_unless($this->sessionReport->teacher_id === auth()->id(), 403);

        $homework = $this->sessionReport->homework()->first();
        $this->form->fill([
            'items' => app(ReportFormQuery::class)->items($this->sessionReport),
            'engagement' => $this->sessionReport->engagement,
            'overall' => $this->sessionReport->overall,
            'notes_for_student' => $this->sessionReport->notes_for_student,
            'notes_internal' => $this->sessionReport->notes_internal,
            'absence_note' => $this->sessionReport->absence_note,
            'answers' => $this->sessionReport->answers ?? [],
            'homework' => [
                'title' => $homework?->title,
                'instructions' => $homework?->instructions,
                'accepts' => $homework->accepts ?? 'any',
                'due_days' => $homework ? max(1, (int) round($this->sessionReport->submitted_at?->diffInDays($homework->due_at) ?? LearningSettings::current()->homework_days)) : LearningSettings::current()->homework_days,
            ],
        ]);
        if ($this->sessionReport->status === 'reviewed') {
            $this->form->disabled();
        }
    }

    public function getTitle(): string|Htmlable
    {
        $student = (string) DB::table('users')->where('id', $this->sessionReport->student_id)->value('name');

        return __('learning::teacher.write.title_'.$this->sessionReport->kind, ['student' => $student]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $tz = app(ViewerTimezone::class)->effective();
        $fmt = fn ($t) => $t->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i');

        return __('learning::teacher.write.subtitle', ['when' => $fmt($this->sessionReport->session_starts_at), 'due' => $fmt($this->sessionReport->due_at)])
            .' · '.__('learning::reports.statuses.'.$this->sessionReport->status);
    }

    /** @return array<string, mixed>|null */
    public function roadmap(): ?array
    {
        return $this->sessionReport->enrollment_id ? app(RoadmapQuery::class)->for($this->sessionReport->enrollment_id, RoadmapQuery::MATERIALS_TEACHERS) : null;
    }

    /** @return array<string, string> lesson title => objectives, for the lessons planned or covered */
    public function plannedObjectives(): array
    {
        return collect(app(ReportFormQuery::class)->items($this->sessionReport))
            ->filter(fn (array $i) => filled($i['objectives']))
            ->mapWithKeys(fn (array $i) => [$i['title'] => $i['objectives']])->all();
    }

    public function form(Schema $schema): Schema
    {
        $scores = array_combine(range(1, 5), array_map('strval', range(1, 5)));
        $outcomes = array_combine(MasteryRule::OUTCOMES, array_map(fn ($o) => __('learning::reports.outcomes.'.$o), MasteryRule::OUTCOMES));
        $lesson = $this->sessionReport->kind === 'lesson';
        $options = $lesson ? app(ReportFormQuery::class)->lessonOptions($this->sessionReport) : [];

        return $schema->statePath('data')->components(array_values(array_filter([
            $lesson ? Section::make(__('learning::teacher.write.plan'))
                ->description($options === [] ? __('learning::teacher.write.no_program') : __('learning::teacher.write.plan_hint'))
                ->visible($options !== [])
                ->schema([
                    Repeater::make('items')->hiddenLabel()
                        ->schema([
                            Hidden::make('reason'),
                            Select::make('node_id')->label(__('learning::teacher.write.lesson'))->options($options)->required()->searchable()->distinct()->native(false)
                                ->columnSpan(2),
                            ToggleButtons::make('outcome')->label(__('learning::teacher.write.outcome'))->options($outcomes)->required()->inline()
                                ->colors(['introduced' => 'gray', 'practiced' => 'info', 'mastered' => 'success', 'needs_review' => 'warning'])->columnSpan(2),
                            ToggleButtons::make('score')->label(__('learning::teacher.write.score'))->options($scores)->inline(),
                            TextInput::make('note')->label(__('learning::teacher.write.note'))->maxLength(500)->columnSpan(3),
                        ])
                        ->columns(4)
                        ->itemLabel(fn (array $state) => ($state['reason'] ?? null) ? __('learning::reports.reasons.'.$state['reason']) : null)
                        ->addActionLabel(__('learning::teacher.write.add_item'))
                        ->reorderable(false)->defaultItems(0)->maxItems(10),
                ]) : null,
            $lesson ? Section::make(__('learning::teacher.write.scores'))->schema([
                Grid::make(2)->schema([
                    ToggleButtons::make('engagement')->label(__('learning::teacher.write.engagement'))->options($scores)->inline()->required(),
                    ToggleButtons::make('overall')->label(__('learning::teacher.write.overall'))->options($scores)->inline()->required(),
                ]),
                Textarea::make('notes_for_student')->label(__('learning::teacher.write.notes_for_student'))->rows(3)->maxLength(3000),
                Textarea::make('notes_internal')->label(__('learning::teacher.write.notes_internal'))->rows(2)->maxLength(3000),
            ]) : Section::make(__('learning::reports.kinds.absence'))->schema([
                Textarea::make('absence_note')->label(__('learning::teacher.write.absence_note'))->helperText(__('learning::teacher.write.absence_hint'))
                    ->rows(3)->required()->maxLength(1000),
                Textarea::make('notes_internal')->label(__('learning::teacher.write.notes_internal'))->rows(2)->maxLength(3000),
            ]),
            $this->questions(),
            Section::make(__('learning::teacher.write.homework'))->collapsible()->collapsed(fn () => blank($this->data['homework']['title'] ?? null))
                ->schema([
                    TextInput::make('homework.title')->label(__('learning::teacher.write.homework_title'))->maxLength(200),
                    Textarea::make('homework.instructions')->label(__('learning::teacher.write.homework_instructions'))->rows(2)->maxLength(3000),
                    Grid::make(2)->schema([
                        ToggleButtons::make('homework.accepts')->label(__('learning::teacher.write.accepts'))->inline()
                            ->options(array_combine(Homework::ACCEPTS, array_map(fn ($a) => __('learning::homework.accepts.'.$a), Homework::ACCEPTS))),
                        Select::make('homework.due_days')->label(__('learning::teacher.write.due_days'))->native(false)
                            ->options(collect([1, 2, 3, 5, 7, 14])->mapWithKeys(fn ($d) => [$d => trans_choice('learning::teacher.write.days', $d, ['n' => $d])])),
                    ]),
                ]),
        ])));
    }

    /** The subject's own questions (the version this report was handed in with, else the one now). */
    private function questions(): ?Section
    {
        $template = $this->template();
        if ($template === null || $template->fields === []) {
            return null;
        }
        $subject = (string) DB::table('sessions as s')->join('subjects as j', fn ($j) => $j->on('j.academy_id', '=', 's.academy_id')->on('j.id', '=', 's.subject_id'))
            ->where('s.id', $this->sessionReport->session_id)->value(app()->getLocale() === 'ar' ? 'j.name_ar' : 'j.name_en');

        return Section::make(__('learning::templates.form.section'))
            ->description(__('learning::templates.form.hint', ['subject' => $subject]))
            ->schema(array_map(fn (TemplateField $f) => $this->question($f), $template->fieldList()))
            ->columns(2);
    }

    private function question(TemplateField $f): Field
    {
        $name = 'answers.'.$f->key;
        $field = match ($f->type) {
            'rating' => ToggleButtons::make($name)->options(array_combine(range(1, 5), array_map('strval', range(1, 5))))->inline(),
            'number' => TextInput::make($name)->numeric()->minValue($f->min)->maxValue($f->max)->suffix($f->unit())->step('any'),
            'choice' => ToggleButtons::make($name)->options($f->choices())->inline(),
            'yes_no' => ToggleButtons::make($name)->boolean(__('learning::templates.answer.yes'), __('learning::templates.answer.no'))->inline(),
            default => TextInput::make($name)->maxLength(500)->columnSpanFull(),
        };

        return $field->label($f->label())->required($f->required)
            ->hint($f->family ? null : __('learning::templates.internal'))->hintIcon($f->family ? null : 'heroicon-m-lock-closed')
            ->hintIconTooltip($f->family ? null : __('learning::templates.form.internal_hint'));
    }

    private function template(): ?ReportTemplate
    {
        if (! $this->templateRead) {
            $this->template = ReportTemplate::forReport($this->sessionReport);
            $this->templateRead = true;
        }

        return $this->template;
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $data['items'] = array_values(array_map(fn (array $i) => array_intersect_key($i, array_flip(['node_id', 'outcome', 'score', 'note'])), $data['items'] ?? []));

        try {
            app(SubmitSessionReport::class)->handle($this->sessionReport, (string) auth()->id(), $data);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ["data.{$key}" => $messages])->all());
        }

        Notification::make()->success()->title(__('learning::teacher.write.sent'))->send();
        $this->redirect(MyReports::getUrl(), navigate: true);
    }
}
