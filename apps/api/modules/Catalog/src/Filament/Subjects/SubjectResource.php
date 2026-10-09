<?php

namespace Modules\Catalog\Filament\Subjects;

use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Catalog\Models\Subject;
use Modules\Catalog\Models\SubjectCategory;
use Modules\Catalog\Models\SubjectLevel;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Subjects (docs/architecture/01, section 3): each with its levels in order
 * and its categories (tracks or school curricula). Teachers take a range of
 * levels and the categories they teach; "Find a teacher" asks for them.
 */
final class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'subjects';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageCatalog->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('catalog::subjects.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog::subjects.nav');
    }

    public static function getModelLabel(): string
    {
        return __('catalog::subjects.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog::subjects.nav');
    }

    public static function form(Schema $schema): Schema
    {
        $l = 'catalog::subjects.fields.';
        $items = fn (string $name, string $hint, string $add) => Repeater::make($name)
            ->label(__($l.$name))->helperText(__($l.$hint))
            ->schema([
                Hidden::make('id'),
                TextInput::make('name_ar')->label(__($l.'name_ar'))->required()->maxLength(80),
                TextInput::make('name_en')->label(__($l.'name_en'))->maxLength(80),
            ])
            ->columns(2)->reorderable()->defaultItems(0)->addActionLabel(__($l.$add))
            ->itemLabel(fn (array $state) => $state['name_ar'] ?? null);

        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                TextInput::make('name_ar')->label(__($l.'name_ar'))->required()->maxLength(120),
                TextInput::make('name_en')->label(__($l.'name_en'))->helperText(__($l.'name_en_hint'))->maxLength(120),
            ]),
            $items('levels', 'levels_hint', 'add_level')->maxItems(SaveSubject::MAX_LEVELS),
            $items('categories', 'categories_hint', 'add_category')->maxItems(SaveSubject::MAX_CATEGORIES),
        ]);
    }

    public static function table(Table $table): Table
    {
        $c = 'catalog::subjects.columns.';
        $name = fn (string $prefix = '') => app()->getLocale() === 'ar' ? $prefix.'name_ar' : $prefix.'name_en';

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['levels', 'categories'])
                ->addSelect(['teachers' => DB::table('teacher_subjects')->selectRaw('count(*)')->whereColumn('teacher_subjects.subject_id', 'subjects.id')]))
            ->defaultSort($name())
            ->columns([
                TextColumn::make($name())->label(__($c.'name'))->weight('semibold')->searchable(['name_ar', 'name_en'])->sortable()
                    ->description(fn (Subject $s) => app()->getLocale() === 'ar' ? ($s->name_en !== $s->name_ar ? $s->name_en : null) : ($s->name_ar !== $s->name_en ? $s->name_ar : null)),
                TextColumn::make('levels')->label(__($c.'levels'))->wrap()
                    ->state(fn (Subject $s) => $s->levels->isEmpty() ? __('catalog::subjects.no_levels') : $s->levels->map(fn (SubjectLevel $l) => $l->name())->implode(__('catalog::subjects.level_separator'))),
                TextColumn::make('categories')->label(__($c.'categories'))->badge()->color('gray')
                    ->state(fn (Subject $s) => $s->categories->map(fn (SubjectCategory $c) => $c->name())->all()),
                TextColumn::make('teachers')->label(__($c.'teachers'))->numeric()->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('catalog::subjects.edit'))
                    ->modalWidth('3xl')
                    ->mutateRecordDataUsing(fn (array $data, Subject $record) => self::fill($record))
                    ->using(fn (Model $record, array $data) => self::attempt(fn () => self::save($record->getKey(), $data)))
                    ->successNotificationTitle(__('catalog::subjects.saved')),
            ])
            ->emptyStateHeading(__('catalog::subjects.empty'))
            ->emptyStateDescription(__('catalog::subjects.empty_hint'))
            ->emptyStateIcon(Heroicon::OutlinedBookOpen);
    }

    /** @return array<string, mixed> */
    public static function fill(Subject $subject): array
    {
        $item = fn (SubjectLevel|SubjectCategory $i) => ['id' => $i->id, 'name_ar' => $i->name_ar, 'name_en' => $i->name_en === $i->name_ar ? null : $i->name_en];

        return [
            'name_ar' => $subject->name_ar,
            'name_en' => $subject->name_en === $subject->name_ar ? null : $subject->name_en,
            'levels' => $subject->levels->map($item)->values()->all(),
            'categories' => $subject->categories->map($item)->values()->all(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?string $id, array $data): Subject
    {
        return app(SaveSubject::class)->handle(
            $id,
            (string) ($data['name_ar'] ?? ''),
            $data['name_en'] ?? null,
            array_values($data['levels'] ?? []),
            array_values($data['categories'] ?? []),
        );
    }

    /**
     * What the save refuses is said in a notification, and the modal stays open.
     *
     * @template T
     *
     * @param  Closure(): T  $save
     * @return T
     */
    public static function attempt(Closure $save): mixed
    {
        try {
            return $save();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();

            throw new Halt;
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubjects::route('/')];
    }
}
