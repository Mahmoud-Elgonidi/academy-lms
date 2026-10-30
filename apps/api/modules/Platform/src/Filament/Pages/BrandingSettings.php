<?php

namespace Modules\Platform\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Platform\Actions\DeleteBrandTemplate;
use Modules\Platform\Actions\SaveAcademyTimeFormat;
use Modules\Platform\Actions\SaveAcademyTimezone;
use Modules\Platform\Actions\SaveBranding;
use Modules\Platform\Actions\SaveBrandTemplate;
use Modules\Platform\Actions\UpdateAcademyIdentity;
use Modules\Platform\Actions\UpdateBrandTemplate;
use Modules\Platform\Branding\BrandFonts;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Branding\BuiltInTemplates;
use Modules\Platform\Branding\TemplateCatalog;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Models\AcademyBranding;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\TimeFormat;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerClock;
use UnitEnum;

/**
 * Settings › Identity and colours. Pick a built-in or saved template, edit any
 * colour, font or corner style with a live preview, then apply it, save it as
 * a new template, or update the saved template it came from.
 *
 * @property-read Schema $form
 */
final class BrandingSettings extends Page
{
    protected string $view = 'platform::filament.branding-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'settings/branding';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** The template the tokens on screen started from ("builtin:noor", "saved:{id}") or null. */
    public ?string $baseRef = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageBranding->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::branding.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::branding.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::branding.subtitle');
    }

    public function mount(): void
    {
        $branding = app(BrandingApi::class)->current();
        $row = AcademyBranding::query()->first();

        $this->baseRef = $branding->templateRef;
        $this->form->fill([
            'name_ar' => $branding->nameAr,
            'name_en' => $branding->nameEn,
            'logo' => $row?->logo_path,
            'favicon' => $row?->favicon_path,
            'time_format' => app(ViewerClock::class)->academy()->value,
            'default_timezone' => app(TenantContext::class)->require()->defaultTimezone,
            ...$branding->tokens,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $directory = UpdateAcademyIdentity::directory(app(TenantContext::class)->require()->id);

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('platform::branding.sections.identity'))
                    ->description(__('platform::branding.identity_hint'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name_ar')->label(__('platform::branding.fields.name_ar'))->required()->maxLength(120)->live(debounce: 400),
                            TextInput::make('name_en')->label(__('platform::branding.fields.name_en'))->required()->maxLength(120)->live(debounce: 400),
                            FileUpload::make('logo')
                                ->label(__('platform::branding.fields.logo'))
                                ->helperText(__('platform::branding.logo_hint'))
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->rules([new CleanFile])
                                ->maxSize(1024)
                                ->disk('public')->directory($directory)->visibility('public')
                                ->live(),
                            FileUpload::make('favicon')
                                ->label(__('platform::branding.fields.favicon'))
                                ->helperText(__('platform::branding.favicon_hint'))
                                ->image()
                                ->acceptedFileTypes(['image/png'])
                                ->rules([new CleanFile])
                                ->maxSize(256)
                                ->disk('public')->directory($directory)->visibility('public'),
                        ]),
                        ToggleButtons::make('time_format')
                            ->label(__('platform::branding.fields.time_format'))
                            ->helperText(__('platform::branding.time_format.hint'))
                            ->options([
                                TimeFormat::Twelve->value => __('platform::branding.time_format.h12'),
                                TimeFormat::TwentyFour->value => __('platform::branding.time_format.h24'),
                            ])
                            ->inline()->required(),
                        // The academy's own zone: the owner alone changes it (SaveAcademyTimezone); the others read it.
                        Select::make('default_timezone')
                            ->label(__('platform::branding.fields.default_timezone'))
                            ->helperText(fn (): string => Gate::allows(Ability::ManageAcademyTimezone->value)
                                ? __('platform::branding.timezone.hint')
                                : __('platform::branding.timezone.owner_only'))
                            ->options(fn (): array => Timezones::options())
                            ->searchable()
                            ->required()
                            ->disabled(fn (): bool => ! Gate::allows(Ability::ManageAcademyTimezone->value)),
                    ]),

                Section::make(__('platform::branding.sections.colours'))
                    ->description(fn () => $this->customAllowed() ? __('platform::branding.colours_hint') : __('platform::branding.custom_not_in_plan'))
                    ->disabled(fn () => ! $this->customAllowed())
                    ->schema([
                        Grid::make(2)->schema(array_map(
                            fn (string $token) => ColorPicker::make($token)
                                ->label(__("platform::branding.fields.{$token}"))
                                ->helperText(__("platform::branding.usage.{$token}"))
                                ->hex()
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->regex('/^#[0-9A-Fa-f]{6}$/')
                                ->required()
                                ->live(debounce: 250),
                            BrandTokens::COLOURS,
                        )),
                        ToggleButtons::make('neutral')
                            ->label(__('platform::branding.fields.neutral'))
                            ->options(collect(BrandTokens::NEUTRALS)->mapWithKeys(fn ($n) => [$n => __("platform::branding.neutrals.{$n}")])->all())
                            ->inline()->required()->live(),
                    ]),

                Section::make(__('platform::branding.sections.type'))
                    ->disabled(fn () => ! $this->customAllowed())
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('heading_font')->label(__('platform::branding.fields.heading_font'))
                                ->options(BrandFonts::options())->required()->native(false)->live(),
                            Select::make('body_font')->label(__('platform::branding.fields.body_font'))
                                ->options(BrandFonts::options())->required()->native(false)->live(),
                        ]),
                        ToggleButtons::make('radius')
                            ->label(__('platform::branding.fields.radius'))
                            ->options(collect(BrandTokens::RADII)->mapWithKeys(fn ($r) => [$r => __("platform::branding.radii.{$r}")])->all())
                            ->inline()->required()->live(),
                    ]),
            ]);
    }

    /** Loads a template into the editor; nothing changes for anyone until "Save and apply". */
    public function pickTemplate(string $ref): void
    {
        $template = app(TemplateCatalog::class)->find($ref);
        if ($template === null) {
            return;
        }

        $this->baseRef = $ref;
        $this->data = array_merge($this->data, $template['tokens']->toArray());
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $actor = auth()->id();

        app(UpdateAcademyIdentity::class)->handle($data['name_ar'], $data['name_en'], $data['logo'] ?? null, $data['favicon'] ?? null, $actor);
        app(SaveAcademyTimeFormat::class)->handle((string) ($data['time_format'] ?? TimeFormat::DEFAULT->value));
        if (Gate::allows(Ability::ManageAcademyTimezone->value) && is_string($data['default_timezone'] ?? null)) {
            app(SaveAcademyTimezone::class)->handle($data['default_timezone']);
        }
        app(SaveBranding::class)->handle($this->tokens(), $this->baseRef, $actor);

        Notification::make()->success()->title(__('platform::branding.saved'))->send();
        $this->redirect(self::getUrl());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveAsTemplate')
                ->visible(fn () => $this->customAllowed())
                ->label(__('platform::branding.actions.save_as_template'))
                ->icon(Heroicon::OutlinedBookmark)
                ->color('gray')
                ->schema([
                    TextInput::make('name')->label(__('platform::branding.fields.template_name'))->required()->maxLength(60),
                ])
                ->modalDescription(__('platform::branding.save_as_template_hint'))
                ->action(function (array $data): void {
                    $this->form->validate();
                    app(SaveBrandTemplate::class)->handle($data['name'], $this->tokens(), auth()->id());
                    Notification::make()->success()->title(__('platform::branding.template_saved', ['name' => $data['name']]))->send();
                    $this->redirect(self::getUrl());
                }),

            Action::make('updateTemplate')
                ->label(fn () => __('platform::branding.actions.update_template', ['name' => $this->baseTemplate()['name'] ?? '']))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn () => $this->customAllowed() && $this->isSavedBase() && $this->isModified())
                ->requiresConfirmation()
                ->modalDescription(__('platform::branding.update_template_hint'))
                ->action(function (): void {
                    $this->form->validate();
                    app(UpdateBrandTemplate::class)->handle(substr((string) $this->baseRef, 6), $this->tokens(), actorId: auth()->id());
                    Notification::make()->success()->title(__('platform::branding.template_updated'))->send();
                    $this->redirect(self::getUrl());
                }),

            Action::make('save')
                ->label(__('platform::branding.actions.save'))
                ->icon(Heroicon::OutlinedCheck)
                ->action(fn () => $this->save()),
        ];
    }

    public function deleteTemplateAction(): Action
    {
        return Action::make('deleteTemplate')
            ->label(__('platform::branding.actions.delete_template'))
            ->iconButton()
            ->icon(Heroicon::OutlinedTrash)
            ->size('sm')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(fn (array $arguments) => __('platform::branding.delete_heading', ['name' => app(TemplateCatalog::class)->find($arguments['ref'] ?? null)['name'] ?? '']))
            ->modalDescription(__('platform::branding.delete_hint'))
            ->action(function (array $arguments): void {
                $template = app(TemplateCatalog::class)->savedTemplate($arguments['ref'] ?? null);
                if ($template === null) {
                    return;
                }
                app(DeleteBrandTemplate::class)->handle($template->id);
                if ($this->baseRef === $template->ref()) {
                    $this->baseRef = null;
                }
                Notification::make()->success()->title(__('platform::branding.template_deleted'))->send();
            });
    }

    /** A look of the academy's own (edited colours and fonts, templates it saves) is an area of its plan. */
    public function customAllowed(): bool
    {
        return app(AcademyPlan::class)->has(Feature::CustomBranding);
    }

    /** @return list<array{ref: string, name: string, swatches: list<string>, fonts: string, builtIn: bool, active: bool, base: bool}> */
    public function templates(): array
    {
        $applied = app(BrandingApi::class)->current();

        return array_map(fn (array $t) => [
            'ref' => $t['ref'],
            'name' => $t['name'],
            'swatches' => [$t['tokens']->primary, $t['tokens']->attention, $t['tokens']->danger, $t['tokens']->info],
            'fonts' => $t['tokens']->headingFont === $t['tokens']->bodyFont ? $t['tokens']->headingFont : $t['tokens']->headingFont.' + '.$t['tokens']->bodyFont,
            'builtIn' => $t['builtIn'],
            'active' => $applied->templateRef === $t['ref'],
            'base' => $this->baseRef === $t['ref'],
        ], array_values(array_filter(app(TemplateCatalog::class)->all(), fn (array $t) => $t['builtIn'] || $this->customAllowed())));
    }

    /** The tokens on screen, or the last valid ones while a field is mid-edit. */
    public function previewTokens(): BrandTokens
    {
        try {
            return $this->tokens();
        } catch (\InvalidArgumentException) {
            return BrandTokens::fromArray(app(BrandingApi::class)->current()->tokens);
        }
    }

    /** @return list<string> Readability problems with the colours on screen. */
    public function contrastWarnings(): array
    {
        $warnings = [];
        foreach (['primary', 'danger'] as $token) {
            $hex = $this->data[$token] ?? null;
            if (BrandTokens::isHex($hex) && BrandTokens::contrastWithWhite($hex) < 4.5) {
                $warnings[] = __('platform::branding.contrast_warning', [
                    'colour' => __("platform::branding.fields.{$token}"),
                    'ratio' => BrandTokens::contrastWithWhite($hex),
                ]);
            }
        }

        return $warnings;
    }

    public function isModified(): bool
    {
        $base = $this->baseTemplate();

        try {
            return $base === null || ! $base['tokens']->equals($this->tokens());
        } catch (\InvalidArgumentException) {
            return true;
        }
    }

    public function baseTemplate(): ?array
    {
        return app(TemplateCatalog::class)->find($this->baseRef);
    }

    public function fontsUrl(): string
    {
        $t = $this->previewTokens();

        return BrandFonts::stylesheetUrl($t->headingFont, $t->bodyFont);
    }

    public function defaultRef(): string
    {
        return 'builtin:'.BuiltInTemplates::DEFAULT;
    }

    private function isSavedBase(): bool
    {
        return is_string($this->baseRef) && str_starts_with($this->baseRef, 'saved:');
    }

    private function tokens(): BrandTokens
    {
        return BrandTokens::fromArray($this->data ?? []);
    }
}
