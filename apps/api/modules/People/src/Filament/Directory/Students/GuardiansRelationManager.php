<?php

namespace Modules\People\Filament\Directory\Students;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Actions\Directory\AddGuardian;
use Modules\People\Actions\Directory\UnlinkGuardian;
use Modules\People\Actions\Directory\UpdateGuardianLink;
use Modules\People\Actions\LinkGuardian;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Filament\Directory\Guardians\GuardianResource;
use Modules\People\Queries\Models\Person;

/** A student's guardians: who they are to them, the main one, who gets the reports; add one already here or a new one, or remove one. */
final class GuardiansRelationManager extends RelationManager
{
    /** On the page from the start, so nothing jumps in after it. */
    protected static bool $isLazy = false;

    protected static string $relationship = 'guardians';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('people::directory.columns.guardians');
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

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__($l.'name'))->weight('semibold')->description(fn (Person $p) => $p->contact())
                    ->url(fn (Person $p) => GuardianResource::getUrl('edit', ['record' => $p])),
                TextColumn::make('relationship')->label(__($l.'relationship'))->state(fn (Person $p) => __($o.'relationships.'.$p->pivot->relationship)),
                IconColumn::make('is_primary')->label(__($l.'is_primary'))->boolean()->state(fn (Person $p) => (bool) $p->pivot->is_primary),
                IconColumn::make('receives_reports')->label(__($l.'receives_reports'))->boolean()->state(fn (Person $p) => (bool) $p->pivot->receives_reports),
            ])
            ->headerActions([
                Action::make('addGuardian')->label(__($a.'add_guardian'))->icon('heroicon-o-plus')->modalWidth('2xl')
                    ->schema([
                        ToggleButtons::make('guardian_mode')->label(__($l.'guardian_mode'))->inline()->live()->default('existing')
                            ->options(array_diff_key(__($o.'guardian_modes'), ['none' => true])),
                        Select::make('guardian_id')->label(__($l.'guardian'))->searchable()
                            ->visible(fn (Get $get) => $get('guardian_mode') === 'existing')->required(fn (Get $get) => $get('guardian_mode') === 'existing')
                            ->getSearchResultsUsing(fn (string $search) => StudentResource::guardianOptions($search, $this->getOwnerRecord()->guardians()->pluck('users.id')->all()))
                            ->getOptionLabelUsing(fn ($value) => Person::query()->find($value)?->name),
                        Grid::make(2)->visible(fn (Get $get) => $get('guardian_mode') === 'new')->schema(DirectoryForm::contact('guardian_', false)),
                        Select::make('relationship')->label(__($l.'relationship'))->default('parent')->selectablePlaceholder(false)
                            ->options(array_intersect_key(__($o.'relationships'), array_flip(UpdateGuardianLink::RELATIONSHIPS))),
                        Toggle::make('welcome')->label(__($l.'welcome'))->default(true)->visible(fn (Get $get) => $get('guardian_mode') === 'new'),
                    ])
                    ->action(function (array $data) {
                        DirectoryForm::attempt(fn () => $this->addGuardian($data));
                        Notification::make()->success()->title(__('people::directory.saved'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('editLink')->label(__($a.'edit_link'))->icon('heroicon-o-pencil-square')->modalWidth('lg')
                    ->fillForm(fn (Person $p) => ['relationship' => $p->pivot->relationship, 'is_primary' => (bool) $p->pivot->is_primary, 'receives_reports' => (bool) $p->pivot->receives_reports])
                    ->schema([
                        Select::make('relationship')->label(__($l.'relationship'))->selectablePlaceholder(false)
                            ->options(array_intersect_key(__($o.'relationships'), array_flip(UpdateGuardianLink::RELATIONSHIPS))),
                        Toggle::make('is_primary')->label(__($l.'is_primary')),
                        Toggle::make('receives_reports')->label(__($l.'receives_reports')),
                    ])
                    ->action(fn (Person $p, array $data) => DirectoryForm::attempt(fn () => app(UpdateGuardianLink::class)->handle(
                        $p->id, $this->getOwnerRecord()->getKey(), (string) $data['relationship'], (bool) $data['is_primary'], (bool) $data['receives_reports']))),
                Action::make('unlink')->label(__($a.'unlink'))->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()->modalDescription(__($a.'unlink_body'))
                    ->action(function (Person $p) use ($a) {
                        DirectoryForm::attempt(fn () => app(UnlinkGuardian::class)->handle($p->id, $this->getOwnerRecord()->getKey()));
                        Notification::make()->success()->title(__($a.'unlinked'))->send();
                    }),
            ])
            ->emptyStateHeading(__('people::directory.guardians.empty'));
    }

    /** @param  array<string, mixed>  $data */
    private function addGuardian(array $data): void
    {
        DB::transaction(fn () => $this->link($data));
    }

    /** @param  array<string, mixed>  $data */
    private function link(array $data): void
    {
        $studentId = $this->getOwnerRecord()->getKey();
        $guardianId = $data['guardian_mode'] === 'new'
            ? app(AddGuardian::class)->handle(DirectoryForm::details($data, 'guardian_'), (bool) ($data['welcome'] ?? false))
            : (string) $data['guardian_id'];
        $linked = $this->getOwnerRecord()->guardians()->pluck('users.id')->all();
        if (in_array($guardianId, $linked, true)) {
            throw ValidationException::withMessages(['guardian' => __('people::directory.errors.already_linked')]);
        }
        app(LinkGuardian::class)->handle($guardianId, $studentId, (string) $data['relationship'], $linked === []);
    }
}
