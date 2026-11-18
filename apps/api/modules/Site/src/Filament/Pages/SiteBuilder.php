<?php

namespace Modules\Site\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Filament\NavGroup;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Time\Clock;
use Modules\Site\Actions\ApplySiteTemplate;
use Modules\Site\Actions\DeleteSiteMedia;
use Modules\Site\Actions\DiscardSiteDraft;
use Modules\Site\Actions\PublishSitePage;
use Modules\Site\Actions\RestoreSitePage;
use Modules\Site\Actions\SaveSiteDraft;
use Modules\Site\Actions\UpdateSiteMedia;
use Modules\Site\Actions\UploadSiteMedia;
use Modules\Site\Domain\Page\Blocks;
use Modules\Site\Domain\Page\Localize;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Domain\Page\Templates;
use Modules\Site\Domain\Page\Verdict;
use Modules\Site\Filament\BuilderState;
use Modules\Site\Filament\Forms\BlockForms;
use Modules\Site\Filament\Forms\MediaPicker;
use Modules\Site\Filament\Forms\Schematics;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;
use Modules\Site\Models\SiteSettings as Settings;
use Modules\Site\Queries\MediaInUse;
use Modules\Site\Support\PageWords;
use Modules\Site\Support\PreviewLinks;
use UnitEnum;

/**
 * Settings › Page design (site.manage: the owner and admins;
 * docs/architecture/site-builder.md, 4): the academy builds its public page
 * from blocks. Sections (add, copy, hide, delete, drag to reorder; each with
 * its content in Arabic and English, its layout and its style), the page's
 * settings (audience, fonts, colours from Branding, header, footer,
 * WhatsApp, search), and the media library. The draft saves as they edit
 * (SaveSiteDraft, refused when someone else saved since); the preview
 * beside it shows the draft at desktop, tablet and phone widths through the
 * portal (a two-hour token, renewed while the screen is open). Publish,
 * discard, templates and the history each go through their own Action, the
 * same the API would call.
 *
 * @property-read Schema $blocksForm
 * @property-read Schema $settingsForm
 */
final class SiteBuilder extends Page
{
    protected string $view = 'site::filament.site-builder';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?int $navigationSort = 908;

    protected static ?string $slug = 'site/builder';

    /** @var array<string, mixed> the blocks' form state: {blocks} */
    public ?array $data = [];

    /** @var array<string, mixed> the page settings' form state */
    public ?array $settings = [];

    /** The draft revision this editor works on. */
    public int $revision = 1;

    public ?int $publishedRevision = null;

    public bool $hasPublished = false;

    public string $previewToken = '';

    public ?string $savedAt = null;

    /** The last problems told, so the same refusal is not told again on every keystroke. */
    public string $toldProblems = '';

    public string $mediaKind = 'all';

    /** The editor's tab: sections, page or media (only that one is drawn). */
    public string $tab = 'sections';

    /** The block whose form is open (its id), or none. */
    public ?string $editing = null;

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
        return __('site::builder.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('site::builder.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('site::builder.subtitle');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->load(SitePage::current());
        $this->previewToken = PreviewLinks::issue();
    }

    /** Fills the forms from a page's draft. */
    private function load(SitePage $page): void
    {
        $doc = $page->draftDoc();
        $this->revision = (int) $page->draft_revision;
        $this->publishedRevision = $page->published !== null && $page->exists ? (int) $page->published_revision : ($page->exists ? null : 1);
        $this->hasPublished = $page->published !== null;
        $this->savedAt = $page->draft_saved_at?->setTimezone(auth()->user()?->timezone ?? config('app.timezone'))->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i'));
        $this->blocksForm->fill(BuilderState::blocksToForm($doc));
        $this->settingsForm->fill(BuilderState::settingsToForm($doc['settings']));
        $this->resetErrorBag();
        $this->toldProblems = '';
    }

    // ---- Forms ----------------------------------------------------------------

