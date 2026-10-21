<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\Site\SaveSiteContent;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Site\PlatformSite;
use Modules\Platform\Site\SiteContent;

/**
 * What the platform's page says (owners only): its opening words, numbers,
 * what academies said, questions and answers, which sections show, how to
 * reach the team and the demo. The plans it shows come from Plans; the
 * requests it brings are under Signup requests.
 *
 * @property-read Schema $form
 */
final class SitePage extends Page
{
    protected string $view = 'platform::console.site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'site';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_owner && $admin->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::site.console.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::site.console.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::site.console.subtitle');
    }

    public static function pageUrl(string $locale = 'ar'): string
    {
        return rtrim((string) config('app.url'), '/').'/'.$locale.'/platform';
    }

    public function mount(): void
    {
        $this->form->fill(PlatformSite::content());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')->label(__('platform::site.console.open'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(self::pageUrl(app()->getLocale() === 'en' ? 'en' : 'ar'))->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $l = 'platform::site.fields.';
        $pair = fn (string $name, int $max, bool $long = false, bool $required = false) => [
            ($long ? Textarea::make("{$name}_ar")->rows(3) : TextInput::make("{$name}_ar"))->label(__($l."{$this->base($name)}_ar"))->maxLength($max)->required($required),
            ($long ? Textarea::make("{$name}_en")->rows(3) : TextInput::make("{$name}_en"))->label(__($l."{$this->base($name)}_en"))->maxLength($max)->required($required)->extraInputAttributes(['dir' => 'ltr']),
        ];

        return $schema->statePath('data')->components([
            Tabs::make('site')->persistTabInQueryString('tab')->tabs([
                Tab::make(__('platform::site.console.tabs.hero'))->id('hero')->icon(Heroicon::OutlinedSparkles)->schema([
                    Section::make(__('platform::site.console.hero'))->description(__('platform::site.console.hero_hint'))->columns(2)->schema([
                        ...$pair('hero.title', 120, required: true),
                        ...$pair('hero.lead', 300, long: true, required: true),
                        TextInput::make('demo_url')->label(__($l.'demo_url'))->helperText(__($l.'demo_url_hint'))->url()->maxLength(300)->columnSpanFull()->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                ]),
                Tab::make(__('platform::site.console.tabs.stats'))->id('stats')->icon(Heroicon::OutlinedChartBar)->schema([
                    Repeater::make('stats')->label(__('platform::site.console.stats'))->helperText(__('platform::site.console.stats_hint'))
                        ->maxItems(SiteContent::MAX_STATS)->columns(3)->reorderable()->addActionLabel(__('platform::site.console.add'))
                        ->schema([
                            TextInput::make('value')->label(__($l.'value'))->required()->maxLength(12)->extraInputAttributes(['dir' => 'ltr']),
                            ...$pair('label', 40, required: true),
                        ]),
                ]),
                Tab::make(__('platform::site.console.tabs.testimonials'))->id('testimonials')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->schema([
                    Repeater::make('testimonials')->label(__('platform::site.console.testimonials'))->helperText(__('platform::site.console.testimonials_hint'))
                        ->maxItems(SiteContent::MAX_TESTIMONIALS)->columns(2)->reorderable()->collapsible()->addActionLabel(__('platform::site.console.add'))
                        ->schema([
                            TextInput::make('name')->label(__($l.'name'))->required()->maxLength(60)->columnSpanFull(),
                            ...$pair('role', 60),
                            ...$pair('quote', 400, long: true, required: true),
                        ]),
                ]),
                Tab::make(__('platform::site.console.tabs.faqs'))->id('faqs')->icon(Heroicon::OutlinedQuestionMarkCircle)->schema([
                    Repeater::make('faqs')->label(__('platform::site.console.faqs'))->maxItems(SiteContent::MAX_FAQS)->columns(2)->reorderable()->collapsible()
                        ->addActionLabel(__('platform::site.console.add'))
                        ->schema([...$pair('q', 160, required: true), ...$pair('a', 800, long: true, required: true)]),
                ]),
                Tab::make(__('platform::site.console.tabs.sections'))->id('sections')->icon(Heroicon::OutlinedSquares2x2)->schema([
                    CheckboxList::make('sections')->label(__('platform::site.console.sections'))->helperText(__('platform::site.console.sections_hint'))
                        ->options(collect(SiteContent::SECTIONS)->mapWithKeys(fn (string $s) => [$s => __('platform::site.sections.'.$s)])->all())->columns(2),
                ]),
                Tab::make(__('platform::site.console.tabs.contact'))->id('contact')->icon(Heroicon::OutlinedEnvelope)->schema([
                    Section::make(__('platform::site.console.contact'))->columns(2)->schema([
                        TextInput::make('contact.whatsapp')->label(__($l.'whatsapp'))->helperText(__($l.'whatsapp_hint'))->tel()->maxLength(20)->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('contact.email')->label(__($l.'email'))->email()->maxLength(190)->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                    Section::make(__('platform::site.console.seo'))->description(__('platform::site.console.seo_hint'))->columns(2)->schema([
                        ...$pair('seo.title', 70),
                        ...$pair('seo.description', 170, long: true),
                    ]),
                ]),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $admin = auth('platform')->user();
        assert($admin instanceof PlatformAdmin);
        try {
            $kept = app(SaveSiteContent::class)->handle($admin, $this->form->getState());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(__('platform::site.console.not_saved'))->body(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->form->fill($kept);
        Notification::make()->success()->title(__('platform::site.console.saved'))->send();
    }

    /** "hero.title" → "title": the wording is the field's, wherever it sits. */
    private function base(string $name): string
    {
        return str_contains($name, '.') ? substr($name, (int) strrpos($name, '.') + 1) : $name;
    }
}
