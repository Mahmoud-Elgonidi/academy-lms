<?php

namespace Modules\Identity\Filament\Console\Pages;

use BackedEnum;
use Closure;
use DateTimeZone;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Modules\Identity\Actions\OnboardAcademy;
use Modules\Identity\Domain\Onboarding\CountryDefaults;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\DTOs\NewAcademy as NewAcademyData;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Geo\Countries;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * A new academy with its owner, from the platform console (docs/architecture/01,
 * section 1: "إنشاء أكاديمية"), in steps: the academy (its names, and the
 * address made from the English name, checked as it is typed), its country
 * (which fills in the currency, the clock and the language: Domain\Onboarding\
 * CountryDefaults), the owner and the plan (each with its limits), then a
 * review. It starts on the plan chosen (the default plan unless another is).
 * Opened from a request to join (?request=, the platform's page), it starts
 * filled in from it, and the request is marked made. The owner's welcome link
 * is shown once to pass on, with what the address still needs on the server;
 * the academy's page opens from here.
 *
 * @property-read Schema $form
 */
final class NewAcademy extends Page
{
    protected string $view = 'identity::console.new-academy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'new-academy';

    /** Currencies the billing engine handles today; more come with their gateways. */
    public const CURRENCIES = ['SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'EGP', 'JOD', 'USD', 'GBP', 'EUR'];

    /** An academy's address: lowercase letters, digits and dashes, 3 to 40 (Platform's CreateAcademy holds the same). */
    public const SLUG = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

    public ?array $data = [];

    /** The request to join this academy comes from, if any. */
    #[Url]
    public ?string $request = null;

    /** @var array{name: string, link: string, page: string, host: string, domain: string}|null */
    public ?array $done = null;

    public static function getNavigationLabel(): string
    {
        return __('identity::onboarding.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::onboarding.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('identity::onboarding.subtitle');
    }

    public function mount(): void
    {
        $fill = ['country' => 'SA', 'plan' => null];
        $asked = $this->request !== null && preg_match('/^[0-9a-f-]{36}$/', $this->request) === 1 ? app(ConsoleApi::class)->signupRequest($this->request) : null;
        if ($asked !== null && $asked->status !== 'created') {
            $plans = app(ConsoleApi::class)->offeredPlans();
            $country = $asked->country !== null ? strtoupper($asked->country) : $fill['country'];
            $fill = [
                'name_ar' => $asked->academyName,
                'name_en' => $asked->academyName,
                'slug' => self::slugFrom($asked->academyName),
                'owner_name' => $asked->contactName,
                'owner_email' => $asked->email,
                'owner_phone' => $asked->phone,
                'country' => $country,
                'plan' => $asked->planCode !== null && isset($plans[$asked->planCode]) ? $asked->planCode : null,
            ];
            $fill = self::countryFill($country) + ['base_currency' => 'USD', 'default_timezone' => 'UTC'] + $fill;
            // The request's own language wins over the country's.
            if (in_array($asked->locale, ['ar', 'en'], true)) {
                $fill['default_locale'] = $asked->locale;
            }
        } else {
            $this->request = null;
            $fill += self::countryFill($fill['country']);
        }
        $this->form->fill($fill);
    }

    public function form(Schema $schema): Schema
    {
        $l = 'identity::onboarding.';

        return $schema->statePath('data')->components([
            Wizard::make([
                Step::make(__($l.'steps.academy'))->key('academy')->icon(Heroicon::OutlinedBuildingLibrary)
                    ->description(__($l.'steps.academy_hint'))
                    ->schema([Grid::make(['default' => 1, 'md' => 2])->schema(self::academyFields())]),
                Step::make(__($l.'steps.country'))->key('country')->icon(Heroicon::OutlinedGlobeAlt)
                    ->description(__($l.'steps.country_hint'))
                    ->schema([Grid::make(['default' => 1, 'md' => 2])->schema(self::countryFields())]),
                Step::make(__($l.'steps.owner'))->key('owner')->icon(Heroicon::OutlinedUserCircle)
                    ->description(__($l.'steps.owner_hint'))
                    ->schema(self::ownerFields()),
                Step::make(__($l.'steps.review'))->key('review')->icon(Heroicon::OutlinedCheckCircle)
                    ->description(__($l.'steps.review_hint'))
                    ->schema([View::make('identity::console.new-academy-review')->viewData(fn (Get $get): array => ['rows' => self::review($get)])]),
            ])
                ->key('onboarding')
                ->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit" icon="heroicon-o-plus">{{ $label }}</x-filament::button>', ['label' => __($l.'create')]))),
        ]);
    }

    /** @return list<Component> */
    private static function academyFields(): array
    {
        $l = 'identity::onboarding.';
        $domain = (string) config('platform.domain');

        return [
            TextInput::make('name_ar')->label(__($l.'fields.name_ar'))->required()->maxLength(120),
            TextInput::make('name_en')->label(__($l.'fields.name_en'))->required()->maxLength(120)
                ->live(onBlur: true)
                // The address follows the English name until someone writes their own.
                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                    $slug = (string) $get('slug');
                    if ($slug === '' || $slug === self::slugFrom((string) $old)) {
                        $set('slug', self::slugFrom((string) $state));
                    }
                }),
            TextInput::make('slug')->label(__($l.'fields.slug'))->required()->suffix('.'.$domain)
                ->extraAttributes(['dir' => 'ltr'])->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => 'off'])
                ->live(debounce: 500)
                ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state)))
                ->helperText(fn (Get $get): string => self::slugHint((string) $get('slug')))
                ->regex(self::SLUG)
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && app(ConsoleApi::class)->slugTaken($value)) {
                        $fail(__('identity::onboarding.slug.taken'));
                    }
                }])
                ->columnSpanFull(),
        ];
    }

    /** @return list<Component> */
    private static function countryFields(): array
    {
        $l = 'identity::onboarding.';

        return [
            Select::make('country')->label(__($l.'fields.country'))->helperText(__($l.'fields.country_hint'))
                ->required()->searchable()->native(false)
                ->options(fn (Get $get): array => self::countryOptions((string) $get('country')))
                ->live()
                // The country fills in the rest; each stays editable.
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    foreach (self::countryFill($state) as $field => $value) {
                        $set($field, $value);
                    }
                })
                ->columnSpanFull(),
            Select::make('base_currency')->label(__($l.'fields.currency'))->required()->native(false)
                ->options(array_combine(self::CURRENCIES, self::CURRENCIES)),
            Select::make('default_timezone')->label(__($l.'fields.timezone'))->required()->searchable()
                ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), array_map(fn ($z) => str_replace('_', ' ', $z), DateTimeZone::listIdentifiers()))),
            ToggleButtons::make('default_locale')->label(__($l.'fields.locale'))->inline()->required()
                ->options(['ar' => __($l.'locales.ar'), 'en' => __($l.'locales.en')]),
        ];
    }

    /** @return list<Component> */
    private static function ownerFields(): array
    {
        $l = 'identity::onboarding.';

        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('owner_name')->label(__($l.'fields.owner_name'))->required()->maxLength(255),
                TextInput::make('owner_email')->label(__($l.'fields.owner_email'))->required()->email()
                    ->validationMessages(['email' => __($l.'errors.owner_email')])
                    ->extraInputAttributes(['dir' => 'ltr']),
            ]),
            Grid::make(['default' => 1, 'md' => 3])->schema([
                Select::make('owner_dial')->label(__($l.'fields.owner_dial'))->searchable()->native(false)
                    ->options(fn (): array => self::dialOptions()),
                TextInput::make('owner_phone')->label(__($l.'fields.owner_phone'))->helperText(__($l.'fields.owner_phone_hint'))
                    ->tel()->maxLength(30)->extraInputAttributes(['dir' => 'ltr'])
                    ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if (filled($value) && self::phone((string) $value, $get('owner_dial')) === null) {
                            $fail(__('identity::onboarding.errors.owner_phone'));
                        }
                    }])
                    ->columnSpan(['md' => 2]),
            ]),
            Radio::make('plan')->label(__($l.'fields.plan'))->helperText(__($l.'fields.plan_hint'))
                ->options(fn (): array => app(ConsoleApi::class)->offeredPlans())
                ->descriptions(fn (): array => array_map(self::limitsLine(...), app(ConsoleApi::class)->offeredPlanLimits()))
                ->visible(fn (): bool => app(ConsoleApi::class)->offeredPlans() !== []),
        ];
    }

    public function create(): void
    {
        try {
            $d = $this->form->getState();
            $result = $this->onboard($d);
        } catch (ValidationException $e) {
            $this->refuse($e);

            return;
        }

        $academy = $result['academy'];
        if ($this->request !== null) {
            app(ConsoleApi::class)->signupRequestCreated($this->request, $academy->id);
            $this->request = null;
        }
        $domain = (string) config('platform.domain');
        $this->done = [
            'name' => app()->getLocale() === 'ar' ? $academy->nameAr : $academy->nameEn,
            'link' => $result['link'],
            'page' => app(ConsoleApi::class)->consoleUrl('academies/'.$academy->id),
            'host' => $academy->slug.'.'.$domain,
            'domain' => $domain,
        ];
    }

    /**
     * Each refusal on its own field (the actions name them: owner_phone, slug…; the form holds them under data),
     * and the step that asks it opened.
     */
    private function refuse(ValidationException $e): void
    {
        $first = null;
        foreach ($e->errors() as $field => $messages) {
            $field = Str::after((string) $field, 'data.');
            $first ??= $field;
            foreach ($messages as $message) {
                $this->addError('data.'.$field, $message);
            }
        }
        $wizard = $this->form->getComponent(fn ($c): bool => $c instanceof Wizard);
        if ($first === null || ! $wizard instanceof Wizard) {
            return;
        }
        foreach ($wizard->getChildSchema()->getComponents() as $component) {
            $asks = $component instanceof Step && $component->getChildSchema()
                ->getComponent(fn ($c): bool => $c instanceof Field && $c->getName() === $first, withHidden: true) !== null;
            if ($asks) {
                $this->dispatch('go-to-wizard-step', key: $wizard->getKey(), step: $component->getKey());

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{academy: TenantRef, owner_id: string, link: string}
     */
    private function onboard(array $d): array
    {
        $phone = trim((string) ($d['owner_phone'] ?? ''));

        return app(OnboardAcademy::class)->handle(
            new NewAcademyData(
                slug: strtolower(trim((string) $d['slug'])),
                nameAr: trim((string) $d['name_ar']),
                nameEn: trim((string) $d['name_en']),
                baseCurrency: (string) $d['base_currency'],
                defaultTimezone: (string) $d['default_timezone'],
                defaultLocale: (string) $d['default_locale'],
                country: filled($d['country'] ?? null) ? strtoupper((string) $d['country']) : null,
                plan: filled($d['plan'] ?? null) ? (string) $d['plan'] : null,
            ),
            (string) $d['owner_name'],
            (string) $d['owner_email'],
            // With the country code chosen ("+90…"); one OnboardAcademy cannot read comes back on the field.
            $phone === '' ? null : (self::phone($phone, $d['owner_dial'] ?? null) ?? $phone),
        );
    }

    public function another(): void
    {
        $this->done = null;
        $this->mount();
    }

    public function linkDays(): int
    {
        return WelcomeController::LINK_DAYS;
    }

    /** An address from a name: "Jeyad Academy" → "jeyad-academy", held to the address's rule (else empty). */
    public static function slugFrom(string $name): string
    {
        $slug = trim(substr(Str::slug(Str::transliterate($name)), 0, 40), '-');

        return preg_match(self::SLUG, $slug) === 1 ? $slug : '';
    }

    /** The owner's mobile with the country code chosen ("+905…"), or null when it cannot be a number. */
    public static function phone(string $number, mixed $country): ?string
    {
        return Phone::normalize($number, CountryDefaults::dial(is_string($country) ? $country : null));
    }

    /** @return array{base_currency?: string, default_timezone?: string, default_locale?: string, owner_dial?: string} */
    private static function countryFill(?string $country): array
    {
        $defaults = CountryDefaults::of($country);

        return $defaults === null ? [] : [
            'base_currency' => in_array($defaults['currency'], self::CURRENCIES, true) ? $defaults['currency'] : 'USD',
            'default_timezone' => $defaults['timezone'],
            'default_locale' => $defaults['locale'],
            'owner_dial' => strtoupper((string) $country),
        ];
    }

    /** What the address will be, and whether it is free, as it is typed. */
    private static function slugHint(string $slug): string
    {
        $l = 'identity::onboarding.';
        $slug = strtolower(trim($slug));
        if (preg_match(self::SLUG, $slug) !== 1) {
            return __($l.'fields.slug_hint');
        }
        $preview = __($l.'slug.preview', ['address' => "\u{2066}https://{$slug}.".config('platform.domain')."\u{2069}"]);

        return $preview.' · '.__(app(ConsoleApi::class)->slugTaken($slug) ? $l.'slug.taken' : $l.'slug.free');
    }

    /** @return array<string, string> the countries by name (in Arabic with the English name beside it, to find either), and one a request named outside them */
    private static function countryOptions(string $current): array
    {
        $locale = app()->getLocale();
        $options = [];
        foreach (Countries::options($locale) as $code => $name) {
            $options[$code] = $locale === 'en' ? $name : $name.' · '.Countries::name($code, 'en');
        }
        if ($current !== '' && ! isset($options[$current]) && preg_match('/^[A-Z]{2}$/', $current) === 1) {
            $options[$current] = Countries::name($current, $locale);
        }

        return $options;
    }

    /** @return array<string, string> country => "name (+code)", by name */
    private static function dialOptions(): array
    {
        $options = [];
        foreach (Countries::options(app()->getLocale()) as $code => $name) {
            if (($dial = CountryDefaults::dial($code)) !== null) {
                $options[$code] = "{$name} (\u{2066}+{$dial}\u{2069})";
            }
        }

        return $options;
    }

    /** @param  array<string, ?int>  $limits  one plan's limits as one line */
    private static function limitsLine(array $limits): string
    {
        $l = 'identity::onboarding.plans.';

        return collect($limits)->map(fn (?int $value, string $limit): string => __($l.'limit', [
            'label' => __('shared::plans.limits.'.$limit),
            'value' => match (true) {
                $value === null => __($l.'unlimited'),
                $limit === 'storage_mb' => __($l.'storage', ['count' => number_format($value)]),
                default => number_format($value),
            },
        ]))->implode(' · ');
    }

    /** @return list<array{label: string, value: string}> what the form holds now, as the review shows it */
    private static function review(Get $get): array
    {
        $l = 'identity::onboarding.';
        $slug = strtolower(trim((string) $get('slug')));
        $phone = trim((string) $get('owner_phone'));
        $country = (string) $get('country');
        $plans = app(ConsoleApi::class)->offeredPlans();
        $locale = (string) $get('default_locale');

        return [
            ['label' => __($l.'fields.name_ar'), 'value' => (string) $get('name_ar')],
            ['label' => __($l.'fields.name_en'), 'value' => (string) $get('name_en')],
            ['label' => __($l.'fields.slug'), 'value' => $slug === '' ? '—' : "\u{2066}{$slug}.".config('platform.domain')."\u{2069}"],
            ['label' => __($l.'fields.country'), 'value' => $country === '' ? '—' : Countries::name($country, app()->getLocale())],
            ['label' => __($l.'fields.currency'), 'value' => (string) $get('base_currency')],
            ['label' => __($l.'fields.timezone'), 'value' => str_replace('_', ' ', (string) $get('default_timezone'))],
            ['label' => __($l.'fields.locale'), 'value' => in_array($locale, ['ar', 'en'], true) ? __($l.'locales.'.$locale) : '—'],
            ['label' => __($l.'fields.owner_name'), 'value' => (string) $get('owner_name')],
            ['label' => __($l.'fields.owner_email'), 'value' => "\u{2066}".$get('owner_email')."\u{2069}"],
            ['label' => __($l.'fields.owner_phone'), 'value' => $phone === '' ? '—' : "\u{2066}".(self::phone($phone, $get('owner_dial')) ?? $phone)."\u{2069}"],
            ['label' => __($l.'fields.plan'), 'value' => $plans[(string) $get('plan')] ?? __($l.'fields.plan_default')],
        ];
    }
}
