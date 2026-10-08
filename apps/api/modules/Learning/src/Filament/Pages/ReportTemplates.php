<?php

namespace Modules\Learning\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Learning\Actions\SaveReportTemplate;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Templates\TemplateDefinition;
use Modules\Learning\Templates\TemplateField;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * The academy's own questions in each subject's lesson report, as versions:
 * pick a subject, change its questions, save a new version. Reports already
 * written keep theirs (docs/architecture/01, section 10).
 *
 * @property-read Schema $form
 */
final class ReportTemplates extends Page
{
    protected string $view = 'learning::filament.report-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 42;

    protected static ?string $slug = 'report-questions';

    #[Url]
    public ?string $subject = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageCurriculum->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('learning::reports.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::templates.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::templates.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('learning::templates.subtitle');
    }

    public function mount(): void
    {
        if (! array_key_exists((string) $this->subject, $this->subjects())) {
            // The first subject that has questions, else the first one.
            $asking = ReportTemplate::query()->whereIn('subject_id', array_keys($this->subjects()))->pluck('subject_id')->unique()->all();
            $this->subject = collect(array_keys($this->subjects()))->first(fn (string $id) => in_array($id, $asking, true)) ?? array_key_first($this->subjects());
        }
        $this->load();
    }

    public function pick(string $subject): void
    {
        if (array_key_exists($subject, $this->subjects())) {
            $this->subject = $subject;
            unset($this->versions);
            $this->load();
        }
    }

    /** @return array<string, string> */
    public function subjects(): array
    {
        return app(CatalogApi::class)->subjectNames();
    }

    /** @return list<ReportTemplate> every version of this subject's questions, latest first */
    #[Computed]
    public function versions(): array
    {
        return $this->subject === null ? [] : ReportTemplate::query()->where('subject_id', $this->subject)->orderByDesc('version')->get()->all();
    }

    public function reportsUsing(ReportTemplate $t): int
    {
        return SessionReport::query()->where('template_id', $t->id)->count();
    }

    public function savedOn(ReportTemplate $t): string
    {
        return $t->created_at->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('j F Y');
    }

    public function form(Schema $schema): Schema
    {
        $l = 'learning::templates.fields.';
        $is = fn (string $type) => fn (Get $get) => $get('type') === $type;

        return $schema->statePath('data')->components([
            Repeater::make('fields')->label(__($l.'list'))
                ->schema([
                    Hidden::make('key'),
                    Grid::make(2)->schema([
                        TextInput::make('label_ar')->label(__($l.'label_ar'))->maxLength(120),
                        TextInput::make('label_en')->label(__($l.'label_en'))->maxLength(120),
                    ]),
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        Select::make('type')->label(__($l.'type'))->native(false)->required()->live()->default('rating')
                            ->options(array_combine(TemplateField::TYPES, array_map(fn ($t) => __($l.'types.'.$t), TemplateField::TYPES))),
                        Toggle::make('required')->label(__($l.'required'))->inline(false),
                        Toggle::make('family')->label(__($l.'family'))->helperText(__($l.'family_hint'))->inline(false)->default(true),
                    ]),
                    Grid::make(['default' => 2, 'md' => 5])->visible($is('number'))->schema([
                        TextInput::make('min')->label(__($l.'min'))->integer()->minValue(0)->maxValue(100000),
                        TextInput::make('max')->label(__($l.'max'))->integer()->minValue(0)->maxValue(100000),
                        TextInput::make('unit_ar')->label(__($l.'unit_ar'))->placeholder(__($l.'unit_hint'))->maxLength(20),
                        TextInput::make('unit_ar_few')->label(__($l.'unit_ar_few'))->placeholder(__($l.'unit_few_hint'))->maxLength(20),
                        TextInput::make('unit_en')->label(__($l.'unit_en'))->maxLength(20),
                    ]),
                    Repeater::make('options')->label(__($l.'options'))->visible($is('choice'))
                        ->table([TableColumn::make(__($l.'option_ar')), TableColumn::make(__($l.'option_en'))])
                        ->schema([
                            TextInput::make('ar')->hiddenLabel()->maxLength(60),
                            TextInput::make('en')->hiddenLabel()->maxLength(60),
                            Hidden::make('key'),
                        ])
                        ->defaultItems(2)->minItems(2)->maxItems(TemplateDefinition::MAX_OPTIONS)
                        ->addActionLabel(__($l.'add_option'))->reorderable(),
                ])
                ->itemLabel(fn (array $state) => ($state[app()->getLocale() === 'ar' ? 'label_ar' : 'label_en'] ?? null) ?: ($state['label_ar'] ?? null) ?: ($state['label_en'] ?? null))
                ->helperText(__($l.'one_language'))
                ->addActionLabel(__($l.'add'))
                ->defaultItems(0)->maxItems(TemplateDefinition::MAX_FIELDS)
                ->reorderable()->collapsible()->cloneable(),
        ]);
    }

    public function save(SaveReportTemplate $save): void
    {
        abort_unless(self::canAccess() && $this->subject !== null, 403);
        $before = $this->versions[0]->version ?? 0;
        $fields = array_values($this->form->getState()['fields'] ?? []);
        $fields = array_map(fn (array $f) => ['options' => array_values($f['options'] ?? [])] + $f, $fields);

        try {
            $template = $save->handle($this->subject, $fields, (string) auth()->id());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }

        unset($this->versions);
        $this->load();
        $template->version > $before
            ? Notification::make()->success()->title(__('learning::templates.saved', ['n' => $template->version]))->send()
            : Notification::make()->info()->title(__('learning::templates.unchanged'))->send();
    }

    private function load(): void
    {
        $current = $this->versions[0] ?? null;
        $this->form->fill(['fields' => array_map(fn (array $f) => [
            'key' => $f['key'],
            'label_ar' => $f['label_ar'],
            'label_en' => $f['label_en'],
            'type' => $f['type'],
            'required' => (bool) ($f['required'] ?? false),
            'family' => (bool) ($f['family'] ?? true),
            'min' => $f['min'] ?? null,
            'max' => $f['max'] ?? null,
            'unit_ar' => $f['unit_ar'] ?? null,
            'unit_en' => $f['unit_en'] ?? null,
            'unit_ar_few' => $f['unit_ar_few'] ?? null,
            'options' => $f['options'] ?? [],
        ], $current->fields ?? [])]);
    }
}
