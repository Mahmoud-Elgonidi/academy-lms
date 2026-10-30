<?php

namespace Modules\Curriculum\Filament\Programs;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\Outline\CopyProgram;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Models\Program;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Programs per subject (docs/architecture/02, section 6). The content of
 * each, levels → units → lessons, is built on its own page.
 */
final class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'curricula';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageCurriculum->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('curriculum::curriculum.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('curriculum::curriculum.nav');
    }

    public static function getModelLabel(): string
    {
        return __('curriculum::curriculum.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('curriculum::curriculum.models');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('subject_id')->label(__('curriculum::curriculum.fields.subject'))
                ->options(fn () => app(CatalogApi::class)->subjectNames())->required()->native(false),
            Grid::make(2)->schema([
                TextInput::make('name_ar')->label(__('curriculum::curriculum.fields.name_ar'))->required()->maxLength(120),
                TextInput::make('name_en')->label(__('curriculum::curriculum.fields.name_en'))->required()->maxLength(120),
                Textarea::make('description_ar')->label(__('curriculum::curriculum.fields.description_ar'))->rows(2),
                Textarea::make('description_en')->label(__('curriculum::curriculum.fields.description_en'))->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $subjects = fn () => app(CatalogApi::class)->subjectNames();

        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->addSelect(['lessons' => DB::table('curriculum_nodes as n')->join('curriculum_versions as v', fn ($j) => $j->on('v.academy_id', '=', 'n.academy_id')->on('v.id', '=', 'n.version_id'))
                    ->whereColumn('v.program_id', 'programs.id')->whereIn('v.status', ['published', 'draft'])->where('n.kind', 'lesson')
                    ->selectRaw('count(*)')])
                ->addSelect(['students' => DB::table('enrollments')->whereColumn('program_id', 'programs.id')->where('status', '!=', 'ended')->selectRaw('count(*)')])
                ->addSelect(['version_status' => DB::table('curriculum_versions')->whereColumn('program_id', 'programs.id')->whereIn('status', ['published', 'draft'])
                    ->orderByRaw("case when status = 'published' then 0 else 1 end")->limit(1)->select('status')]))
            ->defaultSort('name_ar')
            ->columns([
                TextColumn::make('name')->label(__('curriculum::curriculum.fields.name'))
                    ->state(fn (Program $p) => $p->name())
                    ->description(fn (Program $p) => $subjects()[$p->subject_id] ?? null)
                    ->weight('semibold')
                    ->url(fn (Program $p) => EditCurriculum::getUrl(['record' => $p])),
                TextColumn::make('version_status')->label(__('curriculum::curriculum.fields.version'))->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? __('curriculum::curriculum.version_statuses.'.$state) : '—')
                    ->color(fn (?string $state) => $state === 'published' ? 'success' : 'warning'),
                TextColumn::make('lessons')->label(__('curriculum::curriculum.fields.lessons'))->numeric(),
                TextColumn::make('students')->label(__('curriculum::curriculum.fields.students'))->numeric(),
            ])
            ->recordActions([
                Action::make('content')->label(__('curriculum::curriculum.open_builder'))->icon(Heroicon::OutlinedListBullet)
                    ->url(fn (Program $p) => EditCurriculum::getUrl(['record' => $p])),
                EditAction::make()->using(fn (Model $record, array $data) => app(SaveProgram::class)->handle($record, $data)),
                // A new program with a draft copied from this one (CopyProgram): the same files, never a second copy of them.
                Action::make('copy')->label(__('curriculum::outline.copy.action'))->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
                    ->modalHeading(__('curriculum::outline.copy.heading'))
                    ->modalDescription(__('curriculum::outline.copy.description'))
                    ->fillForm(fn (Program $p) => [
                        'name_ar' => __('curriculum::outline.copy.name_ar', ['name' => $p->name_ar]),
                        'name_en' => __('curriculum::outline.copy.name_en', ['name' => $p->name_en]),
                    ])
                    ->schema([Grid::make(2)->schema([
                        TextInput::make('name_ar')->label(__('curriculum::curriculum.fields.name_ar'))->required()->maxLength(120),
                        TextInput::make('name_en')->label(__('curriculum::curriculum.fields.name_en'))->required()->maxLength(120),
                    ])])
                    ->action(function (Program $p, array $data) {
                        $copy = app(CopyProgram::class)->handle($p->id, $data, (string) auth()->id());
                        Notification::make()->success()->title(__('curriculum::outline.copy.copied'))->send();

                        return redirect(EditCurriculum::getUrl(['record' => $copy]));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePrograms::route('/'),
            'content' => EditCurriculum::route('/{record}/content'),
        ];
    }
}
