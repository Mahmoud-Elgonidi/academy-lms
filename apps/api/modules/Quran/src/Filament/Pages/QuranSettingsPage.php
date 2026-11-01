<?php

namespace Modules\Quran\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Actions\Settings\SaveQuranSettings;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Grading\GradeScale;
use Modules\Quran\Domain\Grading\MistakeTypes;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Quran › Settings (owner and admin, while the plan has the Quran; docs/
 * architecture/02, section 26), as cards: the riwaya and the mushaf (Hafs
 * and the Madinah mushaf, for now), a new program's amounts and the daily
 * review, the mistake types and their weights (the three built in keep
 * their keys; the academy's own get a key once), the grade scale and the
 * memorization threshold, the automatic recitation check, exams and who
 * examines, certificates. Saved only through SaveQuranSettings.
 *
 * @property-read Schema $form
 */
final class QuranSettingsPage extends Page
{
    use QuranMenu;

    protected string $view = 'quran::filament.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 49;

    protected static ?string $slug = 'quran/settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageQuran->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::quran.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::quran.settings.title');
    }

    public function getSubheading(): ?string
    {
        return __('quran::screens.settings.subtitle');
    }

    public function mount(): void
    {
        $s = QuranSettings::current();
        $this->form->fill(array_merge($s->only(QuranSettings::FIELDS), [
            'mistake_types' => array_map(fn (array $t) => $t + ['name_ar' => null, 'name_en' => null], $s->mistakeTypes()),
            'grade_scale' => $s->gradeScale(),
        ]));
    }

    public function form(Schema $schema): Schema
    {
        $a = 'quran::settings.attributes.';
        $w = 'quran::screens.settings.';

        return $schema->statePath('data')->components([
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make(__($w.'mushaf.title'))->description(__($w.'mushaf.hint'))->icon(Heroicon::OutlinedBookOpen)->schema([
                    Grid::make(2)->schema([
                        Select::make('riwaya')->label(__($a.'riwaya'))->options(trans('quran::settings.riwayat'))->disabled()->dehydrated(false)->native(false)->selectablePlaceholder(false),
                        Select::make('mushaf')->label(__($a.'mushaf'))->options(trans('quran::settings.mushafs'))->disabled()->dehydrated(false)->native(false)->selectablePlaceholder(false),
                    ]),
                    Text::make(__($w.'mushaf.more')),
                ]),
                Section::make(__($w.'amounts.title'))->description(__($w.'amounts.hint'))->icon(Heroicon::OutlinedScale)->schema([
                    Grid::make(2)->schema([
                        Select::make('default_unit')->label(__($a.'default_unit'))->options(array_intersect_key((array) trans('quran::programs.units'), array_flip(ProgramRules::UNITS)))
                            ->required()->native(false)->selectablePlaceholder(false)->live(),
                        TextInput::make('default_new_amount')->label(__($a.'default_new_amount'))->numeric()->required()
                            ->step(fn (Get $get) => ProgramRules::AMOUNTS[$get('default_unit')][2] ?? 0.25),
                        TextInput::make('default_near_portions')->label(__($a.'default_near_portions'))->numeric()->minValue(0)->maxValue(ProgramRules::NEAR_MAX)->required()
                            ->helperText(__($w.'amounts.near')),
                        TextInput::make('default_far_pages')->label(__($a.'default_far_pages'))->numeric()->minValue(0)->maxValue(ProgramRules::FAR_MAX)->required()
                            ->helperText(__($w.'amounts.far')),
                    ]),
                    TextInput::make('hifz_daily_pages')->label(__('quran::hifz.settings.daily'))->helperText(__('quran::hifz.settings.hint'))
                        ->numeric()->minValue(1)->maxValue(SaveQuranSettings::DAILY_MAX)->required(),
                ]),
            ]),
            Section::make(__($w.'mistakes.title'))->description(__($w.'mistakes.hint'))->icon(Heroicon::OutlinedPencilSquare)->schema([
                Repeater::make('mistake_types')->hiddenLabel()->maxItems(MistakeTypes::MAX)->reorderable(false)->grid(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->addActionLabel(__($w.'mistakes.add'))
                    ->itemLabel(fn (array $state) => in_array($state['key'] ?? null, MistakeTypes::BUILT_IN, true)
                        ? __('quran::mushaf.kinds.'.$state['key']) : ((app()->getLocale() === 'ar' ? ($state['name_ar'] ?? null) : ($state['name_en'] ?? null)) ?: __($w.'mistakes.new')))
                    ->deleteAction(fn (Action $action) => $action->visible(fn (array $arguments, Repeater $component): bool => ! in_array($component->getRawItemState($arguments['item'])['key'] ?? null, MistakeTypes::BUILT_IN, true)))
                    ->schema([
                        Hidden::make('key'),
                        Grid::make(2)->visible(fn (Get $get) => ! in_array($get('key'), MistakeTypes::BUILT_IN, true))->schema([
                            TextInput::make('name_ar')->label(__($w.'mistakes.name_ar'))->maxLength(MistakeTypes::NAME_MAX)->required(fn (Get $get) => ! in_array($get('key'), MistakeTypes::BUILT_IN, true)),
                            TextInput::make('name_en')->label(__($w.'mistakes.name_en'))->maxLength(MistakeTypes::NAME_MAX)->required(fn (Get $get) => ! in_array($get('key'), MistakeTypes::BUILT_IN, true)),
                        ]),
                        TextInput::make('weight')->label(__($w.'mistakes.weight'))->helperText(__($w.'mistakes.weight_hint'))->numeric()->minValue(0)->maxValue(MistakeTypes::WEIGHT_MAX)->step(0.25)->required(),
                    ]),
            ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make(__($w.'grades.title'))->description(__($w.'grades.hint'))->icon(Heroicon::OutlinedAcademicCap)->schema([
                    Grid::make(2)->schema(array_map(fn (string $g) => TextInput::make('grade_scale.'.$g)->label(__($w.'grades.up_to', ['grade' => __('quran::programs.grades.'.$g)]))
                        ->numeric()->minValue(0)->maxValue(GradeScale::MAX)->step(0.25)->required()->suffix(__($w.'grades.per_page')), Grades::PASSING)),
                    Text::make(__($w.'grades.repeat')),
                    Select::make('memorization_threshold')->label(__($a.'memorization_threshold'))->helperText(__($w.'grades.threshold'))
                        ->options(collect(Grades::PASSING)->mapWithKeys(fn (string $g) => [$g => __('quran::programs.grades.'.$g)])->all())->required()->native(false)->selectablePlaceholder(false),
                ]),
                Section::make(__($w.'check.title'))->description(__($w.'check.hint'))->icon(Heroicon::OutlinedMicrophone)->schema([
                    Toggle::make('auto_recitation_check')->label(__($a.'auto_recitation_check'))->helperText(__($w.'check.toggle')),
                ]),
            ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make(__($w.'exams.title'))->description(__($w.'exams.hint'))->icon(Heroicon::OutlinedClipboardDocumentCheck)->schema([
                    Toggle::make('exams_enabled')->label(__($w.'exams.toggle'))->live(),
                    Radio::make('examiner')->label(__($a.'examiner'))->options(trans('quran::settings.examiners'))
                        ->descriptions(trans('quran::screens.settings.exams.examiners'))->required()->visible(fn (Get $get) => (bool) $get('exams_enabled')),
                ]),
                Section::make(__($w.'certificates.title'))->description(__($w.'certificates.hint'))->icon(Heroicon::OutlinedTrophy)->schema([
                    Toggle::make('certificates_enabled')->label(__($w.'certificates.toggle'))->live(),
                    Grid::make(2)->visible(fn (Get $get) => (bool) $get('certificates_enabled'))->schema([
                        TextInput::make('certificate_title')->label(__($a.'certificate_title'))->placeholder(__('quran::certificates.title'))->maxLength(120),
                        TextInput::make('certificate_signature')->label(__($a.'certificate_signature'))->placeholder(__($w.'certificates.signature_placeholder'))->maxLength(120),
                    ]),
                    Toggle::make('certificates_family')->label(__($a.'certificates_family'))->helperText(__($w.'certificates.family'))
                        ->visible(fn (Get $get) => (bool) $get('certificates_enabled')),
                ]),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $data = $this->form->getState();
        $data['mistake_types'] = self::keyed(array_values($data['mistake_types'] ?? []));
        // The defaults stay unset (null) until the academy makes them its own.
        $current = QuranSettings::current();
        if ($current->mistake_types === null && MistakeTypes::errors($data['mistake_types']) === [] && MistakeTypes::normalize($data['mistake_types']) == MistakeTypes::DEFAULTS) {
            unset($data['mistake_types']);
        }
        if ($current->grade_scale === null && GradeScale::errors($data['grade_scale'] ?? null) === [] && GradeScale::normalize($data['grade_scale']) == GradeScale::DEFAULT) {
            unset($data['grade_scale']);
        }
        try {
            app(SaveQuranSettings::class)->handle($data);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn (array $m, string $key) => [match (true) {
                $key === 'grade_scale' => 'data.grade_scale.acceptable',
                default => 'data.'.$key,
            } => $m])->all());
        }
        $this->mount();
        Notification::make()->success()->title(__('quran::screens.settings.saved'))->send();
    }

    /**
     * Each mistake type with its key: the built-in keep theirs, a new one takes one from its English name, unique.
     *
     * @param  list<array<string, mixed>>  $types
     * @return list<array<string, mixed>>
     */
    public static function keyed(array $types): array
    {
        $taken = array_filter(array_column($types, 'key'));
        foreach ($types as $i => $type) {
            if (! empty($type['key'])) {
                continue;
            }
            $base = Str::of((string) ($type['name_en'] ?? ''))->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(24, '')->value();
            $base = preg_match('/^[a-z]/', $base) === 1 ? $base : 'kind_'.$base;
            $key = rtrim($base, '_') ?: 'kind';
            for ($n = 2; in_array($key, $taken, true); $n++) {
                $key = rtrim($base, '_').'_'.$n;
            }
            $taken[] = $types[$i]['key'] = $key;
        }

        return array_map(fn (array $t) => array_filter($t, fn ($v) => $v !== null), $types);
    }
}
