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
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\DeleteNode;
use Modules\Curriculum\Actions\MoveNode;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\Resources\AddNodeResource;
use Modules\Curriculum\Actions\Resources\MoveNodeResource;
use Modules\Curriculum\Actions\Resources\RemoveNodeResource;
use Modules\Curriculum\Actions\Resources\UpdateNodeResource;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Models\NodeResource;
use Modules\Curriculum\Models\Program;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Games\CatalogGame;
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
        unset($this->tree);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(__('curriculum::curriculum.builder.publish'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn () => $this->version()->status === 'draft')
                ->requiresConfirmation()
                ->modalDescription(__('curriculum::curriculum.builder.publish_hint'))
                ->action(function () {
                    app(PublishVersion::class)->handle($this->version(), (string) auth()->id());
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
        return Action::make('addChild')
            ->label(fn (array $arguments) => __('curriculum::curriculum.builder.add_'.($arguments['kind'] ?? 'lesson')))
            ->icon(Heroicon::OutlinedPlus)
            ->link()->size('sm')
            ->modalHeading(fn (array $arguments) => __('curriculum::curriculum.builder.add_'.($arguments['kind'] ?? 'lesson')))
            ->schema(fn (array $arguments) => $this->nodeSchema($arguments['kind'] ?? 'lesson'))
            ->action(fn (array $data, array $arguments) => $this->saveNode(null, ['kind' => $arguments['kind'] ?? 'lesson', 'parent_id' => $arguments['node']] + $data));
    }

    public function editNodeAction(): Action
    {
        return Action::make('editNode')
            ->label(__('curriculum::curriculum.builder.edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => __('curriculum::curriculum.kinds.'.$this->node($arguments['node'])->kind))
            ->fillForm(fn (array $arguments) => $this->node($arguments['node'])->only(['title_ar', 'title_en', 'objectives', 'weight', 'est_sessions', 'mastery_rule']))
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
                unset($this->tree);
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
            ->fillForm(fn (array $arguments) => ['items' => NodeItem::query()->where('node_id', $arguments['node'])->orderBy('ordinal')->get()
                ->map(fn (NodeItem $i) => ['id' => $i->id, 'kind' => $i->kind, 'prompt' => $i->prompt] + $i->data)->all()])
            ->schema([
                Repeater::make('items')->hiddenLabel()
                    ->addActionLabel(__($l.'add'))
                    ->maxItems(SaveNodeItems::MAX)
                    ->reorderable()->collapsible()->cloneable()
                    ->defaultItems(0)
                    ->itemLabel(fn (array $state) => self::itemLabel($state))
                    ->schema([
                        Hidden::make('id'),
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
                unset($this->practiceCounts, $this->playsIn);
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

        return trim(($kind?->label() ?? '').($text !== '' ? ' · '.mb_strimwidth($text, 0, 70, '…') : ''));
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
        ];
    }

    private function saveNode(?Node $node, array $data): void
    {
        app(SaveNode::class)->handle($this->version(), $node, $data);
        unset($this->tree);
    }

    private function node(string $id): Node
    {
        return Node::query()->where('version_id', $this->version()->id)->findOrFail($id);
    }
}
