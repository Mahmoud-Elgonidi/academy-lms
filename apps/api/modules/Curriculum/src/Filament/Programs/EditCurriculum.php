<?php

namespace Modules\Curriculum\Filament\Programs;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\DeleteNode;
use Modules\Curriculum\Actions\MoveNode;
use Modules\Curriculum\Actions\Outline\ApplyOutline;
use Modules\Curriculum\Actions\Outline\CopyProgram;
use Modules\Curriculum\Actions\Outline\SuggestOutline;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Actions\Practice\SuggestNodeItems;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\Resources\AddNodeResource;
use Modules\Curriculum\Actions\Resources\MoveNodeResource;
use Modules\Curriculum\Actions\Resources\RemoveNodeResource;
use Modules\Curriculum\Actions\Resources\UpdateNodeResource;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\Curriculum\Domain\PublishReadiness;
use Modules\Curriculum\Domain\ReadinessItem;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Models\NodeResource;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Outline\OutlinePrompt;
use Modules\Curriculum\Outline\OutlineSheet;
use Modules\Curriculum\Outline\Templates;
use Modules\Curriculum\Outline\TextOutline;
use Modules\Curriculum\Queries\VersionReadiness;
use Modules\Curriculum\Texts\QuranText;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\CatalogIsland;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Games\ItemKind;

/**
 * The content of a program: levels, units and lessons in teaching order.
 * Add, edit, move and (while a draft) delete; then publish so enrollments
 * follow it. Each lesson opens its materials: files and links, for
 * everyone or for teachers only, added, renamed, moved or taken off at any
 * time (published or not), and its practice: the questions, orders and
 * pairs the learner's games play after the lesson (SaveNodeItems), with the
 * games those items are enough for, and how much of the program has practice.
 * A lesson's text (typed, or a passage of the Quran) is set with the lesson;
 * when the academy turned the helper on, "Suggest practice" puts suggested
 * items in the practice form, marked, to look over and save.
 *
 * Built quickly: a title typed under a level or unit and Enter adds it
 * (quickAdd, through SaveNode) with the field kept for the next one; rows are
 * dragged within their parent and lessons between the units of a level
 * (wire:sort, MoveNode::place), the arrows staying for the keyboard. A
 * readiness bar (Domain\PublishReadiness, the same reading as PublishVersion)
 * says what stops publishing and what is only worth a look, each item leading
 * to its node; "Preview" draws the levels as the journey's islands.
 *
 * @property Program $record
 */
