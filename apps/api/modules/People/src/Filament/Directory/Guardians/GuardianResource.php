<?php

namespace Modules\People\Filament\Directory\Guardians;

use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/** Guardians (docs/architecture/01, section 6): who signs in for their children and pays; their children on their page. */
final class GuardianResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'people/guardians';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageStudents->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('people::directory.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('people::directory.guardians.nav');
    }

    public static function getModelLabel(): string
    {
        return __('people::directory.guardians.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('people::directory.guardians.nav');
    }

    public static function getEloquentQuery(): Builder
    {
        return Person::query()->withRole('guardian');
    }

    public static function form(Schema $schema): Schema
    {
        $l = 'people::directory.fields.';

        return $schema->columns(1)->components([
            Section::make(__('people::directory.sections.contact'))->columns(2)->schema(DirectoryForm::contact()),
            Section::make(__('people::directory.sections.welcome'))->visibleOn('create')->schema([
                Toggle::make('welcome')->label(__($l.'welcome'))->helperText(__($l.'welcome_hint'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $c = 'people::directory.columns.';

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'children_names' => DB::table('guardian_student as gs')
                    ->join('users as s', fn ($j) => $j->on('s.academy_id', '=', 'gs.academy_id')->on('s.id', '=', 'gs.student_id'))
                    ->whereColumn('gs.guardian_id', 'users.id')->selectRaw("string_agg(s.name, '|' order by s.name)"),
            ]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__($c.'name'))->weight('semibold')->sortable()
                    ->searchable(['name', 'email', 'phone_e164'])->description(fn (Person $p) => $p->contact()),
                TextColumn::make('children_names')->label(__($c.'children'))->badge()->color('gray')->separator('|'),
                TextColumn::make('status')->label(__($c.'status'))->badge()
                    ->state(fn (Person $p) => __('people::directory.statuses.'.DirectoryForm::status($p)))
                    ->color(fn (Person $p) => match (DirectoryForm::status($p)) {
                        'disabled' => 'danger',
                        'invited' => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                TernaryFilter::make('active')->label(__('people::directory.filters.status'))
                    ->queries(true: fn (Builder $query) => $query->where('status', 'active'), false: fn (Builder $query) => $query->where('status', '!=', 'active')),
            ])
            ->recordUrl(fn (Person $p) => self::getUrl('edit', ['record' => $p]))
            ->emptyStateHeading(__('people::directory.guardians.empty'))
            ->emptyStateDescription(__('people::directory.guardians.empty_hint'))
            ->emptyStateIcon(Heroicon::OutlinedHome);
    }

    public static function getRelations(): array
    {
        return [ChildrenRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuardians::route('/'),
            'create' => CreateGuardian::route('/create'),
            'edit' => EditGuardian::route('/{record}/edit'),
        ];
    }
}
