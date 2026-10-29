<?php

namespace Modules\Identity\Filament\Console\Pages;

use BackedEnum;
use DateTimeZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Modules\Identity\Actions\OnboardAcademy;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\DTOs\NewAcademy as NewAcademyData;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * A new academy with its owner, from the platform console (docs/architecture/01,
 * section 1: "إنشاء أكاديمية"), on the plan chosen (the default plan unless
 * another is). Opened from a request to join (?request=, the platform's
 * page), it starts filled in from it, and the request is marked made. The
 * owner's welcome link is shown once to pass on; the academy's page opens
 * from here.
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

    public ?array $data = [];

    /** The request to join this academy comes from, if any. */
    #[Url]
    public ?string $request = null;

    /** @var array{name: string, link: string, page: string}|null */
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
        $fill = ['base_currency' => 'SAR', 'default_timezone' => 'Asia/Riyadh', 'default_locale' => 'ar', 'country' => 'SA', 'plan' => null];
        $asked = $this->request !== null && preg_match('/^[0-9a-f-]{36}$/', $this->request) === 1 ? app(ConsoleApi::class)->signupRequest($this->request) : null;
        if ($asked !== null && $asked->status !== 'created') {
            $plans = app(ConsoleApi::class)->offeredPlans();
            $fill = [
                'name_ar' => $asked->academyName,
                'name_en' => $asked->academyName,
                'owner_name' => $asked->contactName,
                'owner_email' => $asked->email,
                'owner_phone' => $asked->phone,
                'country' => $asked->country ?? $fill['country'],
                'default_locale' => $asked->locale,
                'plan' => $asked->planCode !== null && isset($plans[$asked->planCode]) ? $asked->planCode : null,
            ] + $fill;
        } else {
            $this->request = null;
        }
        $this->form->fill($fill);
    }

    public function form(Schema $schema): Schema
    {
        $l = 'identity::onboarding.';
        $domain = (string) config('platform.domain');

        return $schema->statePath('data')->components([
            Section::make(__($l.'sections.academy'))->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('name_ar')->label(__($l.'fields.name_ar'))->required()->maxLength(120),
                    TextInput::make('name_en')->label(__($l.'fields.name_en'))->required()->maxLength(120),
                    TextInput::make('slug')->label(__($l.'fields.slug'))->required()->suffix('.'.$domain)->extraAttributes(['dir' => 'ltr'])
                        ->helperText(__($l.'fields.slug_hint'))->regex('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/'),
                    Select::make('base_currency')->label(__($l.'fields.currency'))->required()->native(false)
                        ->options(array_combine(self::CURRENCIES, self::CURRENCIES)),
                    Select::make('default_timezone')->label(__($l.'fields.timezone'))->required()->searchable()
                        ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), array_map(fn ($z) => str_replace('_', ' ', $z), DateTimeZone::listIdentifiers()))),
                    TextInput::make('country')->label(__($l.'fields.country'))->helperText(__($l.'fields.country_hint'))->maxLength(2)->regex('/^[A-Za-z]{2}$/')->extraInputAttributes(['dir' => 'ltr']),
                    ToggleButtons::make('default_locale')->label(__($l.'fields.locale'))->inline()->required()
                        ->options(['ar' => __($l.'locales.ar'), 'en' => __($l.'locales.en')]),
                    Select::make('plan')->label(__($l.'fields.plan'))->placeholder(__($l.'fields.plan_default'))->native(false)
                        ->options(fn (): array => app(ConsoleApi::class)->offeredPlans()),
                ]),
            ]),
            Section::make(__($l.'sections.owner'))->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('owner_name')->label(__($l.'fields.owner_name'))->required()->maxLength(255),
                    TextInput::make('owner_email')->label(__($l.'fields.owner_email'))->required()->email()->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('owner_phone')->label(__($l.'fields.owner_phone'))->tel()->extraInputAttributes(['dir' => 'ltr']),
                ]),
            ]),
        ]);
    }

    public function create(): void
    {
        $d = $this->form->getState();
        try {
            $result = $this->onboard($d);
        } catch (ValidationException $e) {
            // The action names its fields (owner_phone, slug…); the form holds them under data.
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn (array $messages, string $field) => ['data.'.$field => $messages])->all());
        }

        $academy = $result['academy'];
        if ($this->request !== null) {
            app(ConsoleApi::class)->signupRequestCreated($this->request, $academy->id);
            $this->request = null;
        }
        $this->done = [
            'name' => app()->getLocale() === 'ar' ? $academy->nameAr : $academy->nameEn,
            'link' => $result['link'],
            'page' => app(ConsoleApi::class)->consoleUrl('academies/'.$academy->id),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{academy: TenantRef, owner_id: string, link: string}
     */
    private function onboard(array $d): array
    {
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
            $d['owner_phone'] ?? null,
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
}
