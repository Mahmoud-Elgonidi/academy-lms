<?php

namespace Modules\Site\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Filament\NavGroup;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Actions\SaveSiteSettings;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SiteSettings as Settings;
use UnitEnum;

/**
 * Settings › The academy's page (site.manage: the owner and admins): on or
 * off, the teachers' cards (each teacher writes theirs; the academy may take
 * any off), and its free trials (who books them, how soon the academy
 * answers, the subject for "help me choose"). What the page says and how it
 * looks is built in Settings › Page design (SiteBuilder). The requests it
 * brings are under "Page requests".
 *
 * @property-read Schema $form
 */
final class SiteSettings extends Page
{
    protected string $view = 'site::filament.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 909;

    protected static ?string $slug = 'settings/site';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageSite->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavGroup::Settings;
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
        $this->form->fill(self::state(Settings::current()));
    }

    /** @return array<string, mixed> the form's state from what is saved */
    private static function state(Settings $settings): array
    {
        return ['enabled' => $settings->enabled, 'trial_booking' => $settings->trial_booking, 'response_hours' => $settings->response_hours, 'trial_notice_hours' => $settings->trial_notice_hours,
            'default_trial_subject_id' => $settings->default_trial_subject_id];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('design')->label(__('site::site.settings.design'))->icon(Heroicon::OutlinedPaintBrush)->url(fn () => SiteBuilder::getUrl()),
            Action::make('open')->label(__('site::site.settings.open'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => self::pageUrl(app()->getLocale() === 'en' ? 'en' : 'ar'))->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $s = 'site::site.settings.';

        return $schema->statePath('data')->components([
            Tabs::make('site')->persistTabInQueryString('tab')->tabs([
                Tab::make(__($s.'tabs.page'))->id('page')->icon(Heroicon::OutlinedSparkles)->schema([
                    Toggle::make('enabled')->label(__($s.'enabled'))->helperText(__($s.'enabled_hint')),
                    // The words, sections and look are the builder's now (Settings › Page design).
                    Callout::make(__($s.'design_title'))
                        ->description(fn (): string => __($s.'design_hint', ['audience' => __($s.'audiences.'.$this->audience())]))
                        ->icon(Heroicon::OutlinedPaintBrush)->color('primary')
                        ->actions([Action::make('design')->label(__($s.'design'))->icon(Heroicon::OutlinedPaintBrush)->url(fn () => SiteBuilder::getUrl())]),
                ]),
                Tab::make(__($s.'tabs.teachers'))->id('teachers')->icon(Heroicon::OutlinedAcademicCap)->schema([
                    View::make('site::filament.teacher-cards')->viewData(fn (): array => [
                        'cards' => app(PeopleApi::class)->teacherCards(),
                        'off' => ! $this->showsTeachers(),
                    ]),
                ]),
                Tab::make(__($s.'tabs.trials'))->id('trials')->icon(Heroicon::OutlinedCalendarDays)->schema([
                    Section::make(__($s.'trials.title'))->description(__($s.'trials.hint'))->schema([
                        Radio::make('trial_booking')->label(__($s.'trials.booking'))->required()
                            ->options(['family' => __($s.'trials.booking_family'), 'staff' => __($s.'trials.booking_staff')])
                            ->descriptions(['family' => __($s.'trials.booking_family_hint'), 'staff' => __($s.'trials.booking_staff_hint')]),
                        TextInput::make('response_hours')->label(__($s.'trials.response_hours'))->helperText(__($s.'trials.response_hours_hint'))
                            ->numeric()->integer()->minValue(1)->maxValue(72)->required(),
                        TextInput::make('trial_notice_hours')->label(__($s.'trials.notice_hours'))->helperText(__($s.'trials.notice_hours_hint'))
                            ->numeric()->integer()->minValue(1)->maxValue(72)->required(),
                        Select::make('default_trial_subject_id')->label(__($s.'trials.default_subject'))->helperText(__($s.'trials.default_subject_hint'))
                            ->options(fn (): array => app(CatalogApi::class)->subjectNames())->placeholder('—'),
                    ]),
                ]),
            ]),
        ]);
    }

    /** Whom the page speaks to, as its draft says (the builder sets it). */
    private function audience(): string
    {
        return (string) (SitePage::current()->draftDoc()['settings']['audience'] ?? 'families');
    }

    /** Whether the published page has a teachers block that shows. */
    private function showsTeachers(): bool
    {
        $page = SitePage::current();
        foreach (($page->publishedDoc() ?? $page->draftDoc())['blocks'] as $block) {
            if ($block['type'] === 'teachers' && ! $block['hidden']) {
                return true;
            }
        }

        return false;
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
        $this->form->fill(self::state($kept));
        Notification::make()->success()->title(__('site::site.settings.saved'))->send();
    }
}
