<?php

namespace Modules\Site\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Actions\SaveSiteSettings;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings as Settings;
use UnitEnum;

/**
 * Settings › The academy's page (site.manage: the owner and admins): on or
 * off, its opening words, why families choose the academy, what families
 * said, questions and answers, which sections show, the teachers' cards
 * (each teacher writes theirs; the academy may take any off), and its
 * WhatsApp and search words. Subjects come from Catalog, prices from the
 * plans; the requests it brings are under "Page requests".
 *
 * @property-read Schema $form
 */
final class SiteSettings extends Page
{
    protected string $view = 'site::filament.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 86;

    protected static ?string $slug = 'settings/site';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageSite->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('site::site.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('site::site.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('site::site.settings.subtitle');
    }

    /** The academy's page on its own host, in this language. */
    public static function pageUrl(string $locale = 'ar'): string
    {
        return rtrim(app(TenantDirectory::class)->baseUrl(app(TenantContext::class)->require()), '/').'/'.$locale;
    }

    public function mount(): void
    {
        $settings = Settings::current();
        $this->form->fill(['enabled' => $settings->enabled] + $settings->said());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')->label(__('site::site.settings.open'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => self::pageUrl(app()->getLocale() === 'en' ? 'en' : 'ar'))->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $l = 'site::site.fields.';
        $s = 'site::site.settings.';
        $pair = fn (string $name, string $label, int $max, bool $long = false) => [
            ($long ? Textarea::make("{$name}_ar")->rows(3) : TextInput::make("{$name}_ar"))->label(__($l."{$label}_ar"))->maxLength($max),
            ($long ? Textarea::make("{$name}_en")->rows(3) : TextInput::make("{$name}_en"))->label(__($l."{$label}_en"))->maxLength($max)->extraInputAttributes(['dir' => 'ltr']),
        ];
        $sections = [];
        foreach (SiteContent::SECTIONS as $section) {
            $sections[$section] = __('site::site.sections.'.$section);
        }

        return $schema->statePath('data')->components([
            Tabs::make('site')->persistTabInQueryString('tab')->tabs([
                Tab::make(__($s.'tabs.page'))->id('page')->icon(Heroicon::OutlinedSparkles)->schema([
                    Toggle::make('enabled')->label(__($s.'enabled'))->helperText(__($s.'enabled_hint')),
                    Section::make(__($s.'hero'))->description(__($s.'hero_hint'))->columns(2)->schema([
                        ...$pair('hero.title', 'title', 120),
                        ...$pair('hero.lead', 'lead', 300, long: true),
                    ]),
                ]),
                Tab::make(__($s.'tabs.features'))->id('features')->icon(Heroicon::OutlinedStar)->schema([
                    Repeater::make('features')->label(__($s.'features'))->helperText(__($s.'features_hint'))
                        ->maxItems(SiteContent::MAX_FEATURES)->columns(2)->reorderable()->collapsible()->addActionLabel(__($s.'add'))
                        ->schema([...$pair('title', 'title', 60), ...$pair('text', 'text', 200, long: true)]),
                ]),
                Tab::make(__($s.'tabs.testimonials'))->id('testimonials')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->schema([
                    Repeater::make('testimonials')->label(__($s.'testimonials'))->helperText(__($s.'testimonials_hint'))
                        ->maxItems(SiteContent::MAX_TESTIMONIALS)->columns(2)->reorderable()->collapsible()->addActionLabel(__($s.'add'))
                        ->schema([
                            TextInput::make('name')->label(__($l.'name'))->required()->maxLength(60)->columnSpanFull(),
                            ...$pair('role', 'role', 60),
                            ...$pair('quote', 'quote', 400, long: true),
                        ]),
                ]),
                Tab::make(__($s.'tabs.faqs'))->id('faqs')->icon(Heroicon::OutlinedQuestionMarkCircle)->schema([
                    Repeater::make('faqs')->label(__($s.'faqs'))->maxItems(SiteContent::MAX_FAQS)->columns(2)->reorderable()->collapsible()
                        ->addActionLabel(__($s.'add'))
                        ->schema([...$pair('q', 'q', 160), ...$pair('a', 'a', 800, long: true)]),
                ]),
                Tab::make(__($s.'tabs.sections'))->id('sections')->icon(Heroicon::OutlinedSquares2x2)->schema([
                    CheckboxList::make('sections')->label(__($s.'sections'))->helperText(__($s.'sections_hint'))->options($sections)->columns(2),
                ]),
                Tab::make(__($s.'tabs.teachers'))->id('teachers')->icon(Heroicon::OutlinedAcademicCap)->schema([
                    View::make('site::filament.teacher-cards')->viewData(fn (): array => [
                        'cards' => app(PeopleApi::class)->teacherCards(),
                        'off' => ! in_array('teachers', (array) ($this->data['sections'] ?? []), true),
                    ]),
                ]),
                Tab::make(__($s.'tabs.contact'))->id('contact')->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                    Section::make(__($s.'contact'))->schema([
                        TextInput::make('whatsapp')->label(__($l.'whatsapp'))->helperText(__($l.'whatsapp_hint'))->tel()->maxLength(20)->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                    Section::make(__($s.'seo'))->description(__($s.'seo_hint'))->columns(2)->schema([
                        ...$pair('seo.title', 'seo_title', 70),
                        ...$pair('seo.description', 'seo_description', 170, long: true),
                    ]),
                ]),
            ]),
        ]);
    }

    /** Takes a teacher's card off the page, or puts it back (the teacher's own switch stays theirs). */
    public function cardAction(): Action
    {
        return Action::make('card')
            ->label(fn (array $arguments) => __('site::site.settings.card.'.(($arguments['hide'] ?? true) ? 'hide' : 'show')))
            ->icon(fn (array $arguments) => ($arguments['hide'] ?? true) ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
            ->color('gray')->size('sm')
            ->action(function (array $arguments): void {
                abort_unless(self::canAccess(), 403);
                $hide = (bool) ($arguments['hide'] ?? true);
                app(PeopleApi::class)->hideTeacherCard((string) ($arguments['card'] ?? ''), $hide);
                Notification::make()->success()->title(__('site::site.settings.card.'.($hide ? 'hidden_done' : 'shown_done')))->send();
            });
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        try {
            $kept = app(SaveSiteSettings::class)->handle($this->form->getState());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(__('site::site.settings.not_saved'))->body(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->form->fill(['enabled' => $kept->enabled] + $kept->said());
        Notification::make()->success()->title(__('site::site.settings.saved'))->send();
    }
}
