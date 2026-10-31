<?php

namespace Modules\People\Filament\Directory\Teachers;

use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Teachers (docs/architecture/01, section 5): who they are and how to reach
 * them, the ages they teach, their caps, and each subject with its levels
 * and categories; what "Find a teacher" searches by.
 */
final class TeacherResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'people/teachers';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageTeachers->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('people::directory.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('people::directory.teachers.nav');
    }

    public static function getModelLabel(): string
    {
        return __('people::directory.teachers.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('people::directory.teachers.nav');
    }

    public static function getEloquentQuery(): Builder
    {
        return Person::query()->withRole('teacher');
    }

    public static function form(Schema $schema): Schema
    {
        $l = 'people::directory.fields.';
        $o = 'people::directory.options.';
        $catalog = app(CatalogApi::class);

        return $schema->columns(1)->components([
            Section::make(__('people::directory.sections.contact'))->columns(2)->schema(DirectoryForm::contact()),
            Section::make(__('people::directory.sections.teaching'))->columns(2)->schema([
                ToggleButtons::make('gender')->label(__($l.'gender'))->inline()->required()->options(__($o.'genders')),
                CheckboxList::make('languages')->label(__($l.'languages'))->columns(3)->required()->default(['ar'])->options(__($o.'languages')),
                TextInput::make('dialect')->label(__($l.'dialect'))->helperText(__($l.'dialect_hint'))->maxLength(40),
                CheckboxList::make('age_groups')->label(__($l.'age_groups'))->helperText(__($l.'age_groups_hint'))->columns(2)->options(AgeGroup::options()),
                Toggle::make('accepts_new')->label(__($l.'accepts_new'))->default(true),
                Toggle::make('accepts_trials')->label(__($l.'accepts_trials'))->default(true),
                // Group lessons beside the private ones: the switch, then how many one lesson may hold.
                Toggle::make('accepts_groups')->label(__($l.'accepts_groups'))->helperText(__($l.'accepts_groups_hint'))
                    ->onIcon(Heroicon::UserGroup)->onColor('info')->default(false)->live()->columnSpanFull(),
                Slider::make('max_group_size')->label(__($l.'max_group_size'))
                    ->range(2, 20)->step(1)->default(6)->tooltips()->pips(PipsMode::Values)->pipsValues([2, 5, 10, 15, 20])
                    ->hint(fn (Get $get) => trans_choice($l.'group_up_to', (int) ($get('max_group_size') ?? 6), ['n' => (int) ($get('max_group_size') ?? 6)]))
                    ->hintIcon(Heroicon::UserGroup)->hintColor('info')
                    ->helperText(__($l.'max_group_hint'))
                    ->visible(fn (Get $get) => (bool) $get('accepts_groups'))->required(fn (Get $get) => (bool) $get('accepts_groups'))
                    ->live()->columnSpanFull(),
                Text::make(__($l.'groups_note'))->color('gray')->visible(fn (Get $get) => (bool) $get('accepts_groups'))->columnSpanFull(),
                TextInput::make('max_weekly_hours')->label(__($l.'max_weekly_hours'))->numeric()->minValue(0.5)->maxValue(100)->step(0.5),
                TextInput::make('max_daily_lessons')->label(__($l.'max_daily_lessons'))->helperText(__($l.'max_daily_hint'))->numeric()->integer()->minValue(1)->maxValue(30),
            ]),
            Section::make(__('people::directory.sections.subjects'))->schema([
                Repeater::make('subjects')->hiddenLabel()->minItems(1)->defaultItems(1)->columns(4)
                    ->addActionLabel(__($l.'add_subject'))
                    ->schema([
                        Select::make('subject_id')->label(__($l.'subject'))->required()->live()->distinct()
                            ->options(fn () => $catalog->subjectNames())
                            ->afterStateUpdated(function (Set $set) {
                                $set('level_from_id', null);
                                $set('level_to_id', null);
                                $set('category_ids', []);
                            }),
                        Select::make('level_from_id')->label(__($l.'level_from'))->placeholder(__($l.'any_level'))
                            ->options(fn (Get $get) => $get('subject_id') ? $catalog->levels($get('subject_id')) : []),
                        Select::make('level_to_id')->label(__($l.'level_to'))->placeholder(__($l.'any_level'))
                            ->options(fn (Get $get) => $get('subject_id') ? $catalog->levels($get('subject_id')) : []),
                        Select::make('category_ids')->label(__($l.'categories'))->helperText(__($l.'categories_hint'))->multiple()
                            ->options(fn (Get $get) => $get('subject_id') ? $catalog->categories($get('subject_id')) : []),
                    ]),
            ]),
            Section::make(__('people::directory.sections.welcome'))->visibleOn('create')->schema([
                Toggle::make('welcome')->label(__($l.'welcome'))->helperText(__($l.'welcome_hint'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $c = 'people::directory.columns.';
        $name = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('teacherProfile')->addSelect([
                'subject_names' => DB::table('teacher_subjects as ts')
                    ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'ts.academy_id')->on('s.id', '=', 'ts.subject_id'))
                    ->whereColumn('ts.teacher_id', 'users.id')->selectRaw("string_agg(s.{$name}, '|' order by s.{$name})"),
            ]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__($c.'name'))->weight('semibold')->sortable()
                    ->searchable(['name', 'email', 'phone_e164'])->description(fn (Person $p) => $p->contact()),
                TextColumn::make('subject_names')->label(__($c.'subjects'))->badge()->separator('|'),
                TextColumn::make('gender')->label(__($c.'gender'))->visibleFrom('md')
                    ->state(fn (Person $p) => $p->teacherProfile ? __('people::directory.options.genders.'.$p->teacherProfile->gender) : null),
                TextColumn::make('age_groups')->label(__($c.'age_groups'))->visibleFrom('md')->badge()->color('gray')
                    ->state(fn (Person $p) => array_map(fn (string $g) => AgeGroup::from($g)->label(), $p->teacherProfile->age_groups ?? [])),
                TextColumn::make('weekly')->label(__($c.'weekly'))->visibleFrom('md')
                    ->state(fn (Person $p) => $p->teacherProfile?->max_weekly_min ? __('people::directory.hours', ['hours' => round($p->teacherProfile->max_weekly_min / 60, 1)]) : null),
                IconColumn::make('accepts_new')->label(__($c.'accepts_new'))->visibleFrom('md')->boolean()->state(fn (Person $p) => (bool) $p->teacherProfile?->accepts_new),
                IconColumn::make('accepts_groups')->label(__($c.'groups'))->visibleFrom('md')
                    ->state(fn (Person $p) => (bool) $p->teacherProfile?->accepts_groups)
                    ->icon(fn (bool $state) => $state ? Heroicon::OutlinedUserGroup : Heroicon::OutlinedMinus)
                    ->color(fn (bool $state) => $state ? 'info' : 'gray')
                    ->tooltip(fn (Person $p) => $p->teacherProfile?->accepts_groups
                        ? trans_choice('people::directory.fields.group_up_to', (int) $p->teacherProfile->max_group_size, ['n' => (int) $p->teacherProfile->max_group_size])
                        : __('people::directory.columns.no_groups')),
                TextColumn::make('status')->label(__($c.'status'))->badge()
                    ->state(fn (Person $p) => __('people::directory.statuses.'.DirectoryForm::status($p)))
                    ->color(fn (Person $p) => match (DirectoryForm::status($p)) {
                        'disabled' => 'danger',
                        'invited' => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                SelectFilter::make('subject')->label(__('people::directory.filters.subject'))
                    ->options(fn () => app(CatalogApi::class)->subjectNames())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $id) => $q->whereExists(
                        fn ($s) => $s->from('teacher_subjects')->whereColumn('teacher_subjects.teacher_id', 'users.id')->where('teacher_subjects.subject_id', $id)))),
                SelectFilter::make('gender')->label(__('people::directory.filters.gender'))->options(__('people::directory.options.genders'))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $g) => $q->whereExists(
                        fn ($s) => $s->from('teacher_profiles')->whereColumn('teacher_profiles.user_id', 'users.id')->where('teacher_profiles.gender', $g)))),
                TernaryFilter::make('groups')->label(__('people::directory.filters.groups'))
                    ->trueLabel(__('people::directory.filters.groups_yes'))->falseLabel(__('people::directory.filters.groups_no'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereExists(fn ($s) => $s->from('teacher_profiles')->whereColumn('teacher_profiles.user_id', 'users.id')->where('teacher_profiles.accepts_groups', true)),
                        false: fn (Builder $query) => $query->whereNotExists(fn ($s) => $s->from('teacher_profiles')->whereColumn('teacher_profiles.user_id', 'users.id')->where('teacher_profiles.accepts_groups', true)),
                    ),
                TernaryFilter::make('active')->label(__('people::directory.filters.status'))
                    ->queries(true: fn (Builder $query) => $query->where('status', 'active'), false: fn (Builder $query) => $query->where('status', '!=', 'active')),
            ])
            ->recordUrl(fn (Person $p) => self::getUrl('edit', ['record' => $p]))
            ->emptyStateHeading(__('people::directory.teachers.empty'))
            ->emptyStateDescription(__('people::directory.teachers.empty_hint'))
            ->emptyStateIcon(Heroicon::OutlinedAcademicCap);
    }

    /** @param  array<string, mixed>  $data */
    public static function profile(array $data): TeacherProfileData
    {
        $hours = $data['max_weekly_hours'] ?? null;
        $subjects = array_map(fn (array $s) => new TeacherSubjectData(
            (string) $s['subject_id'], ($s['level_from_id'] ?? null) ?: null, ($s['level_to_id'] ?? null) ?: null, array_values($s['category_ids'] ?? []),
        ), array_values(array_filter($data['subjects'] ?? [], fn (array $s) => ! empty($s['subject_id']))));

        return new TeacherProfileData(
            teacherId: '',
            gender: (string) ($data['gender'] ?? ''),
            languages: array_values($data['languages'] ?? ['ar']),
            dialect: ($data['dialect'] ?? null) ?: null,
            acceptsNew: (bool) ($data['accepts_new'] ?? true),
            acceptsTrials: (bool) ($data['accepts_trials'] ?? true),
            maxWeeklyMinutes: $hours === null || $hours === '' ? null : (int) round((float) $hours * 60),
            ageGroups: array_values($data['age_groups'] ?? []),
            maxDailyLessons: ($data['max_daily_lessons'] ?? null) === null || $data['max_daily_lessons'] === '' ? null : (int) $data['max_daily_lessons'],
            subjects: $subjects,
            acceptsGroups: array_key_exists('accepts_groups', $data) ? (bool) $data['accepts_groups'] : null,
            maxGroupSize: (bool) ($data['accepts_groups'] ?? false) && ($data['max_group_size'] ?? null) !== null ? (int) $data['max_group_size'] : null,
        );
    }

    /** @return array<string, mixed> the teacher as the form shows them */
    public static function fill(Person $teacher): array
    {
        $profile = $teacher->teacherProfile;
        $categories = DB::table('teacher_subject_categories')->where('teacher_id', $teacher->id)->get()->groupBy('subject_id');
        $subjects = DB::table('teacher_subjects')->where('teacher_id', $teacher->id)->get()->map(fn ($s) => [
            'subject_id' => $s->subject_id,
            'level_from_id' => $s->level_from_id,
            'level_to_id' => $s->level_to_id,
            'category_ids' => ($categories[$s->subject_id] ?? collect())->pluck('category_id')->all(),
        ])->values()->all();

        return DirectoryForm::fill($teacher) + [
            'gender' => $profile?->gender,
            'languages' => $profile->languages ?? ['ar'],
            'dialect' => $profile?->dialect,
            'age_groups' => $profile->age_groups ?? [],
            'accepts_new' => $profile->accepts_new ?? true,
            'accepts_trials' => $profile->accepts_trials ?? true,
            'max_weekly_hours' => $profile?->max_weekly_min ? round($profile->max_weekly_min / 60, 1) : null,
            'max_daily_lessons' => $profile?->max_daily_lessons,
            'accepts_groups' => (bool) ($profile?->accepts_groups ?? false),
            'max_group_size' => $profile?->max_group_size ?? 6,
            'subjects' => $subjects,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeachers::route('/'),
            'create' => CreateTeacher::route('/create'),
            'edit' => EditTeacher::route('/{record}/edit'),
        ];
    }
}