    public function blocksForm(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Builder::make('blocks')
                ->hiddenLabel()
                ->blocks(BlockForms::blocks())
                ->blockPickerColumns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->blockPickerWidth(Width::FourExtraLarge)
                ->searchable()
                ->collapsible(false)
                ->cloneable()
                ->reorderableWithDragAndDrop()
                ->reorderableWithButtons()
                ->blockNumbers(false)
                ->maxItems(Blocks::MAX_BLOCKS)
                ->addActionLabel(__('site::builder.actions.add'))
                ->addBetweenAction(fn (Action $action) => $action->hidden())
                ->deleteAction(fn (Action $action) => $action->requiresConfirmation()->modalDescription(__('site::builder.block.delete_confirm')))
                ->extraItemActions([
                    Action::make('toggleHidden')
                        ->label(fn (array $arguments, Builder $component) => ($component->getRawItemState($arguments['item'])['hidden'] ?? false) ? __('site::builder.actions.show') : __('site::builder.actions.hide'))
                        ->icon(fn (array $arguments, Builder $component) => ($component->getRawItemState($arguments['item'])['hidden'] ?? false) ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
                        ->tooltip(fn (array $arguments, Builder $component) => ($component->getRawItemState($arguments['item'])['hidden'] ?? false) ? __('site::builder.actions.show') : __('site::builder.actions.hide'))
                        ->color('gray')
                        ->action(function (array $arguments, Builder $component): void {
                            $state = $component->getRawState();
                            $key = $arguments['item'];
                            $state[$key]['data']['hidden'] = ! ($state[$key]['data']['hidden'] ?? false);
                            $component->rawState($state);
                            $this->saveDraft();
                        }),
                    Action::make('focusPreview')
                        ->label(__('site::builder.actions.show_in_preview'))
                        ->icon(Heroicon::OutlinedViewfinderCircle)
                        ->tooltip(__('site::builder.actions.show_in_preview'))
                        ->color('gray')
                        ->action(function (array $arguments, Builder $component): void {
                            $item = $component->getRawState()[$arguments['item']] ?? null;
                            if (is_array($item)) {
                                $this->dispatch('site-preview-focus', anchor: PageDoc::anchor(['type' => $item['type'], 'id' => $item['data']['id'] ?? ''] + (isset($item['data']['anchor']) && $item['data']['anchor'] !== '' ? ['anchor' => $item['data']['anchor']] : [])));
                            }
                        }),
                ])
                ->afterStateUpdated(fn () => $this->saveDraft()),
        ]);
    }

    public function settingsForm(Schema $schema): Schema
    {
        $p = 'site::builder.page.';
        $save = BlockForms::save();
        $fonts = [];
        foreach (app(BrandingApi::class)->fontFamilies() as $family) {
            $fonts[$family] = $family;
        }
        $tokens = app(BrandingApi::class)->current()->tokens;
        $choice = fn (string $name, string $label, array $options) => ToggleButtons::make($name)->label($label)->inline()->options($options)->live()->afterStateUpdated($save);
        $words = fn (string $set, array $keys) => array_combine($keys, array_map(fn ($k) => __($p.$set.'.'.$k), $keys));

        return $schema->statePath('settings')->components([
            Section::make(__($p.'look'))->description(__($p.'look_hint'))->icon(Heroicon::OutlinedSwatch)
                ->headerActions([Action::make('branding')->label(__($p.'branding'))->link()->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn () => route('filament.academy.pages.settings.branding'))->visible(fn () => app('router')->has('filament.academy.pages.settings.branding'))])
                ->schema([
                    ToggleButtons::make('audience')->label(__($p.'audience'))->helperText(__($p.'audience_hint'))
                        ->options($words('audiences', PageDoc::AUDIENCES))->inline()->live()->afterStateUpdated($save),
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        Select::make('fonts.heading')->label(__($p.'font_heading'))->native(false)->live()->afterStateUpdated($save)
                            ->options($fonts)->placeholder(__($p.'brand_font', ['font' => $tokens['heading_font']])),
                        Select::make('fonts.body')->label(__($p.'font_body'))->native(false)->live()->afterStateUpdated($save)
                            ->options($fonts)->placeholder(__($p.'brand_font', ['font' => $tokens['body_font']])),
                    ]),
                    ViewField::make('accent')->label(__($p.'accent'))->view('site::filament.accent-picker')
                        ->viewData(['accents' => ['primary' => $tokens['primary'], 'attention' => $tokens['attention'], 'info' => $tokens['info']], 'names' => $words('accents', PageDoc::ACCENTS)])
                        ->live()->afterStateUpdated($save),
                    Grid::make(['default' => 1, 'lg' => 3])->schema([
                        $choice('mood', __($p.'mood'), $words('moods', PageDoc::MOODS)),
                        $choice('corners', __($p.'corners'), $words('corner_options', PageDoc::CORNERS)),
                        $choice('motion', __($p.'motion'), $words('motions', PageDoc::MOTION)),
                    ]),
                ]),
            Section::make(__($p.'header'))->icon(Heroicon::OutlinedWindow)->collapsible()->schema([
                $choice('header.style', __($p.'header_style'), $words('header_styles', PageDoc::HEADER_STYLES)),
                BlockForms::link('header.cta', __($p.'header_cta')),
                Grid::make(2)->schema([
                    Toggle::make('header.sign_in')->label(__($p.'sign_in'))->live()->afterStateUpdated($save),
                    Toggle::make('header.language_switch')->label(__($p.'language_switch'))->live()->afterStateUpdated($save),
                ]),
            ]),
            Section::make(__($p.'footer'))->description(__($p.'footer_hint'))->icon(Heroicon::OutlinedBars3BottomLeft)->collapsible()->collapsed()->schema([
                BlockForms::pair('footer.text', __($p.'footer_text'), 300, long: true),
                Repeater::make('footer.links')->label(__($p.'footer_links'))->schema([
                    BlockForms::pair('label', __('site::builder.fields.label_text'), 40),
                    TextInput::make('url')->label(__('site::builder.fields.url'))->maxLength(500)->placeholder('https://')->extraInputAttributes(['dir' => 'ltr']),
                ])->maxItems(PageDoc::MAX_FOOTER_LINKS)->defaultItems(0)->collapsible()->reorderableWithDragAndDrop()
                    ->itemLabel(fn (array $state) => Localize::word($state['label'] ?? null, app()->getLocale()))
                    ->addActionLabel(__('site::builder.actions.add_link'))->afterStateUpdated($save),
                Repeater::make('footer.social')->label(__($p.'social'))->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        Select::make('network')->label(__('site::builder.fields.network'))->native(false)->required()
                            ->options(array_combine(array_keys(PageDoc::SOCIAL), array_map(fn ($n) => Str::headline($n === 'x' ? 'X' : $n), array_keys(PageDoc::SOCIAL)))),
                        TextInput::make('url')->label(__('site::builder.fields.url'))->maxLength(500)->placeholder('https://')->extraInputAttributes(['dir' => 'ltr'])->columnSpan(['md' => 2]),
                    ]),
                ])->maxItems(PageDoc::MAX_SOCIAL)->defaultItems(0)->reorderableWithDragAndDrop()
                    ->addActionLabel(__('site::builder.actions.add_social'))->afterStateUpdated($save),
            ]),
            Section::make(__($p.'contact'))->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis)->collapsible()->collapsed()->schema([
                TextInput::make('whatsapp')->label(__($p.'whatsapp'))->helperText(__($p.'whatsapp_hint'))->tel()->maxLength(20)
                    ->placeholder('+966500000000')->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('floating_whatsapp')->label(__($p.'floating_whatsapp'))->live()->afterStateUpdated($save),
            ]),
            Section::make(__($p.'seo'))->description(__($p.'seo_hint'))->icon(Heroicon::OutlinedMagnifyingGlass)->collapsible()->collapsed()->schema([
                BlockForms::pair('seo.title', __($p.'seo_title'), 70),
                BlockForms::pair('seo.description', __($p.'seo_description'), 170, long: true),
                BlockForms::media('seo.image.media', __($p.'seo_image'), 'image')->helperText(__($p.'seo_image_hint')),
            ]),
        ]);
    }

    // ---- Saving ---------------------------------------------------------------

    /**
     * Saves the draft as it stands in the forms (shape and lengths only). New
     * and copied blocks get their ids here first, so the forms keep them.
     */
    public function saveDraft(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->giveIds();
        $doc = BuilderState::toDoc($this->data ?? [], $this->settings ?? []);
        try {
            $page = app(SaveSiteDraft::class)->handle($doc, $this->revision);
        } catch (ValidationException $e) {
            $this->tellProblems($e);

            return;
        }
        $this->resetErrorBag();
        $this->toldProblems = '';
        if ($page->draft_revision !== $this->revision) {
            $this->revision = (int) $page->draft_revision;
            $this->savedAt = now()->setTimezone(auth()->user()?->timezone ?? config('app.timezone'))->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i'));
            $this->dispatch('site-draft-saved', revision: $this->revision);
        }
    }

    /** Opens a block's form (or closes it when it is the open one), and shows that block in the preview. */
    public function edit(string $id): void
    {
        abort_unless(self::canAccess(), 403);
        $this->editing = $this->editing === $id ? null : $id;
        if ($this->editing !== null) {
            foreach ($this->data['blocks'] ?? [] as $item) {
                if (($item['data']['id'] ?? null) === $id) {
                    $this->dispatch('site-preview-focus', anchor: PageDoc::anchor(['type' => $item['type'], 'id' => $id] + (filled($item['data']['anchor'] ?? null) ? ['anchor' => $item['data']['anchor']] : [])));
                }
            }
        }
    }

    /** A fresh preview token: the preview renews it while the screen stays open. */
    public function renewPreview(): string
    {
        abort_unless(self::canAccess(), 403);

        return $this->previewToken = PreviewLinks::issue();
    }

    private function giveIds(): void
    {
        $seen = [];
        foreach (array_keys($this->data['blocks'] ?? []) as $key) {
            $id = $this->data['blocks'][$key]['data']['id'] ?? null;
            if (! is_string($id) || preg_match(Blocks::ID, $id) !== 1 || isset($seen[$id])) {
                $id = PageDoc::newId();
                $this->data['blocks'][$key]['data']['id'] = $id;
                // A block just added or copied opens at once.
                $this->editing = $id;
                // A copy keeps no old anchor: anchors are one block's each.
                if (isset($seen[$this->data['blocks'][$key]['data']['anchor'] ?? ''])) {
                    $this->data['blocks'][$key]['data']['anchor'] = null;
                }
            }
            $seen[$id] = true;
            $anchor = $this->data['blocks'][$key]['data']['anchor'] ?? null;
            if (is_string($anchor) && $anchor !== '') {
                if (isset($seen['#'.$anchor])) {
                    $this->data['blocks'][$key]['data']['anchor'] = null;
                }
                $seen['#'.$anchor] = true;
            }
        }
    }

    /** The draft was not saved: each problem under its field, and once as a notice. */
    private function tellProblems(ValidationException $e): void
    {
        $this->resetErrorBag();
        $first = null;
        foreach ($e->errors() as $path => $messages) {
            $message = (string) ($messages[0] ?? '');
            $first ??= $message;
            if ($path === 'revision') {
                Notification::make()->danger()->title($message)->persistent()
                    ->actions([Action::make('reload')->label(__('site::builder.actions.reload'))->button()->url(self::getUrl())])
                    ->send();
                $this->toldProblems = 'revision';

                return;
            }
            $field = BuilderState::formPath($path, $this->data ?? []);
            $this->addError($field ?? 'draft', $message);
        }
        $hash = md5(implode('|', array_keys($e->errors())));
        if ($first !== null && $hash !== $this->toldProblems) {
            Notification::make()->warning()->title(__('site::builder.status.not_saved'))->body($first)->send();
            $this->toldProblems = $hash;
        }
    }

    // ---- Reading --------------------------------------------------------------

    /** @return array<string, string> the page's blocks, for a button that goes to one */
    public function blockOptions(): array
    {
        $out = [];
        foreach ($this->data['blocks'] ?? [] as $item) {
            $id = $item['data']['id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }
            $title = Localize::word($item['data']['content']['title'] ?? null, app()->getLocale());
            $out[$id] = __('site::builder.types.'.$item['type'].'.name').($title !== null ? ' · '.Str::limit($title, 40) : '');
        }

        return $out;
    }

    public function verdict(): Verdict
    {
        return PublishSitePage::verdict(SitePage::current());
    }

    public function previewBase(): string
    {
        return PreviewLinks::base();
    }

    public function pageIsOn(): bool
    {
        return Settings::current()->shows();
    }

    /** @return array{files: list<array<string, mixed>>, count: int, used: string, limit: string} */
    public function library(): array
    {
        $inUse = MediaInUse::all();
        $files = [];
        $query = SiteMedia::query()->latest();
        if (in_array($this->mediaKind, ['image', 'video'], true)) {
            $query->where('kind', $this->mediaKind);
        }
        foreach ($query->get() as $media) {
            $where = [];
            foreach ($inUse[$media->id] ?? [] as $type) {
                $where[] = $type === 'seo' ? __('site::builder.media.used_seo') : PageWords::type($type);
            }
            $files[] = MediaPicker::describe($media) + ['where' => $where, 'alt' => Localize::word($media->alt, app()->getLocale())];
        }

        return [
            'files' => $files,
            'count' => SiteMedia::query()->count(),
            'used' => Number::fileSize((int) SiteMedia::query()->sum('bytes'), 1),
            'limit' => Number::fileSize(SiteMedia::MAX_BYTES),
        ];
    }

    // ---- Actions --------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('history')
                ->label(__('site::builder.actions.history'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->slideOver()
                ->modalWidth(Width::Medium)
                ->modalHeading(__('site::builder.history.title'))
                ->modalDescription(__('site::builder.history.hint'))
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('site::filament.builder-history', ['rows' => $this->history(), 'live' => $this->publishedRevision])),
            Action::make('discard')
                ->label(__('site::builder.actions.discard'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn () => $this->hasPublished && $this->publishedRevision !== $this->revision)
                ->requiresConfirmation()
                ->modalHeading(__('site::builder.discard.heading'))
                ->modalDescription(__('site::builder.discard.body'))
                ->action(function (): void {
                    $page = app(DiscardSiteDraft::class)->handle();
                    $this->load($page);
                    $this->dispatch('site-draft-saved', revision: $this->revision);
                    Notification::make()->success()->title(__('site::builder.discard.done'))->send();
                }),
            Action::make('publish')
                ->label(__('site::builder.actions.publish'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->modalHeading(__('site::builder.publish.heading'))
                ->modalDescription(__('site::builder.publish.body'))
                ->modalIcon(Heroicon::OutlinedRocketLaunch)
                ->modalWidth(Width::Large)
                ->mountUsing(fn () => $this->saveDraft())
                ->modalContent(fn () => view('site::filament.builder-verdict', ['verdict' => $this->verdict()]))
                ->modalSubmitAction(fn (Action $action) => $this->verdict()->ok() ? $action : false)
                ->modalSubmitActionLabel(__('site::builder.actions.publish'))
                ->action(function (): void {
                    $page = app(PublishSitePage::class)->handle($this->revision);
                    $this->revision = (int) $page->draft_revision;
                    $this->publishedRevision = (int) $page->published_revision;
                    $this->hasPublished = true;
                    // The preview reloads to say the draft it shows is the page now.
                    $this->dispatch('site-draft-saved', revision: $this->revision);
                    $notice = Notification::make()->success()->title(__('site::builder.publish.done'));
                    if (! $this->pageIsOn()) {
                        $notice->body(__('site::builder.publish.off'))->persistent()->actions([
                            Action::make('on')->label(__('site::builder.publish.turn_on'))->button()->url(SiteSettings::getUrl()),
                        ]);
                    }
                    $notice->send();
                }),
            ActionGroup::make([
                Action::make('templates')
                    ->label(__('site::builder.actions.templates'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->modalHeading(__('site::builder.templates.title'))
                    ->modalDescription(__('site::builder.templates.hint'))
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitActionLabel(__('site::builder.templates.use'))
                    ->schema([
                        ViewField::make('template')->hiddenLabel()->required()->view('site::filament.template-picker')
                            ->viewData(fn () => ['templates' => $this->templateCards(), 'count' => count($this->data['blocks'] ?? [])]),
                    ])
                    ->action(function (array $data): void {
                        $page = app(ApplySiteTemplate::class)->handle((string) $data['template']);
                        $this->load($page);
                        $this->dispatch('site-draft-saved', revision: $this->revision);
                        Notification::make()->success()->title(__('site::builder.templates.applied'))->send();
                    }),
                Action::make('open')
                    ->label(__('site::builder.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn () => SiteSettings::pageUrl(app()->getLocale() === 'en' ? 'en' : 'ar'))
                    ->openUrlInNewTab(),
                Action::make('settings')
                    ->label(__('site::builder.actions.settings'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn () => SiteSettings::getUrl()),
            ])->label(__('site::builder.actions.more'))->icon(Heroicon::OutlinedEllipsisVertical)->color('gray')->button(),
        ];
    }

    public function uploadMediaAction(): Action
    {
        return Action::make('uploadMedia')
            ->label(__('site::builder.media.upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading(__('site::builder.media.upload_title'))
            ->modalDescription(__('site::builder.media.upload_hint'))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('site::builder.media.upload'))
            ->schema([
                FileUpload::make('file')->label(__('site::builder.media.file'))->required()->storeFiles(false)
                    ->acceptedFileTypes([...array_keys(SiteMedia::IMAGES), ...array_keys(SiteMedia::VIDEOS)])
                    ->maxSize(SiteMedia::MAX_VIDEO_KB)->rules([new CleanFile])
                    ->imageResizeMode('contain')->imageResizeTargetWidth('2560')->imageResizeTargetHeight('2560')->imageResizeUpscale(false),
                BlockForms::pair('alt', __('site::builder.media.alt'), 200),
            ])
            ->action(function (array $data): void {
                app(UploadSiteMedia::class)->handle($data['file'], is_array($data['alt'] ?? null) ? $data['alt'] : []);
                Notification::make()->success()->title(__('site::builder.media.uploaded'))->send();
            });
    }

    public function editMediaAction(): Action
    {
        return Action::make('editMedia')
            ->label(__('site::builder.media.edit'))
            ->icon(Heroicon::OutlinedLanguage)
            ->iconButton()
            ->color('gray')
            ->modalHeading(__('site::builder.media.alt'))
            ->modalDescription(__('site::builder.media.alt_hint'))
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments) => ['alt' => SiteMedia::query()->find($arguments['media'] ?? null)?->alt ?? []])
            ->schema([BlockForms::pair('alt', __('site::builder.media.alt'), 200)])
            ->action(function (array $data, array $arguments): void {
                app(UpdateSiteMedia::class)->handle((string) ($arguments['media'] ?? ''), is_array($data['alt'] ?? null) ? $data['alt'] : []);
                Notification::make()->success()->title(__('site::builder.media.saved'))->send();
            });
    }

    public function deleteMediaAction(): Action
    {
        return Action::make('deleteMedia')
            ->label(__('site::builder.media.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('site::builder.media.delete_confirm'))
            ->action(function (array $arguments): void {
                app(DeleteSiteMedia::class)->handle((string) ($arguments['media'] ?? ''));
                Notification::make()->success()->title(__('site::builder.media.deleted'))->send();
            });
    }

    /** Brings an earlier publish back into the draft (from the history). */
    public function restorePublish(string $id): void
    {
        abort_unless(self::canAccess(), 403);
        $page = app(RestoreSitePage::class)->handle($id);
        $this->load($page);
        $this->dispatch('site-draft-saved', revision: $this->revision);
        $this->unmountAction();
        Notification::make()->success()->title(__('site::builder.history.restored'))->send();
    }

    /** @return list<array{id: string, revision: int, blocks: int, when: string, by: ?string}> */
    private function history(): array
    {
        $rows = SitePagePublish::query()->orderByDesc('published_at')->limit(SitePagePublish::KEEP)->get();
        $names = DB::table('users')->whereIn('id', $rows->pluck('published_by')->filter()->unique()->all())->pluck('name', 'id')->all();
        $tz = auth()->user()?->timezone ?? config('app.timezone');
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row->id, 'revision' => $row->revision, 'blocks' => count($row->page['blocks'] ?? []),
                'when' => $row->published_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('j M Y · H:i')),
                'by' => $row->published_by !== null ? ($names[$row->published_by] ?? null) : null,
            ];
        }

        return $out;
    }

    /** @return list<array{key: string, name: string, line: string, blocks: list<array{type: string, svg: string, name: string}>}> */
    private function templateCards(): array
    {
        $out = [];
        $audience = (string) ($this->settings['audience'] ?? 'families');
        foreach (Templates::KEYS as $key) {
            $blocks = [];
            foreach (Templates::make($key, $audience)['blocks'] as $block) {
                if (! $block['hidden']) {
                    $blocks[] = ['type' => $block['type'], 'name' => PageWords::type($block['type']), 'svg' => Schematics::svg($block['type'], $block['variant'])];
                }
            }
            $out[] = ['key' => $key, 'name' => __("site::builder.templates.{$key}.name"), 'line' => __("site::builder.templates.{$key}.line"), 'blocks' => $blocks];
        }

        return $out;
    }
}
