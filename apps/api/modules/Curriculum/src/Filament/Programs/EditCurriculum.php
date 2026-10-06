<?php

namespace Modules\Curriculum\Filament\Programs;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\DeleteNode;
use Modules\Curriculum\Actions\MoveNode;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\Program;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The content of a program: levels, units and lessons in teaching order.
 * Add, edit, move and (while a draft) delete; then publish so enrollments
 * follow it.
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
