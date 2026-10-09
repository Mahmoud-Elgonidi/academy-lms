<?php

namespace Modules\People\Filament\Directory\Guardians;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\People\Actions\Directory\AddStudent;
use Modules\People\Actions\Directory\NewGuardian;
use Modules\People\Actions\Directory\UnlinkGuardian;
use Modules\People\Actions\Directory\UpdateGuardianLink;
use Modules\People\Actions\LinkGuardian;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Filament\Directory\Students\StudentResource;
use Modules\People\Queries\Models\Person;

/** A guardian's children: add a child under them, link a student already here, change how they are linked, or remove one. */
final class ChildrenRelationManager extends RelationManager
{
    /** On the page from the start, so nothing jumps in after it. */
    protected static bool $isLazy = false;

    protected static string $relationship = 'children';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('people::directory.columns.children');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $l = 'people::directory.fields.';
        $o = 'people::directory.options.';
        $a = 'people::directory.actions.';
        $relationships = array_intersect_key(__($o.'relationships'), array_flip(UpdateGuardianLink::RELATIONSHIPS));

        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('studentProfile'))
            ->columns([
                TextColumn::make('name')->label(__($l.'student'))->weight('semibold')
                    ->description(fn (Person $p) => $p->is_managed_minor ? __('people::directory.under_guardian') : $p->contact())
                    ->url(fn (Person $p) => StudentResource::getUrl('edit', ['record' => $p])),
                TextColumn::make('age')->label(__('people::directory.columns.age'))
                    ->state(fn (Person $p) => $p->studentProfile?->birth_date ? __('people::directory.age', ['years' => (int) $p->studentProfile->birth_date->diffInYears(CarbonImmutable::now())]) : null),
                TextColumn::make('relationship')->label(__($l.'relationship'))->state(fn (Person $p) => __($o.'relationships.'.$p->pivot->relationship)),
                IconColumn::make('is_primary')->label(__($l.'is_primary'))->boolean()->state(fn (Person $p) => (bool) $p->pivot->is_primary),
                IconColumn::make('receives_reports')->label(__($l.'receives_reports'))->boolean()->state(fn (Person $p) => (bool) $p->pivot->receives_reports),
            ])
            ->headerActions([
                Action::make('addChild')->label(__($a.'add_child'))->icon('heroicon-o-plus')->modalWidth('2xl')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')->label(__($l.'name'))->required()->maxLength(255),
                            DatePicker::make('birth_date')->label(__($l.'birth_date'))->native(false)->maxDate(now()),
                            ToggleButtons::make('gender')->label(__($l.'student_gender'))->inline()->options(__($o.'student_genders')),
                            Select::make('school_grade')->label(__($l.'school_grade'))->options(__($o.'grades')),
                            Select::make('teacher_gender')->label(__($l.'teacher_gender'))->default('any')->selectablePlaceholder(false)->options(__($o.'teacher_genders')),
                            Select::make('relationship')->label(__($l.'relationship'))->default('parent')->selectablePlaceholder(false)->options($relationships),
                        ]),
                    ])
                    ->action(function (array $data) {
                        DirectoryForm::attempt(fn () => $this->addChild($data));
                        Notification::make()->success()->title(__('people::directory.students.created'))->send();
                    }),
                Action::make('linkChild')->label(__($a.'link_child'))->icon('heroicon-o-link')->color('gray')->modalWidth('lg')
                    ->schema([
                        Select::make('student_id')->label(__($l.'student'))->searchable()->required()
                            ->getSearchResultsUsing(fn (string $search) => $this->studentOptions($search))
                            ->getOptionLabelUsing(fn ($value) => Person::query()->find($value)?->name),
                        Select::make('relationship')->label(__($l.'relationship'))->default('parent')->selectablePlaceholder(false)->options($relationships),
                    ])
                    ->action(function (array $data) {
                        DirectoryForm::attempt(fn () => $this->link((string) $data['student_id'], (string) $data['relationship']));
                        Notification::make()->success()->title(__('people::directory.saved'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('editLink')->label(__($a.'edit_link'))->icon('heroicon-o-pencil-square')->modalWidth('lg')
                    ->fillForm(fn (Person $p) => ['relationship' => $p->pivot->relationship, 'is_primary' => (bool) $p->pivot->is_primary, 'receives_reports' => (bool) $p->pivot->receives_reports])
                    ->schema([
                        Select::make('relationship')->label(__($l.'relationship'))->selectablePlaceholder(false)->options($relationships),
                        Toggle::make('is_primary')->label(__($l.'is_primary')),
                        Toggle::make('receives_reports')->label(__($l.'receives_reports')),
                    ])
                    ->action(fn (Person $p, array $data) => DirectoryForm::attempt(fn () => app(UpdateGuardianLink::class)->handle(
                        $this->getOwnerRecord()->getKey(), $p->id, (string) $data['relationship'], (bool) $data['is_primary'], (bool) $data['receives_reports']))),
                Action::make('unlink')->label(__($a.'unlink'))->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()->modalDescription(__($a.'unlink_body'))
                    ->action(function (Person $p) use ($a) {
                        DirectoryForm::attempt(fn () => app(UnlinkGuardian::class)->handle($this->getOwnerRecord()->getKey(), $p->id));
                        Notification::make()->success()->title(__($a.'unlinked'))->send();
                    }),
            ])
            ->emptyStateHeading(__('people::directory.students.empty'));
    }

    /** @param  array<string, mixed>  $data */
    private function addChild(array $data): void
    {
        /** @var Person $guardian */
        $guardian = $this->getOwnerRecord();
        $teacherGender = $data['teacher_gender'] ?? null;
        app(AddStudent::class)->handle(
            new UserDetails(trim((string) $data['name']), null, null, $guardian->locale, $guardian->timezone),
            new NewGuardian(existingId: $guardian->id, relationship: (string) ($data['relationship'] ?? 'parent')),
            empty($data['birth_date']) ? null : CarbonImmutable::parse($data['birth_date']),
            null,
            new StudentDetails(($data['gender'] ?? null) ?: null, null, ($data['school_grade'] ?? null) ?: null, $teacherGender === 'any' ? null : $teacherGender),
            false,
        );
    }

    private function link(string $studentId, string $relationship): void
    {
        $student = Person::query()->withRole('student')->findOrFail($studentId);
        if ($student->guardians()->where('users.id', $this->getOwnerRecord()->getKey())->exists()) {
            throw ValidationException::withMessages(['student' => __('people::directory.errors.already_linked')]);
        }
        app(LinkGuardian::class)->handle($this->getOwnerRecord()->getKey(), $student->id, $relationship, ! $student->guardians()->exists());
    }

    /** @return array<string, string> students matching what was typed, not already this guardian's */
    private function studentOptions(string $search): array
    {
        $linked = $this->getOwnerRecord()->children()->pluck('users.id')->all();

        return Person::query()->withRole('student')->where('status', 'active')->whereNotIn('id', $linked)
            ->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
            ->orderBy('name')->limit(20)->pluck('name', 'id')->all();
    }
}
