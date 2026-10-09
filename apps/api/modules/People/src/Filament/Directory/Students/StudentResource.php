<?php

namespace Modules\People\Filament\Directory\Students;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Actions\Directory\NewGuardian;
use Modules\People\Actions\Directory\UpdateGuardianLink;
use Modules\People\Actions\SaveStudentDetails;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;
use Modules\People\Support\Countries;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Students (docs/architecture/01, section 6): an older student with their
 * own email or number signs in themselves; a child's account is under a
 * guardian. Their age, gender, country, grade and who should teach them;
 * their guardians on their page. Courses start from "Find a teacher".
 */
final class StudentResource extends Resource
{
    /** A course still running, paused or waiting for its first payment. */
    public const RUNNING = ['pending_payment', 'active', 'paused'];

    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'people/students';

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
        return __('people::directory.students.nav');
    }

    public static function getModelLabel(): string
    {
        return __('people::directory.students.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('people::directory.students.nav');
    }

    public static function getEloquentQuery(): Builder
    {
        return Person::query()->withRole('student');
    }

    public static function form(Schema $schema): Schema
    {
        $l = 'people::directory.fields.';
        $o = 'people::directory.options.';
        [$name, $email, $phone, $timezone, $locale] = DirectoryForm::contact();
        $own = fn (Get $get) => (bool) $get('own_contact');

        return $schema->columns(1)->components([
            Section::make(__('people::directory.sections.contact'))->columns(2)->schema([
                $name,
                Toggle::make('own_contact')->label(__($l.'own_contact'))->helperText(__($l.'own_contact_hint'))->live()->default(false)
                    ->visibleOn('create'),
                Hidden::make('managed'),
                $email->visible(fn (Get $get, string $operation) => $operation === 'create' ? $own($get) : ! $get('managed')),
                $phone->visible(fn (Get $get, string $operation) => $operation === 'create' ? $own($get) : ! $get('managed')),
                $timezone,
                $locale,
            ]),
            Section::make(__('people::directory.sections.student'))->columns(2)->schema([
                DatePicker::make('birth_date')->label(__($l.'birth_date'))->native(false)->maxDate(now())->minDate(now()->subYears(100)),
                Select::make('stage')->label(__($l.'stage'))->helperText(__($l.'stage_hint'))->default('auto')->selectablePlaceholder(false)
                    ->options(__($o.'stages')),
                ToggleButtons::make('gender')->label(__($l.'student_gender'))->inline()->options(__($o.'student_genders')),
                Select::make('country')->label(__($l.'country'))->searchable()->options(fn () => Countries::options()),
                Select::make('school_grade')->label(__($l.'school_grade'))->options(__($o.'grades')),
                Select::make('teacher_gender')->label(__($l.'teacher_gender'))->helperText(__($l.'teacher_gender_hint'))->default('any')->selectablePlaceholder(false)
                    ->options(__($o.'teacher_genders')),
            ]),
            Section::make(__('people::directory.sections.guardian'))->visibleOn('create')->columns(2)->schema([
                ToggleButtons::make('guardian_mode')->label(__($l.'guardian_mode'))->inline()->live()->default('new')->columnSpanFull()
                    ->options(fn (Get $get) => $own($get) ? __($o.'guardian_modes') : array_diff_key(__($o.'guardian_modes'), ['none' => true])),
                Select::make('guardian_id')->label(__($l.'guardian'))->searchable()->columnSpanFull()
                    ->visible(fn (Get $get) => $get('guardian_mode') === 'existing')->required(fn (Get $get) => $get('guardian_mode') === 'existing')
                    ->getSearchResultsUsing(fn (string $search) => self::guardianOptions($search))
                    ->getOptionLabelUsing(fn ($value) => Person::query()->find($value)?->name),
                Grid::make(2)->columnSpanFull()->visible(fn (Get $get) => $get('guardian_mode') === 'new')
                    ->schema(array_map(fn ($f) => $f->required(fn (Get $get, $component) => $get('guardian_mode') === 'new' && in_array($component->getName(), ['guardian_name', 'guardian_timezone'], true)), DirectoryForm::contact('guardian_', false))),
                Select::make('relationship')->label(__($l.'relationship'))->default('parent')->selectablePlaceholder(false)
                    ->visible(fn (Get $get) => $get('guardian_mode') !== 'none')
                    ->options(array_intersect_key(__($o.'relationships'), array_flip(UpdateGuardianLink::RELATIONSHIPS))),
            ]),
            Section::make(__('people::directory.sections.welcome'))->visibleOn('create')->schema([
                Toggle::make('welcome')->label(__($l.'welcome'))->helperText(__($l.'welcome_hint'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $c = 'people::directory.columns.';

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('studentProfile')->addSelect([
                'guardian_names' => DB::table('guardian_student as gs')
                    ->join('users as g', fn ($j) => $j->on('g.academy_id', '=', 'gs.academy_id')->on('g.id', '=', 'gs.guardian_id'))
                    ->whereColumn('gs.student_id', 'users.id')->selectRaw("string_agg(g.name, '|' order by gs.is_primary desc, g.name)"),
                'running_courses' => DB::table('enrollments')->whereColumn('enrollments.student_id', 'users.id')
                    ->whereIn('enrollments.status', self::RUNNING)->selectRaw('count(*)'),
            ]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__($c.'name'))->weight('semibold')->sortable()
                    ->searchable(['name', 'email', 'phone_e164'])
                    ->description(fn (Person $p) => $p->is_managed_minor ? __('people::directory.under_guardian') : $p->contact()),
                TextColumn::make('age')->label(__($c.'age'))->visibleFrom('md')
                    ->state(fn (Person $p) => $p->studentProfile?->birth_date ? __('people::directory.age', ['years' => (int) $p->studentProfile->birth_date->diffInYears(CarbonImmutable::now())]) : null),
                TextColumn::make('guardian_names')->label(__($c.'guardians'))->badge()->color('gray')->separator('|'),
                TextColumn::make('country')->label(__($c.'country'))->visibleFrom('md')
                    ->state(fn (Person $p) => $p->studentProfile?->country ? Countries::name($p->studentProfile->country) : null),
                TextColumn::make('running_courses')->label(__($c.'courses'))->visibleFrom('md')->numeric()->alignCenter(),
                TextColumn::make('status')->label(__($c.'status'))->badge()
                    ->state(fn (Person $p) => __('people::directory.statuses.'.DirectoryForm::status($p)))
                    ->color(fn (Person $p) => match (DirectoryForm::status($p)) {
                        'disabled' => 'danger',
                        'invited' => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                TernaryFilter::make('courses')->label(__('people::directory.filters.courses'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereExists(fn ($s) => $s->from('enrollments')->whereColumn('enrollments.student_id', 'users.id')->whereIn('enrollments.status', self::RUNNING)),
                        false: fn (Builder $query) => $query->whereNotExists(fn ($s) => $s->from('enrollments')->whereColumn('enrollments.student_id', 'users.id')->whereIn('enrollments.status', self::RUNNING)),
                    ),
                TernaryFilter::make('active')->label(__('people::directory.filters.status'))
                    ->queries(true: fn (Builder $query) => $query->where('status', 'active'), false: fn (Builder $query) => $query->where('status', '!=', 'active')),
            ])
            ->recordUrl(fn (Person $p) => self::getUrl('edit', ['record' => $p]))
            ->emptyStateHeading(__('people::directory.students.empty'))
            ->emptyStateDescription(__('people::directory.students.empty_hint'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }

    /** @return array<string, string> guardians matching what was typed (name, email or number) */
    public static function guardianOptions(string $search, array $except = []): array
    {
        return Person::query()->withRole('guardian')->where('status', 'active')->whereNotIn('id', $except)
            ->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")
                ->when(preg_replace('/\D/', '', $search), fn ($q, string $digits) => $q->orWhere('phone_e164', 'like', "%{$digits}%")))
            ->orderBy('name')->limit(20)->get()->mapWithKeys(fn (Person $p) => [$p->id => $p->name.' · '.$p->contact()])->all();
    }

    /** @param  array<string, mixed>  $data */
    public static function birthDate(array $data): ?CarbonImmutable
    {
        return empty($data['birth_date']) ? null : CarbonImmutable::parse($data['birth_date']);
    }

    /** @param  array<string, mixed>  $data */
    public static function stage(array $data): ?LearnerStage
    {
        return LearnerStage::tryFrom((string) ($data['stage'] ?? ''));
    }

    /** @param  array<string, mixed>  $data */
    public static function details(array $data): StudentDetails
    {
        $teacherGender = $data['teacher_gender'] ?? null;

        return new StudentDetails(
            gender: ($data['gender'] ?? null) ?: null,
            country: ($data['country'] ?? null) ?: null,
            schoolGrade: ($data['school_grade'] ?? null) ?: null,
            teacherGender: in_array($teacherGender, SaveStudentDetails::TEACHER_GENDERS, true) ? $teacherGender : null,
        );
    }

    /** @param  array<string, mixed>  $data */
    public static function guardian(array $data): ?NewGuardian
    {
        $relationship = (string) ($data['relationship'] ?? 'parent');

        return match ($data['guardian_mode'] ?? 'none') {
            'existing' => new NewGuardian(existingId: $data['guardian_id'] ?? null, relationship: $relationship),
            'new' => new NewGuardian(details: DirectoryForm::details($data, 'guardian_'), relationship: $relationship),
            default => null,
        };
    }

    /** @return array<string, mixed> the student as the form shows them */
    public static function fill(Person $student): array
    {
        $profile = $student->studentProfile;

        return DirectoryForm::fill($student) + [
            'managed' => $student->is_managed_minor,
            'birth_date' => $profile?->birth_date?->toDateString(),
            'stage' => $profile->stage ?? 'auto',
            'gender' => $profile?->gender,
            'country' => $profile?->country,
            'school_grade' => $profile?->school_grade,
            'teacher_gender' => $profile->teacher_gender ?? 'any',
        ];
    }

    public static function getRelations(): array
    {
        return [GuardiansRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }
}
