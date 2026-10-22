<?php

namespace Modules\Learning\Filament\Teacher;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Actions\Assist\DraftReportFromVoice;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Assist\RecitationTarget;
use Modules\Learning\Domain\MasteryRule;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ReportFormQuery;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\Learning\Templates\TemplateField;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The report form, filled in with today's plan (docs/architecture/02,
 * section 6, step 2): what was covered with an outcome and a score per
 * lesson, two ratings, notes, the subject's own questions (its report
 * template), and optional homework. Same Action as the API. When the
 * academy turned the helper on, a voice note drafts what is still empty
 * (DraftReportFromVoice): the teacher checks it, then sends as usual; and
 * homework handed in by voice may say what to recite, so each recording is
 * checked word by word for the teacher (RecitationTarget, CheckRecitation).
 *
 * @property-read Schema $form
 */
final class WriteReport extends Page
{
    use WithFileUploads;

    protected string $view = 'learning::filament.teacher.write-report';

    protected static ?string $slug = 'reports/{report}';

    protected static bool $shouldRegisterNavigation = false;

    public SessionReport $sessionReport;

    public ?array $data = [];

    /** The teacher's voice note while it is heard (deleted as soon as it is). */
    public ?TemporaryUploadedFile $voiceNote = null;

    /** @var ?array{heard: string, filled: list<string>, unsure: list<string>, left: list<string>} what the last draft did */
    public ?array $voiceDraft = null;

    private ?ReportTemplate $template = null;

    private bool $templateRead = false;

    /** The passage of the report's lessons the form starts from, as it reads (null: none, or the homework has its own). */
    public ?string $reciteSuggestion = null;