final class EditCurriculum extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProgramResource::class;

    protected string $view = 'curriculum::filament.builder';

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows(Ability::ManageCurriculum->value);
    }

    /** The lesson whose materials are open under its row. */
    public ?string $openLesson = null;

    /** @var array<string, string> what is typed in each quick-add field, by its parent's id ("root" for a new level) */
    public array $quick = [];

    /** The outline being reviewed (Domain\Outline\OutlineRules rows), nothing saved yet. */
    public array $outline = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('curriculum::curriculum.builder.subtitle', [
            'subject' => app(CatalogApi::class)->subjectNames()[$this->record->subject_id] ?? '',
            'version' => $this->version()->version,
        ]).' · '.__('curriculum::curriculum.version_statuses.'.$this->version()->status);
    }

    public function version(): CurriculumVersion
    {
        return $this->record->workingVersion();
    }

    #[Computed]
    public function tree(): CurriculumTree
    {
        return app(CurriculumApi::class)->tree($this->version()->id);
    }

    /** @return array{0: PublishReadiness, 1: CurriculumTree} */
    #[Computed]
    public function readiness(): array
    {
        return app(VersionReadiness::class)->read($this->version());
    }

    public function sayReadiness(ReadinessItem $item): string
    {
        return VersionReadiness::say($item, $this->readiness[1]);
    }

    /** @return array<string, string> lesson id => the kind of its text (text, quran) */
    #[Computed]
    public function texts(): array
    {
        return Node::query()->where('version_id', $this->version()->id)->where('kind', 'lesson')->whereNotNull('text_source')
            ->get(['id', 'text_source'])->mapWithKeys(fn (Node $n) => [$n->id => (string) ($n->text_source['kind'] ?? '')])->filter()->all();
    }

    /**
     * The quick ways to start an empty curriculum (docs/architecture/02, section 6): an outline from the
     * helper, a pasted list or a sheet, a template, each shown for review before ApplyOutline saves it;
     * or a copy of another program (CopyProgram), saved at once.
     *
     * @return list<Action>
     */
    public function startWays(): array
    {
        if ($this->version()->status !== 'draft') {
            return [];
        }

        return [$this->startAiAction, $this->startListAction, $this->startTemplateAction, $this->startCopyAction];
    }

    /** A title typed in the review: one written the same in both languages changes in both. */
    public function updatingOutline(mixed $value, string $key): void
    {
        [$i, $field] = array_pad(explode('.', $key, 2), 2, null);
        $other = ['title_ar' => 'title_en', 'title_en' => 'title_ar'][$field] ?? null;
        if ($other !== null && isset($this->outline[$i]) && ($this->outline[$i][$other] ?? null) === ($this->outline[$i][$field] ?? null)) {
            $this->outline[$i][$other] = $value;
        }
    }

    /** Takes a row out of the review, with what is under it. */
    public function dropOutlineRow(int $index): void
    {
        foreach (OutlineRules::branch(array_values($this->outline), $index) as $i) {
            unset($this->outline[$i]);
        }
        $this->outline = array_values($this->outline);
    }

    /** Why the outline helper cannot run now, in the reader's words; null when it can. */
    public function aiOff(): ?string
    {
        try {
            app(AcademyAi::class)->require(AiFeature::CurriculumOutline);
        } catch (AiFailed $e) {
            return $e->reason === 'off' ? __('curriculum::outline.ai.off') : $e->sayable();
        }

        return null;
    }

    public function startAiAction(): Action
    {
        $o = 'curriculum::outline.';
        $surahs = app(QuranText::class)->available();
        $subject = (string) (app(CatalogApi::class)->subjectNames()[$this->record->subject_id] ?? '');

        return $this->startWay('startAi', 'ai', Heroicon::OutlinedSparkles)
            ->modalDescription(fn () => $this->aiOff() ?? __($o.'ai.description'))
            ->modalSubmitActionLabel(__($o.'ai.go'))
            ->modalSubmitAction(fn (Action $action) => $this->aiOff() === null ? $action : false)
            ->schema(fn () => $this->aiOff() !== null ? [] : [
                Textarea::make('goal')->label(__($o.'ai.fields.goal'))->helperText(__($o.'ai.fields.goal_hint'))
                    ->placeholder(__($o.'ai.fields.goal_placeholder'))->rows(3)->required()->maxLength(SuggestOutline::GOAL_MAX),
                Grid::make(2)->schema([
                    Select::make('age')->label(__($o.'ai.fields.age'))->native(false)->default('any')->required()
                        ->options(collect(OutlinePrompt::AGES)->mapWithKeys(fn ($a) => [$a => __($o.'ai.ages.'.$a)])->all()),
                    TextInput::make('lessons')->label(__($o.'ai.fields.lessons'))->numeric()->required()
                        ->minValue(1)->maxValue(OutlineRules::LESSONS_MAX)->default(24),
                ]),
                Toggle::make('quran')->label(__($o.'ai.fields.quran'))->helperText(__($o.'ai.fields.quran_hint'))
                    ->visible($surahs)->default($surahs && preg_match('/قرآن|قران|quran|qur.an|حفظ|تحفيظ/iu', $subject) === 1),
            ])
            ->action(function (array $data, Action $action) use ($o) {
                try {
                    $result = app(SuggestOutline::class)->handle($this->record, $data, (string) auth()->id());
                } catch (AiFailed $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();

                    return;
                }
                if ($result['dropped'] > 0) {
                    Notification::make()->warning()->title(trans_choice($o.'ai.dropped', $result['dropped'], ['count' => $result['dropped']]))->send();
                }
                $this->review($result['rows']);
            });
    }

    public function startListAction(): Action
    {
        $o = 'curriculum::outline.list.';

        return $this->startWay('startList', 'list', Heroicon::OutlinedClipboardDocumentList)
            ->modalDescription(__($o.'description'))
            ->modalWidth('2xl')
            ->schema([
                Textarea::make('text')->label(__($o.'text'))->rows(10)->placeholder(__($o.'text_placeholder'))
                    ->extraInputAttributes(['dir' => 'auto'])->maxLength(60000),
                FileUpload::make('sheet')->label(__($o.'sheet'))->helperText(__($o.'sheet_hint'))
                    ->storeFiles(false)->acceptedFileTypes(OutlineSheet::TYPES)->maxSize(2048),
                Actions::make([
                    Action::make('outlineSheet')->label(__($o.'template'))->icon(Heroicon::OutlinedArrowDownTray)->link()
                        ->action(fn () => response()->download(OutlineSheet::template(), 'curriculum-outline.xlsx')->deleteFileAfterSend()),
                ]),
            ])
            ->action(function (array $data, Action $action) use ($o) {
                $file = is_array($data['sheet'] ?? null) ? collect($data['sheet'])->first() : ($data['sheet'] ?? null);
                $text = trim((string) ($data['text'] ?? ''));
                if ($file === null && $text === '') {
                    Notification::make()->danger()->title(__($o.'one'))->send();
                    $action->halt();
                }
                if ($file !== null) {
                    $cells = OutlineSheet::read((string) $file->getRealPath(), (string) $file->getClientOriginalName());
                    if ($cells === null) {
                        Notification::make()->danger()->title(__($o.'unreadable'))->send();
                        $action->halt();
                    }
                    $rows = TextOutline::fromSheet((array) $cells, OutlineSheet::headers());
                } else {
                    $rows = TextOutline::parse($text, __($o.'first_level'));
                }
                $clean = OutlineRules::clean($rows);
                if ($clean['rows'] === []) {
                    Notification::make()->danger()->title(__($o.'nothing'))->send();
                    $action->halt();
                }
                $this->review($clean['rows']);
            });
    }

    public function startTemplateAction(): Action
    {
        $o = 'curriculum::outline.template.';
        $templates = app(Templates::class);

        return $this->startWay('startTemplate', 'template', Heroicon::OutlinedSquares2x2)
            ->modalDescription(__($o.'description'))
            ->schema([
                Radio::make('template')->label(__($o.'field'))->required()
                    ->options($templates->options())->descriptions($templates->hints()),
            ])
            ->action(fn (array $data) => $this->review(OutlineRules::clean($templates->rows((string) $data['template']))['rows']));
    }

    public function startCopyAction(): Action
    {
        $o = 'curriculum::outline.copy.';

        return $this->startWay('startCopy', 'copy', Heroicon::OutlinedDocumentDuplicate)
            ->modalDescription(__($o.'description'))
            ->modalSubmitActionLabel(__($o.'go'))
            ->schema([
                Select::make('program')->label(__($o.'field'))->required()->native(false)->searchable()
                    ->options(fn () => Program::query()->whereKeyNot($this->record->id)->orderBy('name_ar')->get()
                        ->mapWithKeys(fn (Program $p) => [$p->id => $p->name()])->all()),
            ])
            ->action(function (array $data) use ($o) {
                app(CopyProgram::class)->into((string) $data['program'], $this->record, (string) auth()->id());
                $this->forget();
                $counts = OutlineRules::counts(array_map(fn ($n) => ['kind' => $n->kind], $this->tree->nodes));
                Notification::make()->success()->title(__($o.'done', $counts))->send();
            });
    }

    /** One of the start ways, as a card on the empty screen. */
    private function startWay(string $name, string $way, Heroicon $icon): Action
    {
        $o = 'curriculum::outline.ways.'.$way.'.';

        return Action::make($name)
            ->label(__($o.'label'))
            ->icon($icon)
            ->color('gray')
            ->outlined()
            ->extraAttributes(['class' => 'cb-way', 'title' => __($o.'hint')])
            ->modalHeading(__('curriculum::outline.'.$way.'.heading'))
            ->visible(fn () => $this->version()->status === 'draft' && $this->tree->nodes === []);
    }

    /** Opens the review of an outline instead of the way that made it. */
    private function review(array $rows): void
    {
        $this->outline = array_values($rows);
        $this->replaceMountedAction('reviewOutline');
    }

    /**
     * The outline as an editable tree: titles to rename, rows to untick or take out; saved to the draft by
     * ApplyOutline, which refuses past the limits.
     */
    public function reviewOutlineAction(): Action
    {
        $o = 'curriculum::outline.review.';

        return Action::make('reviewOutline')
            ->modalHeading(__($o.'heading'))
            ->modalDescription(__($o.'description'))
            ->modalWidth('4xl')
            ->modalSubmitActionLabel(__($o.'save'))
            ->modalContent(fn () => view('curriculum::filament.outline-review', [
                'rows' => $this->outline,
                'counting' => OutlineRules::counting($this->outline),
                'counts' => OutlineRules::counts(OutlineRules::selected($this->outline)),
                'field' => app()->getLocale() === 'ar' ? 'title_ar' : 'title_en',
            ]))
            ->action(function () use ($o) {
                $counts = app(ApplyOutline::class)->handle($this->version(), $this->outline);
                $this->outline = [];
                $this->forget();
                Notification::make()->success()->title(__($o.'saved', $counts))->send();
            });
    }

    /** What a quick-add field under $parent adds: a level at the top, a unit in a level that has units, else a lesson. */
    public function quickKind(string $parent): string
    {
        if ($parent === 'root') {
            return 'level';
        }
        $node = $this->tree->nodes[$parent] ?? null;
        if ($node?->kind === 'level' && array_filter($this->tree->childrenOf($parent), fn ($n) => $n->kind === 'unit') !== []) {
            return 'unit';
        }

        return 'lesson';
    }

    /** Adds what was typed in a quick-add field (Enter) and empties the field for the next one. */
    public function quickAdd(string $parent): void
    {
        $title = trim((string) ($this->quick[$parent] ?? ''));
        if ($title === '') {
            return;
        }
        Validator::make(['title' => $title], ['title' => ['string', 'max:200']], [], ['title' => __('curriculum::curriculum.builder.quick.title')])->validate();
        $kind = $this->quickKind($parent);
        $this->saveNode(null, ['kind' => $kind, 'parent_id' => $parent === 'root' ? null : $this->node($parent)->id, 'title_ar' => $title, 'title_en' => $title]);
        $this->quick[$parent] = '';
    }

    /**
     * A row dropped by drag and drop (wire:sort): $position among the rows of the list it was dropped
     * in, whose parent is $parentId (the list's wire:sort:group-id; none for the levels).
     */
    public function sortNode(string $nodeId, int $position, ?string $parentId = null): void
    {
        app(MoveNode::class)->place($this->node($nodeId), $parentId === null ? null : $this->node($parentId)->id, $position);
        $this->forget();
    }

    /**
     * The student's view of the program, without any learner: each level an island of the world in
     * turn (as the journey draws them), its lessons the stations.
     *
     * @return list<array{title: string, art: ?CatalogIsland, stations: list<string>}>
     */
    public function previewIslands(): array
    {
        $islands = array_values(app(GameCatalog::class)->islands());
        $out = [];
        foreach ($this->tree->childrenOf(null) as $i => $level) {
            $out[] = [
                'title' => $level->title(),
                'art' => $islands === [] ? null : $islands[$i % count($islands)],
                'stations' => array_map(fn ($l) => $l->title(), $this->tree->lessons($level->id)),
            ];
        }

        return $out;
    }

    public function toggleMaterials(string $nodeId): void
    {
        $this->openLesson = $this->openLesson === $nodeId ? null : $this->node($nodeId)->id;
    }

    /** @return array<string, int> lesson id => how many materials it has */
    #[Computed]
    public function materialCounts(): array
    {
        return NodeResource::query()->whereIn('node_id', array_map(fn ($n) => $n->id, $this->tree->lessons()))
            ->selectRaw('node_id, count(*) as n')->groupBy('node_id')->pluck('n', 'node_id')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<string, int> lesson id => how many practice items it has */
    #[Computed]
    public function practiceCounts(): array
    {
        return NodeItem::query()->whereIn('node_id', array_map(fn ($n) => $n->id, $this->tree->lessons()))
            ->selectRaw('node_id, count(*) as n')->groupBy('node_id')->pluck('n', 'node_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * The games each lesson's practice is enough for (the platform's live games: the items of the kinds a game
     * plays reach its smallest round), so the academy sees its curriculum become a game.
     *
     * @return array<string, list<array{title: string, stages: string}>> lesson id => games
     */
    #[Computed]
    public function playsIn(): array
    {
        $kinds = [];
        foreach (NodeItem::query()->whereIn('node_id', array_map(fn ($n) => $n->id, $this->tree->lessons()))
            ->selectRaw('node_id, kind, count(*) as n')->groupBy('node_id', 'kind')->get() as $row) {
            $kinds[$row->node_id][$row->kind] = (int) $row->n;
        }
        $games = app(GameCatalog::class)->games();
        $out = [];
        foreach ($kinds as $lesson => $counts) {
            $out[$lesson] = array_values(array_map(fn (CatalogGame $g) => [
                'title' => $g->title(),
                'stages' => implode('، ', array_map(fn (string $s) => __('shared::games.stages.'.$s), $g->stages)),
            ], array_filter($games, fn (CatalogGame $g) => array_sum(array_map(fn (ItemKind $k) => $counts[$k->value] ?? 0, $g->kinds)) >= $g->minItems)));
        }

        return $out;
    }

    /** @return array{with: int, total: int, missing: list<string>} lessons with practice, of all, and the first without */
    public function practiceSummary(): array
    {
        $lessons = $this->tree->lessons();
        $without = array_values(array_filter($lessons, fn ($l) => ($this->practiceCounts[$l->id] ?? 0) === 0));

        return ['with' => count($lessons) - count($without), 'total' => count($lessons), 'missing' => array_map(fn ($l) => $l->title(), $without)];
    }

    /** @return list<NodeResource> */
    public function materials(string $nodeId): array
    {
        return NodeResource::query()->where('node_id', $nodeId)->orderBy('ordinal')->get()->all();
    }

    public function moveMaterial(string $resourceId, string $direction): void
    {
        app(MoveNodeResource::class)->handle($this->resource($resourceId), $direction === 'up' ? 'up' : 'down');
    }

    public function move(string $nodeId, string $direction): void
    {
        app(MoveNode::class)->handle($this->node($nodeId), $direction === 'up' ? 'up' : 'down');
        $this->forget();
    }

    /** Drops what this request read of the tree, so the screen shows it as it now is. */
    private function forget(): void
    {
        unset($this->tree, $this->readiness, $this->texts, $this->practiceCounts, $this->materialCounts, $this->playsIn);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('curriculum::curriculum.preview.action'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn () => $this->tree->nodes !== [])
                ->modalHeading(__('curriculum::curriculum.preview.title'))
                ->modalDescription(__('curriculum::curriculum.preview.hint'))
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('curriculum::curriculum.preview.close'))
                ->modalContent(fn () => view('curriculum::filament.preview', ['islands' => $this->previewIslands()])),
            // Closed with the reason while something stops it (the readiness bar lists it); else its
            // confirmation names what is only worth a look. PublishVersion checks the same again.
            Action::make('publish')
                ->label(__('curriculum::curriculum.builder.publish'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn () => $this->version()->status === 'draft')
                ->disabled(fn () => ! $this->readiness[0]->canPublish())
                ->tooltip(fn () => $this->readiness[0]->canPublish() ? null : __('curriculum::curriculum.readiness.closed', [
                    'reason' => $this->sayReadiness($this->readiness[0]->blockers()[0]),
                ]))
                ->requiresConfirmation()
                ->modalDescription(__('curriculum::curriculum.builder.publish_hint'))
                ->modalContent(fn () => $this->readiness[0]->warnings() === [] ? null : view('curriculum::filament.publish-warnings', [
                    'warnings' => array_map(fn (ReadinessItem $w) => $this->sayReadiness($w), $this->readiness[0]->warnings()),
                ]))
                ->action(function () {
                    app(PublishVersion::class)->handle($this->version(), (string) auth()->id());
                    $this->forget();
                    Notification::make()->success()->title(__('curriculum::curriculum.builder.published'))->send();
                }),
            Action::make('addLevel')
                ->label(__('curriculum::curriculum.builder.add_level'))
                ->icon(Heroicon::OutlinedPlus)
                ->color('gray')
                ->schema($this->nodeSchema('level'))
                ->action(fn (array $data) => $this->saveNode(null, ['kind' => 'level'] + $data)),
        ];
    }

    public function addChildAction(): Action
    {
        // With `details`, the quick-add field's full form: what was typed there comes along.
        return Action::make('addChild')
            ->label(fn (array $arguments) => __('curriculum::curriculum.builder.'.(($arguments['details'] ?? false) ? 'quick.details' : 'add_'.($arguments['kind'] ?? 'lesson'))))
            ->icon(fn (array $arguments) => ($arguments['details'] ?? false) ? Heroicon::OutlinedAdjustmentsHorizontal : Heroicon::OutlinedPlus)
            ->link()->size('sm')
            ->modalHeading(fn (array $arguments) => __('curriculum::curriculum.builder.add_'.($arguments['kind'] ?? 'lesson')))
            ->fillForm(fn (array $arguments) => array_fill_keys(['title_ar', 'title_en'], trim((string) ($this->quick[$arguments['node'] ?? 'root'] ?? ''))))
            ->schema(fn (array $arguments) => $this->nodeSchema($arguments['kind'] ?? 'lesson'))
            ->action(function (array $data, array $arguments) {
                $this->saveNode(null, ['kind' => $arguments['kind'] ?? 'lesson', 'parent_id' => $arguments['node'] ?? null] + $data);
                $this->quick[$arguments['node'] ?? 'root'] = '';
            });
    }

    public function editNodeAction(): Action
    {
        return Action::make('editNode')
            ->label(__('curriculum::curriculum.builder.edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => __('curriculum::curriculum.kinds.'.$this->node($arguments['node'])->kind))
            ->fillForm(fn (array $arguments) => $this->nodeForm($this->node($arguments['node'])))
            ->schema(fn (array $arguments) => $this->nodeSchema($this->node($arguments['node'])->kind))
            ->action(fn (array $data, array $arguments) => $this->saveNode($this->node($arguments['node']), $data));
    }

    public function deleteNodeAction(): Action
    {
        return Action::make('deleteNode')
            ->label(__('curriculum::curriculum.builder.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()->color('danger')->size('sm')
            ->visible(fn () => $this->version()->status === 'draft')
            ->requiresConfirmation()
            ->modalDescription(__('curriculum::curriculum.builder.delete_confirm'))
            ->action(function (array $arguments) {
                app(DeleteNode::class)->handle($this->node($arguments['node']));
                $this->forget();
            });
    }

    public function addMaterialAction(): Action
    {
        $l = 'curriculum::curriculum.resources.';

        return Action::make('addMaterial')
            ->label(fn (array $arguments) => __($l.(($arguments['kind'] ?? 'file') === 'link' ? 'add_link' : 'add_file')))
            ->icon(fn (array $arguments) => ($arguments['kind'] ?? 'file') === 'link' ? Heroicon::OutlinedLink : Heroicon::OutlinedArrowUpTray)
            ->link()->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'title', ['lesson' => $this->node($arguments['node'])->toData()->title()]))
            ->modalDescription(__($l.'hint'))
            ->schema(fn (array $arguments) => $this->materialSchema($arguments['kind'] ?? 'file', true))
            ->action(function (array $data, array $arguments, Action $action) use ($l) {
                $file = is_array($data['file'] ?? null) ? collect($data['file'])->first() : ($data['file'] ?? null);
                $this->attempt($action, fn () => app(AddNodeResource::class)->handle(
                    $this->node($arguments['node']), ['kind' => $arguments['kind'] ?? 'file'] + $data, $file, (string) auth()->id(),
                ), __($l.'added'));
                $this->openLesson = $arguments['node'];
                unset($this->materialCounts);
            });
    }

    public function editMaterialAction(): Action
    {
        $l = 'curriculum::curriculum.resources.';

        return Action::make('editMaterial')
            ->label(__($l.'edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => $this->resource($arguments['resource'])->toData()->title())
            ->fillForm(fn (array $arguments) => $this->resource($arguments['resource'])->only(['title_ar', 'title_en', 'audience', 'url']))
            ->schema(fn (array $arguments) => $this->materialSchema($this->resource($arguments['resource'])->kind, false))
            ->action(fn (array $data, array $arguments, Action $action) => $this->attempt(
                $action, fn () => app(UpdateNodeResource::class)->handle($this->resource($arguments['resource']), $data), __($l.'saved'),
            ));
    }

    public function removeMaterialAction(): Action
    {
        $l = 'curriculum::curriculum.resources.';

        return Action::make('removeMaterial')
            ->label(__($l.'remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()->color('danger')->size('sm')
            ->requiresConfirmation()
            ->modalDescription(__($l.'remove_confirm'))
            ->action(function (array $arguments) use ($l) {
                app(RemoveNodeResource::class)->handle($this->resource($arguments['resource']));
                unset($this->materialCounts);
                Notification::make()->success()->title(__($l.'removed'))->send();
            });
    }

    /**
     * A lesson's practice, edited as one list: each item a question with
     * choices, parts in their order, or a pair (what the games play).
     */
    public function practiceAction(): Action
    {
        $l = 'curriculum::curriculum.practice.';

        return Action::make('practice')
            ->label(fn (array $arguments) => trans_choice($l.'count', $this->practiceCounts[$arguments['node']] ?? 0, ['count' => $this->practiceCounts[$arguments['node']] ?? 0]))
            ->icon(Heroicon::OutlinedPuzzlePiece)
            ->link()->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'title', ['lesson' => $this->node($arguments['node'])->toData()->title()]))
            ->modalDescription(__($l.'hint'))
            ->modalWidth('4xl')
            ->modalSubmitActionLabel(__($l.'save'))
            ->fillForm(fn (array $arguments) => ['items' => [
                ...NodeItem::query()->where('node_id', $arguments['node'])->orderBy('ordinal')->get()
                    ->map(fn (NodeItem $i) => ['id' => $i->id, 'kind' => $i->kind, 'prompt' => $i->prompt] + $i->data)->all(),
                // What "Suggest practice" brought, marked until it is saved.
                ...array_map(fn (array $item) => $item + ['suggested' => true], (array) ($arguments['suggested'] ?? [])),
            ]])
            ->schema([
                Repeater::make('items')->hiddenLabel()
                    ->addActionLabel(__($l.'add'))
                    ->maxItems(SaveNodeItems::MAX)
                    ->reorderable()->collapsible()->cloneable()
                    ->defaultItems(0)
                    ->itemLabel(fn (array $state) => self::itemLabel($state))
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('suggested'),
                        Grid::make(3)->schema([
                            Select::make('kind')->label(__($l.'fields.kind'))->required()->native(false)->live()->default('choice')
                                ->options(collect(ItemKind::cases())->mapWithKeys(fn (ItemKind $k) => [$k->value => $k->label()])->all()),
                            // An item reads the way its own words read (an English lesson in an Arabic builder), as the games show it.
                            TextInput::make('prompt')->label(__($l.'fields.prompt'))->maxLength(ItemKind::PROMPT_MAX)->columnSpan(2)
                                ->extraInputAttributes(['dir' => 'auto'])
                                ->placeholder(fn (Get $get) => __($l.'placeholders.prompt_'.($get('kind') ?? 'choice')))
                                ->required(fn (Get $get) => $get('kind') === 'choice')
                                ->visible(fn (Get $get) => $get('kind') !== 'pair'),
                        ]),
                        TagsInput::make('options')->label(__($l.'fields.options'))->helperText(__($l.'hints.options'))
                            ->placeholder(__($l.'placeholders.option'))->live()->reorderable()
                            ->visible(fn (Get $get) => $get('kind') === 'choice')->required(fn (Get $get) => $get('kind') === 'choice'),
                        Select::make('answer')->label(__($l.'fields.answer'))->native(false)
                            ->options(fn (Get $get) => array_values((array) ($get('options') ?? [])))
                            ->visible(fn (Get $get) => $get('kind') === 'choice')->required(fn (Get $get) => $get('kind') === 'choice'),
                        TagsInput::make('parts')->label(__($l.'fields.parts'))->helperText(__($l.'hints.parts'))
                            ->placeholder(__($l.'placeholders.part'))->reorderable()
                            ->visible(fn (Get $get) => $get('kind') === 'order')->required(fn (Get $get) => $get('kind') === 'order'),
                        Grid::make(2)->schema([
                            TextInput::make('left')->label(__($l.'fields.left'))->maxLength(ItemKind::SIDE_MAX)->required(fn (Get $get) => $get('kind') === 'pair')
                                ->extraInputAttributes(['dir' => 'auto']),
                            TextInput::make('right')->label(__($l.'fields.right'))->maxLength(ItemKind::SIDE_MAX)->required(fn (Get $get) => $get('kind') === 'pair')
                                ->extraInputAttributes(['dir' => 'auto']),
                        ])->visible(fn (Get $get) => $get('kind') === 'pair'),
                    ]),
            ])
            ->action(function (array $data, array $arguments, Action $action) use ($l) {
                $this->attempt($action, fn () => app(SaveNodeItems::class)->handle(
                    $this->node($arguments['node']), array_values($data['items'] ?? []), (string) auth()->id(),
                ), __($l.'saved'));
                $this->forget();
            });
    }

    /** @param  array<string, mixed>  $state */
    private static function itemLabel(array $state): string
    {
        $kind = ItemKind::tryFrom((string) ($state['kind'] ?? ''));
        $text = match ($kind) {
            ItemKind::Choice, ItemKind::Order => (string) ($state['prompt'] ?? '') ?: implode(' · ', (array) ($state['parts'] ?? [])),
            ItemKind::Pair => trim(($state['left'] ?? '').' ↔ '.($state['right'] ?? '')),
            default => '',
        };

        $label = trim(($kind?->label() ?? '').($text !== '' ? ' · '.mb_strimwidth($text, 0, 70, '…') : ''));

        return ($state['suggested'] ?? false) ? __('curriculum::curriculum.practice.suggest.badge').' · '.$label : $label;
    }

    /**
     * Suggests practice for a lesson (SuggestNodeItems) and opens its practice form with the
     * suggestions under the lesson's items, to look over and save; says why when it cannot.
     */
    public function suggestAction(): Action
    {
        $l = 'curriculum::curriculum.practice.suggest.';

        return Action::make('suggest')
            ->label(__($l.'action'))
            ->icon(Heroicon::OutlinedSparkles)
            ->link()->size('sm')
            ->visible(fn () => app(AcademyAi::class)->on(AiFeature::PracticeItems))
            ->action(function (array $arguments) use ($l) {
                try {
                    $result = app(SuggestNodeItems::class)->handle($this->node($arguments['node']), (string) auth()->id());
                } catch (AiFailed $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

                    return;
                }
                $count = count($result['items']);
                Notification::make()->{$count > 0 ? 'success' : 'warning'}()
                    ->title(trans_choice($l.'done', $count, ['count' => $count]))
                    ->body($result['dropped'] > 0 ? trans_choice($l.'dropped', $result['dropped'], ['count' => $result['dropped']]) : null)
                    ->send();
                if ($count > 0) {
                    $this->replaceMountedAction('practice', ['node' => $arguments['node'], 'suggested' => $result['items']]);
                }
            });
    }

    /** @return list<Component|Field> */
    private function materialSchema(string $kind, bool $adding): array
    {
        $l = 'curriculum::curriculum.resources.';
        $types = collect(AddNodeResource::FILES)->flatMap(fn (array $t) => $t['mimes'])->values()->all();

        return array_values(array_filter([
            $kind === 'file' && $adding
                ? FileUpload::make('file')->label(__($l.'fields.file'))->helperText(__($l.'fields.file_hint'))->required()
                    ->storeFiles(false)->acceptedFileTypes($types)->maxSize(collect(AddNodeResource::FILES)->max('max_kb'))
                : null,
            $kind === 'link'
                ? TextInput::make('url')->label(__($l.'fields.url'))->helperText(__($l.'fields.url_hint'))->required()->url()->maxLength(2000)
                : null,
            Grid::make(2)->schema([
                TextInput::make('title_ar')->label(__($l.'fields.title_ar'))->maxLength(160),
                TextInput::make('title_en')->label(__($l.'fields.title_en'))->maxLength(160),
            ]),
            Radio::make('audience')->label(__($l.'fields.audience'))->inline()->default('all')
                ->options(collect(NodeResource::AUDIENCES)->mapWithKeys(fn ($a) => [$a => __($l.'audiences.'.$a)])->all()),
        ]));
    }

    /** Runs a materials Action; its refusal shows as a notice and keeps the modal open. */
    private function attempt(Action $action, callable $run, string $done): void
    {
        try {
            $run();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            $action->halt();
        }
        Notification::make()->success()->title($done)->send();
    }

    private function resource(string $id): NodeResource
    {
        return NodeResource::query()->whereIn('node_id', Node::query()->where('version_id', $this->version()->id)->select('id'))->findOrFail($id);
    }

    /** @return list<Component|Field> */
    private function nodeSchema(string $kind): array
    {
        $fields = [
            Grid::make(2)->schema([
                TextInput::make('title_ar')->label(__('curriculum::curriculum.fields.title_ar'))->required()->maxLength(200),
                TextInput::make('title_en')->label(__('curriculum::curriculum.fields.title_en'))->required()->maxLength(200),
            ]),
        ];
        if ($kind !== 'lesson') {
            return $fields;
        }

        return [...$fields,
            Textarea::make('objectives')->label(__('curriculum::curriculum.fields.objectives'))->rows(3)->helperText(__('curriculum::curriculum.hints.objectives')),
            Grid::make(3)->schema([
                TextInput::make('weight')->label(__('curriculum::curriculum.fields.weight'))->numeric()->minValue(1)->maxValue(100)->default(1)
                    ->helperText(__('curriculum::curriculum.hints.weight')),
                TextInput::make('est_sessions')->label(__('curriculum::curriculum.fields.est_sessions'))->numeric()->minValue(1)->maxValue(50)->default(1)
                    ->helperText(__('curriculum::curriculum.hints.est_sessions')),
                Select::make('mastery_rule')->label(__('curriculum::curriculum.fields.mastery_rule'))->native(false)->default('mastered_once')
                    ->options(array_combine(Node::MASTERY_RULES, array_map(fn ($r) => __('curriculum::curriculum.mastery_rules.'.$r), Node::MASTERY_RULES))),
            ]),
            $this->textSection(),
        ];
    }

    /** A lesson's text: none, typed, or a passage of the Quran by surah and verses (offered once the platform has its copy). */
    private function textSection(): Section
    {
        $t = 'curriculum::curriculum.text.';
        $surahs = app(CurriculumApi::class)->surahs();
        $kinds = collect(['none', 'text', 'quran'])->mapWithKeys(fn ($k) => [$k => __($t.'kinds.'.$k)])->all();

        return Section::make(__($t.'section'))->description(__($t.'hint'))->compact()->schema([
            Radio::make('text_kind')->label(__($t.'kind'))->inline()->default('none')->live()
                ->options($kinds)
                ->disableOptionWhen(fn (string $value) => $value === 'quran' && $surahs === [])
                ->helperText($surahs === [] ? __($t.'quran_missing') : null),
            Textarea::make('text')->label(__($t.'text'))->rows(5)->maxLength(TextSource::TEXT_MAX)
                ->helperText(__($t.'text_hint', ['max' => TextSource::TEXT_MAX]))
                ->extraInputAttributes(['dir' => 'auto'])
                ->visible(fn (Get $get) => $get('text_kind') === 'text')->required(fn (Get $get) => $get('text_kind') === 'text'),
            Grid::make(3)->schema([
                Select::make('surah')->label(__($t.'surah'))->native(false)->searchable()->live()
                    ->options(collect($surahs)->mapWithKeys(fn (array $s) => [$s['number'] => $s['number'].'. '.$s['name_ar'].' · '.$s['name_en']])->all())
                    ->required(fn (Get $get) => $get('text_kind') === 'quran'),
                TextInput::make('from')->label(__($t.'from'))->numeric()->minValue(1)->default(1)
                    ->required(fn (Get $get) => $get('text_kind') === 'quran'),
                TextInput::make('to')->label(__($t.'to'))->numeric()->minValue(1)
                    ->maxValue(fn (Get $get) => collect($surahs)->firstWhere('number', (int) $get('surah'))['verses'] ?? null)
                    ->required(fn (Get $get) => $get('text_kind') === 'quran'),
            ])->visible(fn (Get $get) => $get('text_kind') === 'quran'),
            Text::make(__($t.'quran_hint'))->visible(fn (Get $get) => $get('text_kind') === 'quran'),
        ]);
    }

    /** @return array<string, mixed> a node as its form shows it */
    private function nodeForm(Node $node): array
    {
        $text = TextSource::fromArray($node->text_source);

        return $node->only(['title_ar', 'title_en', 'objectives', 'weight', 'est_sessions', 'mastery_rule']) + [
            'text_kind' => $text?->kind ?? 'none',
            'text' => $text?->text,
            'surah' => $text?->surah,
            'from' => $text?->from,
            'to' => $text?->to,
        ];
    }

    private function saveNode(?Node $node, array $data): void
    {
        if (array_key_exists('text_kind', $data)) {
            $data['text_source'] = match ($data['text_kind']) {
                'text' => ['kind' => 'text', 'text' => $data['text'] ?? ''],
                'quran' => ['kind' => 'quran', 'surah' => $data['surah'] ?? null, 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null],
                default => null,
            };
        }
        unset($data['text_kind'], $data['text'], $data['surah'], $data['from'], $data['to']);
        app(SaveNode::class)->handle($this->version(), $node, $data);
        $this->forget();
    }

    private function node(string $id): Node
    {
        return Node::query()->where('version_id', $this->version()->id)->findOrFail($id);
    }
}