    /** The passage most marked in this lesson's mushaf (surah, from, to, title), offered with one tap. */
    public ?array $mistakesSuggestion = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::WriteReports->value);
    }

    public function mount(string $report): void
    {
        $this->sessionReport = SessionReport::query()->findOrFail($report);
        abort_unless($this->sessionReport->teacher_id === auth()->id(), 403);

        $homework = $this->sessionReport->homework()->first();
        $target = app(RecitationTarget::class);
        // What to recite: the homework's own, else the passage of the report's lessons, ready for when the teacher turns it on.
        $recite = $target->present($homework?->expectedSource());
        if ($recite === null && $this->sessionReport->kind === 'lesson') {
            $recite = $target->suggestion(array_column(app(ReportFormQuery::class)->items($this->sessionReport), 'node_id'));
            $this->reciteSuggestion = $recite['title'] ?? null;
        }
        if ($this->sessionReport->kind === 'lesson') {
            $this->mistakesSuggestion = $target->fromMistakes($this->sessionReport->session_id);
        }
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
                'practice' => $homework?->hasPractice() ?? false,
                'due_days' => $homework ? max(1, (int) round($this->sessionReport->submitted_at?->diffInDays($homework->due_at) ?? LearningSettings::current()->homework_days)) : LearningSettings::current()->homework_days,
                'recite' => $homework?->expected !== null,
                'expected' => ['surah' => $recite['surah'] ?? null, 'from' => $recite['from'] ?? null, 'to' => $recite['to'] ?? null],
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
        $fmt = fn ($t) => $t->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('l j F · H:i'));

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
                        ToggleButtons::make('homework.accepts')->label(__('learning::teacher.write.accepts'))->inline()->live()
                            ->options(array_combine(Homework::ACCEPTS, array_map(fn ($a) => __('learning::homework.accepts.'.$a), Homework::ACCEPTS))),
                        Select::make('homework.due_days')->label(__('learning::teacher.write.due_days'))->native(false)
                            ->options(collect([1, 2, 3, 5, 7, 14])->mapWithKeys(fn ($d) => [$d => trans_choice('learning::teacher.write.days', $d, ['n' => $d])])),
                    ]),
                    Toggle::make('homework.practice')->label(__('learning::homework.practice.with'))->helperText(__('learning::homework.practice.with_hint')),
                    ...$this->recitation(),
                ]),
        ])));
    }

    /** Whether the form asks what the homework is to recite: the helper is on, or the homework already says. */
    public function reciteOffered(): bool
    {
        return app(RecitationTarget::class)->offered() || $this->sessionReport->homework()->whereNotNull('expected')->exists();
    }

    /**
     * What a homework handed in by voice asks to recite, for the teacher's check: a passage of the Quran by surah and verses.
     *
     * @return list<Field|Grid>
     */
    private function recitation(): array
    {
        if (! $this->reciteOffered()) {
            return [];
        }
        $r = 'learning::recitation.';
        $surahs = app(CurriculumApi::class)->surahs();
        $voice = fn (Get $get) => in_array($get('homework.accepts'), RecitationTarget::ACCEPTS, true);
        $on = fn (Get $get) => $voice($get) && (bool) $get('homework.recite');

        return [
            Toggle::make('homework.recite')->label(__($r.'toggle'))->helperText(__($r.'hint'))->live()->visible($voice)
                ->hintAction(Action::make('fromMistakes')->link()
                    ->label(fn () => __('learning::mushaf.from_mistakes', ['title' => $this->mistakesSuggestion['title'] ?? '']))
                    ->visible(fn () => $this->mistakesSuggestion !== null)
                    ->action(function (Set $set): void {
                        $set('homework.recite', true);
                        foreach (['surah', 'from', 'to'] as $key) {
                            $set('homework.expected.'.$key, $this->mistakesSuggestion[$key] ?? null);
                        }
                    })),
            Grid::make(3)->schema([
                Select::make('homework.expected.surah')->label(__($r.'fields.surah'))->native(false)->searchable()->live()
                    ->helperText($this->reciteSuggestion === null ? null : __($r.'suggestion', ['title' => $this->reciteSuggestion]))
                    ->options(collect($surahs)->mapWithKeys(fn (array $s) => [$s['number'] => $s['number'].'. '.$s['name_ar'].' · '.$s['name_en']])->all())
                    ->required($on),
                TextInput::make('homework.expected.from')->label(__($r.'fields.from'))->numeric()->minValue(1)->required($on),
                TextInput::make('homework.expected.to')->label(__($r.'fields.to'))->numeric()->minValue(1)
                    ->maxValue(fn (Get $get) => collect($surahs)->firstWhere('number', (int) $get('homework.expected.surah'))['verses'] ?? null)
                    ->required($on),
            ])->visible($on),
        ];
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

    /** Whether this report can be drafted from a voice note (the academy's helper is on, the report not locked). */
    public function voiceOn(): bool
    {
        return $this->sessionReport->status !== 'reviewed' && app(AcademyAi::class)->on(AiFeature::ReportDraft);
    }

    /** Fills what the form leaves empty from the voice note just uploaded; saves nothing. */
    public function draftFromVoice(): void
    {
        abort_unless($this->sessionReport->teacher_id === auth()->id() && $this->voiceOn(), 403);
        $this->validate(['voiceNote' => ['required', 'file', 'max:6144', 'mimetypes:'.implode(',', SubmitHomework::AUDIO), new CleanFile]]);
        $state = $this->data ?? [];
        $current = $state;
        $current['items'] = array_values(array_map(fn (array $i) => array_intersect_key($i, array_flip(['node_id', 'outcome', 'score', 'note'])), $state['items'] ?? []));

        try {
            $draft = app(DraftReportFromVoice::class)->handle($this->sessionReport, (string) auth()->id(), AudioClip::fromUpload($this->voiceNote), $current);
        } catch (AiFailed $e) {
            Notification::make()->danger()->title(__('learning::assist.voice.title'))->body($e->sayable())->send();

            return;
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(__('learning::assist.voice.title'))->body(collect($e->errors())->flatten()->first())->send();

            return;
        } finally {
            $this->voiceNote?->delete();
            $this->voiceNote = null;
        }

        // The draft in the form: lessons keep their row (and the plan's reason; one it did not speak of stays as it
        // is), a lesson the teacher spoke of comes in after them, and homework keeps its practice choice.
        $report = $draft['report'];
        $fields = array_flip(['node_id', 'outcome', 'score', 'note']);
        $drafted = collect($report['items'] ?? [])->keyBy('node_id');
        $rows = collect($state['items'] ?? [])->map(fn (array $i) => $drafted->has($i['node_id'] ?? '')
            ? array_merge($i, array_intersect_key($drafted[$i['node_id']], $fields))
            : $i);
        $rows = $rows->concat($drafted->except($rows->pluck('node_id')->all())->map(fn (array $i) => ['reason' => null] + array_intersect_key($i, $fields)))->values()->all();
        $this->form->fill(array_merge($state, array_filter([
            'engagement' => $report['engagement'] ?? null,
            'overall' => $report['overall'] ?? null,
            'notes_for_student' => $report['notes_for_student'] ?? null,
            'notes_internal' => $report['notes_internal'] ?? null,
            'absence_note' => $report['absence_note'] ?? null,
            'answers' => $report['answers'] ?? null,
        ], fn ($v) => $v !== null), array_key_exists('items', $report) ? ['items' => $rows] : [], is_array($report['homework'] ?? null) ? [
            'homework' => array_merge($state['homework'] ?? [], $report['homework'], ['practice' => (bool) ($state['homework']['practice'] ?? false)]),
        ] : []));

        $this->voiceDraft = ['heard' => $draft['heard'], 'filled' => $draft['filled'], 'unsure' => $this->fieldNames($draft['unsure']), 'left' => $this->fieldNames($draft['left'])];
        Notification::make()->success()->title(trans_choice('learning::assist.voice.done', count($draft['filled']), ['count' => count($draft['filled'])]))->send();
    }

    /**
     * The form's fields by the names the teacher reads.
     *
     * @param  list<string>  $fields  as the draft names them (engagement, items.{node}, answers.{key}…)
     * @return list<string>
     */
    private function fieldNames(array $fields): array
    {
        $w = 'learning::teacher.write.';
        $lessons = $this->sessionReport->kind === 'lesson' ? app(ReportFormQuery::class)->lessonOptions($this->sessionReport) : [];
        $questions = collect($this->template()?->fieldList() ?? [])->mapWithKeys(fn (TemplateField $f) => [$f->key => $f->label()]);

        return array_values(array_unique(array_map(function (string $field) use ($w, $lessons, $questions): string {
            $parts = explode('.', $field);

            return match ($parts[0]) {
                'items' => isset($parts[1]) ? (string) str($lessons[$parts[1]] ?? __($w.'lesson'))->afterLast(' › ') : __($w.'plan'),
                'answers' => $questions[$parts[1] ?? ''] ?? __('learning::templates.form.section'),
                'homework' => __($w.'homework'),
                default => __($w.$parts[0]),
            };
        }, $fields)));
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $data['items'] = array_values(array_map(fn (array $i) => array_intersect_key($i, array_flip(['node_id', 'outcome', 'score', 'note'])), $data['items'] ?? []));
        // What to recite goes only when the form asks it: the passage while the switch is on, else none.
        $recite = (bool) ($data['homework']['recite'] ?? false) && in_array($data['homework']['accepts'] ?? null, RecitationTarget::ACCEPTS, true);
        if ($this->reciteOffered()) {
            $data['homework']['expected'] = $recite ? array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, $data['homework']['expected'] ?? []) : null;
        } else {
            unset($data['homework']['expected']);
        }
        unset($data['homework']['recite']);

        try {
            app(SubmitSessionReport::class)->handle($this->sessionReport, (string) auth()->id(), $data);
        } catch (ValidationException $e) {
            // Why what to recite cannot be checked shows under its switch.
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['data.'.($key === 'homework.expected' ? 'homework.recite' : $key) => $messages])->all());
        }

        Notification::make()->success()->title(__('learning::teacher.write.sent'))->send();
        // A lesson whose lessons have practice goes on to its homework game (the approved design, Teacher).
        $nodes = $this->sessionReport->items()->pluck('node_id')->all();
        $practice = $this->sessionReport->kind === 'lesson' && $nodes !== [] && array_filter(app(CurriculumApi::class)->practiceItems($nodes)) !== [];
        $this->redirect($practice ? HomeworkGame::getUrl(['report' => $this->sessionReport->id]) : MyReports::getUrl(), navigate: true);
    }
}
