<?php

namespace Modules\Site\Filament\Forms;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Modules\Billing\Contracts\BillingApi;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\People\Contracts\PeopleApi;
use Modules\Site\Domain\Page\Blocks;
use Modules\Site\Domain\Page\Localize;
use Modules\Site\Filament\Pages\SiteBuilder;

/**
 * The page builder's forms, one Filament Builder block per block type
 * (site-builder.md, 4.2): Content (Arabic and English side by side), Layout
 * (the design as drawn choices, and the type's options) and Style (the
 * background in the academy's colours, alignment, space, width, the menu
 * label). Field names follow the document (Domain\Page\Blocks), under
 * "content.". Choices that change what shows save at once; words save after
 * a pause in typing (the page's script).
 */
final class BlockForms
{
    private const B = 'site::builder.';

    /** Each type's icon in the block picker and the list. */
    public const ICONS = [
        'hero' => Heroicon::OutlinedSparkles, 'features' => Heroicon::OutlinedStar, 'subjects' => Heroicon::OutlinedBookOpen,
        'teachers' => Heroicon::OutlinedAcademicCap, 'pricing' => Heroicon::OutlinedTag, 'steps' => Heroicon::OutlinedListBullet,
        'app' => Heroicon::OutlinedDevicePhoneMobile, 'testimonials' => Heroicon::OutlinedChatBubbleBottomCenterText,
        'faq' => Heroicon::OutlinedQuestionMarkCircle, 'stats' => Heroicon::OutlinedChartBar, 'rich_text' => Heroicon::OutlinedDocumentText,
        'image_text' => Heroicon::OutlinedPhoto, 'gallery' => Heroicon::OutlinedSquares2x2, 'video' => Heroicon::OutlinedPlayCircle,
        'logos' => Heroicon::OutlinedBuildingOffice2, 'cards' => Heroicon::OutlinedRectangleGroup, 'cta' => Heroicon::OutlinedMegaphone, 'form' => Heroicon::OutlinedEnvelopeOpen,
        'contact' => Heroicon::OutlinedPhone, 'spacer' => Heroicon::OutlinedArrowsUpDown,
    ];

    /** @return list<Block> */
    public static function blocks(): array
    {
        $out = [];
        foreach (Blocks::types() as $type) {
            $out[] = Block::make($type)
                ->label(fn (?array $state) => $state === null ? __(self::B."types.{$type}.name") : self::label($type, $state))
                ->icon(self::ICONS[$type])
                // Built only for the types on the page, when they are drawn.
                ->schema(fn (): array => self::schema($type));
        }

        return $out;
    }

    /** The list's line for a block: its type, its design and the start of its title; dimmed when hidden. */
    public static function label(string $type, array $state): HtmlString
    {
        $variant = (string) ($state['variant'] ?? '');
        $title = Localize::word($state['content']['title'] ?? null, app()->getLocale()) ?? '';
        $title = mb_strlen($title) > 40 ? mb_substr($title, 0, 40).'…' : $title;
        $html = '<span class="lms-blk"><span class="lms-blk__type">'.e(__(self::B."types.{$type}.name")).'</span>'
            .'<span class="lms-blk__variant">'.e(trans()->has(self::B."variants.{$type}.{$variant}") ? __(self::B."variants.{$type}.{$variant}") : '').'</span>';
        if ($title !== '') {
            $html .= '<span class="lms-blk__title">'.e($title).'</span>';
        }
        if (in_array($type, Blocks::LIVE, true)) {
            $html .= '<span class="lms-blk__live">'.e(__(self::B.'block.live')).'</span>';
        }
        if ($state['hidden'] ?? false) {
            $html .= '<span class="lms-blk__hidden" data-lms-hidden>'.e(__(self::B.'block.hidden')).'</span>';
        }

        return new HtmlString($html.'</span>');
    }

    /** @return list<Component> */
    private static function schema(string $type): array
    {
        $tabs = [
            Tab::make(__(self::B.'groups.content'))->icon(Heroicon::OutlinedPencilSquare)->schema(fn (): array => self::content($type)),
            Tab::make(__(self::B.'groups.layout'))->icon(Heroicon::OutlinedSquaresPlus)->schema(fn (): array => self::layout($type)),
            Tab::make(__(self::B.'groups.style'))->icon(Heroicon::OutlinedSwatch)->schema(fn (): array => self::style($type)),
        ];

        // Only the block being edited draws its form: a page of twenty blocks stays light,
        // and every save re-renders one form, not all of them.
        $editing = fn (Get $get, $livewire): bool => $livewire instanceof SiteBuilder && $livewire->editing !== null && $livewire->editing === $get('id');

        return [
            Hidden::make('id'),
            Hidden::make('anchor'),
            Hidden::make('hidden'),
            ViewField::make('summary')->hiddenLabel()->dehydrated(false)->view('site::filament.block-summary')
                ->viewData(['type' => $type])->visible(fn (Get $get, $livewire) => ! $editing($get, $livewire)),
            Tabs::make('parts')->tabs($tabs)->contained(false)->visible($editing),
        ];
    }

    /** The blocks whose title and lead fall back to the portal's words when empty (spec 6.6). */
    private const SAID = ['features', 'subjects', 'teachers', 'steps', 'app', 'pricing', 'testimonials', 'faq', 'form', 'contact'];

    // ---- Content -------------------------------------------------------------

    /** @return list<Component> */
    private static function content(string $type): array
    {
        $c = 'content.';
        // A heading the portal has its own words for (spec 6.6): empty shows them, and the box says so.
        $said = in_array($type, self::SAID, true) ? __(self::B.'fields.empty_default') : null;
        $heading = fn (bool $lead = true) => [
            self::pair($c.'eyebrow', __(self::B.'fields.eyebrow'), 40, placeholder: __(self::B.'fields.empty_default'))->visible(fn (Get $get) => (bool) $get('style.eyebrow')),
            self::pair($c.'title', __(self::B.'fields.title'), 120, placeholder: $said),
            ...($lead ? [self::pair($c.'lead', __(self::B.'fields.lead'), 300, long: true, placeholder: $said)] : []),
        ];
        $note = fn (string $key) => Callout::make(__(self::B."live.{$key}"))->info()->icon(Heroicon::OutlinedInformationCircle);

        return match ($type) {
            'hero' => [
                self::pair($c.'kicker', __(self::B.'fields.kicker'), 60, placeholder: __(self::B.'fields.empty_default')),
                self::pair($c.'title', __(self::B.'fields.title'), 120),
                self::pair($c.'lead', __(self::B.'fields.lead'), 300, long: true),
                Section::make(__(self::B.'fields.buttons'))->compact()->collapsible()->schema([
                    self::link($c.'primary', __(self::B.'fields.primary')),
                    self::link($c.'secondary', __(self::B.'fields.secondary')),
                ]),
                self::defaultable($c.'trust', __(self::B.'fields.trust'), __(self::B.'fields.trust_hint'), self::lines($c.'trust', 60, 4)),
                self::media($c.'media.media', __(self::B.'fields.media'), 'image')
                    ->visible(fn (Get $get) => in_array($get('variant'), ['centered', 'split'], true)),
                self::media($c.'video.media', __(self::B.'fields.video'), 'video')
                    ->helperText(__(self::B.'live.video'))
                    ->visible(fn (Get $get) => in_array($get('variant'), ['centered', 'split', 'video'], true)),
                self::media($c.'poster.media', __(self::B.'fields.poster'), 'image')->helperText(__(self::B.'fields.poster_hint'))
                    ->visible(fn (Get $get) => in_array($get('variant'), ['centered', 'split', 'video'], true)),
            ],
            'features' => [
                ...$heading(),
                Repeater::make($c.'items')->label(__(self::B.'fields.items'))->schema([
                    Select::make('icon')->label(__(self::B.'fields.icon'))->options(self::icons())->native(false)->required()->default('check')
                        ->allowHtml()->searchable(false),
                    self::pair('title', __(self::B.'fields.title'), 60),
                    self::pair('text', __(self::B.'fields.text'), 200, long: true),
                ])->maxItems(9)->collapsible()->collapsed()->cloneable()->reorderableWithDragAndDrop()
                    ->itemLabel(fn (array $state) => Localize::word($state['title'] ?? null, app()->getLocale()))
                    ->addActionLabel(__(self::B.'actions.add_item'))->afterStateUpdated(self::save()),
            ],
            'subjects' => [
                $note('subjects'),
                ...$heading(),
                self::only($c.'only', fn () => app(CatalogApi::class)->subjectNames()),
                Grid::make(3)->schema([
                    self::toggle($c.'show_levels', __(self::B.'fields.show_levels')),
                    self::toggle($c.'show_programs', __(self::B.'fields.show_programs')),
                    self::toggle($c.'show_categories', __(self::B.'fields.show_categories')),
                ]),
                self::pair($c.'ask_label', __(self::B.'fields.ask_label'), 40),
            ],
            'teachers' => [
                $note('teachers'),
                ...$heading(),
                self::only($c.'only', fn () => self::cards()),
                Select::make($c.'max')->label(__(self::B.'fields.max'))->options(array_combine(array_map('strval', range(1, 24)), range(1, 24)))
                    ->native(false)->live()->afterStateUpdated(self::save()),
                Grid::make(2)->schema([
                    self::toggle($c.'show_bio', __(self::B.'fields.show_bio')),
                    self::toggle($c.'show_languages', __(self::B.'fields.show_languages')),
                    self::toggle($c.'show_ages', __(self::B.'fields.show_ages')),
                    self::toggle($c.'show_badges', __(self::B.'fields.show_badges')),
                ]),
            ],
            'pricing' => [
                $note('pricing'),
                ...$heading(),
                self::only($c.'only', fn () => self::plans()),
                Select::make($c.'highlight')->label(__(self::B.'fields.highlight'))->options(fn () => self::plans())->placeholder(__(self::B.'options.none'))
                    ->native(false)->live()->afterStateUpdated(self::save()),
                self::pair($c.'highlight_label', __(self::B.'fields.highlight_label'), 30)->visible(fn (Get $get) => filled($get($c.'highlight'))),
                self::toggle($c.'per_lesson', __(self::B.'fields.per_lesson')),
                self::pair($c.'note', __(self::B.'fields.note'), 300, long: true),
            ],
            'steps' => [
                ...$heading(),
                self::defaultable($c.'items', __(self::B.'fields.items'), __(self::B.'fields.points_hint'), self::items($c.'items', 6, [
                    self::pair('title', __(self::B.'fields.title'), 60),
                    self::pair('text', __(self::B.'fields.text'), 200, long: true),
                ])),
            ],
            'app' => [
                $note('app'),
                ...$heading(),
                self::defaultable($c.'points', __(self::B.'fields.points'), __(self::B.'fields.points_hint'), self::items($c.'points', 4, [
                    self::pair('title', __(self::B.'fields.title'), 60),
                    self::pair('text', __(self::B.'fields.text'), 200, long: true),
                ])),
                Grid::make(2)->schema([
                    self::toggle($c.'show_code', __(self::B.'fields.show_code')),
                    self::toggle($c.'show_stores', __(self::B.'fields.show_stores')),
                ]),
                self::media($c.'media.media', __(self::B.'fields.media'), 'image')->visible(fn (Get $get) => $get('variant') === 'image'),
            ],
            'testimonials' => [
                Callout::make(__(self::B.'live.testimonials'))->warning()->icon(Heroicon::OutlinedHandRaised),
                ...$heading(),
                self::items($c.'items', 12, [
                    TextInput::make('name')->label(__(self::B.'fields.name'))->maxLength(60),
                    self::pair('role', __(self::B.'fields.role'), 60),
                    self::pair('quote', __(self::B.'fields.quote'), 400, long: true),
                    Grid::make(2)->schema([
                        self::media('photo.media', __(self::B.'fields.photo'), 'image'),
                        Select::make('rating')->label(__(self::B.'fields.rating'))->native(false)
                            ->options(['0' => __(self::B.'options.rating.0'), '5' => '★★★★★', '4' => '★★★★', '3' => '★★★', '2' => '★★', '1' => '★']),
                    ]),
                ], 'name'),
            ],
            'faq' => [
                ...$heading(),
                self::items($c.'items', 20, [
                    self::pair('q', __(self::B.'fields.q'), 160),
                    self::pair('a', __(self::B.'fields.a'), 800, long: true),
                ], 'q'),
            ],
            'stats' => [
                Callout::make(__(self::B.'live.stats'))->info()->icon(Heroicon::OutlinedInformationCircle),
                ...$heading(),
                self::items($c.'items', 6, [
                    self::pair('value', __(self::B.'fields.value'), 12),
                    self::pair('label', __(self::B.'fields.label'), 60),
                ], 'value'),
            ],
            'rich_text' => [
                ...$heading(),
                self::rich($c.'body', __(self::B.'fields.body')),
            ],
            'image_text' => [
                ...$heading(),
                self::rich($c.'body', __(self::B.'fields.body')),
                self::media($c.'media.media', __(self::B.'fields.media'), 'image'),
                Grid::make(2)->schema([
                    self::media($c.'video.media', __(self::B.'fields.video'), 'video'),
                    self::media($c.'poster.media', __(self::B.'fields.poster'), 'image')->helperText(__(self::B.'fields.poster_hint')),
                ]),
                self::lines($c.'bullets', 120, 6)->hiddenLabel(false)->label(__(self::B.'fields.bullets')),
                Section::make(__(self::B.'fields.link'))->compact()->collapsible()->collapsed()->schema([self::link($c.'link', __(self::B.'fields.link'))]),
            ],
            'gallery' => [
                ...$heading(false),
                self::items($c.'items', 24, [
                    self::media('media.media', __(self::B.'fields.media'), 'image'),
                    self::pair('caption', __(self::B.'fields.caption'), 120),
                ], 'caption', grid: 2),
            ],
            'video' => [
                ...$heading(),
                ToggleButtons::make($c.'source.kind')->label(__(self::B.'fields.source'))->inline()->live()->afterStateUpdated(self::save())
                    ->options(['upload' => __(self::B.'options.source.upload'), 'youtube' => __(self::B.'options.source.youtube'), 'vimeo' => __(self::B.'options.source.vimeo')]),
                self::media($c.'source.video.media', __(self::B.'fields.video'), 'video')->helperText(__(self::B.'live.video'))
                    ->visible(fn (Get $get) => ($get($c.'source.kind') ?? 'upload') === 'upload'),
                TextInput::make($c.'source.id')->label(__(self::B.'fields.id'))->helperText(__(self::B.'fields.id_hint'))->maxLength(300)
                    ->extraInputAttributes(['dir' => 'ltr'])->visible(fn (Get $get) => in_array($get($c.'source.kind'), ['youtube', 'vimeo'], true)),
                self::media($c.'source.poster.media', __(self::B.'fields.poster'), 'image')->helperText(__(self::B.'fields.poster_hint')),
                self::pair($c.'caption', __(self::B.'fields.caption'), 200),
            ],
            'logos' => [
                Callout::make(__(self::B.'live.logos'))->info()->icon(Heroicon::OutlinedInformationCircle),
                ...$heading(false),
                self::items($c.'items', 24, [
                    self::media('media.media', __(self::B.'fields.media'), 'image'),
                    TextInput::make('name')->label(__(self::B.'fields.name'))->maxLength(60),
                    TextInput::make('url')->label(__(self::B.'fields.url'))->url()->maxLength(500)->prefix('https://')->extraInputAttributes(['dir' => 'ltr']),
                ], 'name', grid: 2),
            ],
            'cards' => [
                Callout::make(__(self::B.'live.cards'))->info()->icon(Heroicon::OutlinedInformationCircle),
                ...$heading(),
                self::items($c.'items', Blocks::MAX_CARDS, [
                    self::media('media.media', __(self::B.'fields.media'), 'image')->helperText(__(self::B.'fields.card_media_hint')),
                    self::pair('label', __(self::B.'fields.badge'), 40, placeholder: __(self::B.'fields.badge_hint')),
                    self::pair('title', __(self::B.'fields.title'), 80),
                    self::pair('text', __(self::B.'fields.text'), 300, long: true),
                    Section::make(__(self::B.'fields.link'))->compact()->collapsible()->collapsed()->schema([self::link('link', __(self::B.'fields.action'))]),
                ]),
            ],
            'cta' => [
                self::pair($c.'title', __(self::B.'fields.title'), 120),
                self::pair($c.'lead', __(self::B.'fields.lead'), 300, long: true),
                Section::make(__(self::B.'fields.buttons'))->compact()->collapsible()->schema([
                    self::link($c.'primary', __(self::B.'fields.primary'), nullable: false),
                    self::link($c.'secondary', __(self::B.'fields.secondary')),
                ]),
                self::media($c.'media.media', __(self::B.'fields.media'), 'image')->visible(fn (Get $get) => in_array($get('variant'), ['split', 'banner'], true)),
            ],
            'form' => [
                $note('form'),
                ...$heading(),
                self::defaultable($c.'points', __(self::B.'fields.points'), __(self::B.'fields.points_hint'), self::lines($c.'points', 120, 5)),
                Grid::make(2)->schema([
                    self::toggle($c.'teach', __(self::B.'fields.teach')),
                    self::toggle($c.'whatsapp_button', __(self::B.'fields.whatsapp_button')),
                ]),
            ],
            'contact' => [
                ...$heading(),
                Grid::make(2)->schema([
                    TextInput::make($c.'email')->label(__(self::B.'fields.email'))->email()->maxLength(120)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make($c.'phone')->label(__(self::B.'fields.phone'))->tel()->maxLength(20)->placeholder('+966500000000')->extraInputAttributes(['dir' => 'ltr']),
                ]),
                self::pair($c.'address', __(self::B.'fields.address'), 300, long: true),
                self::pair($c.'hours', __(self::B.'fields.hours'), 120),
                self::toggle($c.'whatsapp', __(self::B.'fields.whatsapp')),
            ],
            'spacer' => [
                ToggleButtons::make($c.'size')->label(__(self::B.'fields.size'))->inline()->live()->afterStateUpdated(self::save())
                    ->options(self::opts('size', ['sm', 'md', 'lg'])),
            ],
            default => [],
        };
    }

    // ---- Layout ---------------------------------------------------------------

    /** @return list<Component> */
    private static function layout(string $type): array
    {
        $c = 'content.';
        $variants = [];
        foreach (Blocks::variants($type) as $variant) {
            $variants[$variant] = ['label' => __(self::B."variants.{$type}.{$variant}"), 'svg' => Schematics::svg($type, $variant)];
        }
        $out = [
            ViewField::make('variant')->label(__(self::B.'fields.variant'))->view('site::filament.variant-picker')
                ->viewData(['choices' => $variants])->live()->afterStateUpdated(self::save()),
        ];
        $buttons = fn (string $name, string $set, array $values) => ToggleButtons::make($c.$name)->label(__(self::B."fields.{$name}"))->inline()
            ->options(self::opts($set, $values))->live()->afterStateUpdated(self::save());

        return [...$out, ...match ($type) {
            'hero' => [
                $buttons('media_side', 'media_side', ['start', 'end'])->visible(fn (Get $get) => $get('variant') === 'split'),
                $buttons('media_shape', 'media_shape', ['rounded', 'arch', 'circle', 'bleed'])->visible(fn (Get $get) => $get('variant') === 'split'),
                $buttons('scrim', 'scrim', ['light', 'medium', 'strong'])->visible(fn (Get $get) => $get('variant') === 'video'),
                Section::make(__(self::B.'fields.show'))->compact()->columns(3)->schema(array_map(
                    fn (string $key) => self::toggle($c.'show.'.$key, __(self::B."options.show.{$key}")),
                    ['kicker', 'lead', 'buttons', 'trust', 'media'],
                )),
            ],
            'features' => [$buttons('columns', 'columns', ['2', '3'])],
            'cards' => [$buttons('columns', 'columns', ['2', '3', '4'])->visible(fn (Get $get) => $get('variant') !== 'list')],
            'image_text' => [$buttons('media_side', 'media_side', ['start', 'end'])],
            'gallery' => [$buttons('ratio', 'ratio', ['auto', '1:1', '4:3', '3:2', '16:9'])->visible(fn (Get $get) => $get('variant') === 'grid')],
            'video' => [
                $buttons('ratio', 'ratio', ['16:9', '4:3', '1:1', '9:16']),
                self::toggle($c.'autoplay_muted', __(self::B.'fields.autoplay_muted'))->visible(fn (Get $get) => ($get($c.'source.kind') ?? 'upload') === 'upload'),
            ],
            'logos' => [self::toggle($c.'mono', __(self::B.'fields.mono'))],
            default => [],
        }];
    }

    // ---- Style ----------------------------------------------------------------

    /** @return list<Component> */
    private static function style(string $type): array
    {
        $buttons = fn (string $name, array $values) => ToggleButtons::make('style.'.$name)->label(__(self::B."fields.{$name}"))->inline()
            ->options(self::opts($name, $values))->live()->afterStateUpdated(self::save());
        $tone = ViewField::make('style.tone')->label(__(self::B.'fields.tone'))->view('site::filament.tone-picker')
            ->viewData(['tones' => self::opts('tone', Blocks::TONES)])->live()->afterStateUpdated(self::save());
        if ($type === 'spacer') {
            return [$tone];
        }

        return [
            $tone,
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                $buttons('align', Blocks::ALIGN),
                $buttons('space', Blocks::SPACE),
            ]),
            $buttons('width', Blocks::WIDTH),
            self::toggle('style.eyebrow', __(self::B.'fields.eyebrow_on')),
            self::pair('nav', __(self::B.'fields.nav'), 24),
            Callout::make(__(self::B.'fields.nav_hint'))->icon(Heroicon::OutlinedBars3)->color('gray'),
        ];
    }

    // ---- Pieces ---------------------------------------------------------------

    /** An autosave after a choice. */
    public static function save(): \Closure
    {
        return function ($livewire): void {
            if ($livewire instanceof SiteBuilder) {
                $livewire->saveDraft();
            }
        };
    }

    /** A text in Arabic and English, side by side; English written left to right. */
    public static function pair(string $path, string $label, int $max, bool $long = false, ?string $placeholder = null): Grid
    {
        $box = fn (string $locale) => ($long ? Textarea::make("{$path}.{$locale}")->rows(2)->autosize() : TextInput::make("{$path}.{$locale}"))
            ->label($label)->hint($locale === 'ar' ? 'ع' : 'EN')->maxLength($max)->placeholder($placeholder)
            ->extraInputAttributes($locale === 'en' ? ['dir' => 'ltr', 'lang' => 'en'] : ['dir' => 'rtl', 'lang' => 'ar']);

        return Grid::make(['default' => 1, 'md' => 2])->schema([$box('ar'), $box('en')]);
    }

    private static function lines(string $path, int $max, int $count): Repeater
    {
        return Repeater::make($path)->hiddenLabel()->schema([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('ar')->hiddenLabel()->prefix('ع')->maxLength($max)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('en')->hiddenLabel()->prefix('EN')->maxLength($max)->extraInputAttributes(['dir' => 'ltr']),
            ]),
        ])->maxItems($count)->defaultItems(0)->reorderableWithDragAndDrop()->addActionLabel(__(self::B.'actions.add_line'))
            ->afterStateUpdated(self::save());
    }

    /** @param  list<Component>  $schema */
    private static function items(string $path, int $max, array $schema, string $labelField = 'title', int $grid = 1): Repeater
    {
        return Repeater::make($path)->label(__(self::B.'fields.items'))->schema($schema)->maxItems($max)->defaultItems(0)
            ->collapsible()->collapsed()->cloneable()->reorderableWithDragAndDrop()->grid($grid)
            ->itemLabel(function (array $state) use ($labelField): ?string {
                $value = $state[$labelField] ?? null;

                return is_string($value) ? $value : Localize::word($value, app()->getLocale());
            })
            ->addActionLabel(__(self::B.'actions.add_item'))->afterStateUpdated(self::save());
    }

    /** A list the portal fills with its own words while the switch is on. */
    private static function defaultable(string $path, string $label, string $hint, Component $list): Group
    {
        return Group::make([
            Toggle::make("{$path}_default")->label(__(self::B.'fields.use_default', ['field' => $label]))
                ->helperText($hint)->onIcon(Heroicon::OutlinedSparkles)->live()->afterStateUpdated(self::save())
                ->extraAttributes(['class' => 'lms-default-toggle'])
                ->formatStateUsing(fn ($state) => (bool) $state)
                ->dehydrated(),
            $list->visible(fn (Get $get) => ! $get("{$path}_default")),
        ]);
    }

    private static function toggle(string $path, string $label): Toggle
    {
        return Toggle::make($path)->label($label)->live()->afterStateUpdated(self::save());
    }

    private static function rich(string $path, string $label): Grid
    {
        $editor = fn (string $locale) => RichEditor::make("{$path}.{$locale}")->label($label)->hint($locale === 'ar' ? 'ع' : 'EN')->json()
            ->toolbarButtons([['bold', 'italic', 'link'], ['h3', 'h4'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
            ->extraInputAttributes($locale === 'en' ? ['dir' => 'ltr', 'lang' => 'en'] : ['dir' => 'rtl', 'lang' => 'ar']);

        return Grid::make(1)->schema([$editor('ar'), $editor('en')]);
    }

    public static function media(string $path, string $label, string $kind): MediaPicker
    {
        return MediaPicker::make($path)->label($label)->kind($kind)->afterStateUpdated(self::save());
    }

    /** A button: where it goes (or none), the section or the address, and its words. */
    public static function link(string $path, string $label, bool $nullable = true): Group
    {
        $kinds = [];
        if ($nullable) {
            $kinds['none'] = __(self::B.'options.none');
        }
        foreach (['trial', 'teach', 'sign_in', 'whatsapp', 'block', 'url'] as $kind) {
            $kinds[$kind] = __(self::B."options.action.{$kind}");
        }

        return Group::make([
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Select::make("{$path}.action.kind")->label($label)->options($kinds)->native(false)->selectablePlaceholder(false)
                    ->live()->afterStateUpdated(self::save()),
                Select::make("{$path}.action.block")->label(__(self::B.'fields.block'))->native(false)
                    ->options(fn ($livewire) => $livewire instanceof SiteBuilder ? $livewire->blockOptions() : [])
                    ->visible(fn (Get $get) => $get("{$path}.action.kind") === 'block')->live()->afterStateUpdated(self::save()),
                TextInput::make("{$path}.action.url")->label(__(self::B.'fields.url'))->maxLength(500)->placeholder('https://')
                    ->extraInputAttributes(['dir' => 'ltr'])->visible(fn (Get $get) => $get("{$path}.action.kind") === 'url'),
            ]),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make("{$path}.label.ar")->label(__(self::B.'fields.label_text'))->hint('ع')->maxLength(40)->placeholder(__(self::B.'fields.label_hint')),
                TextInput::make("{$path}.label.en")->label(__(self::B.'fields.label_text'))->hint('EN')->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
            ])->visible(fn (Get $get) => ! in_array($get("{$path}.action.kind"), [null, '', 'none'], true)),
        ]);
    }

    /** @param  \Closure(): array<string, string>  $options */
    private static function only(string $path, \Closure $options): Select
    {
        return Select::make($path)->label(__(self::B.'fields.only'))->helperText(__(self::B.'fields.only_hint'))
            ->multiple()->reorderable()->native(false)->options($options)->live()->afterStateUpdated(self::save());
    }

    /** @return array<string, string> */
    private static function opts(string $set, array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $out[(string) $value] = __(self::B."options.{$set}.{$value}");
        }

        return $out;
    }

    /** @return array<string, string> the portal's icons, each drawn, for the reasons */
    private static function icons(): array
    {
        $out = [];
        foreach (Blocks::ICONS as $icon) {
            $out[$icon] = '<span class="lms-icon-opt">'.svg(self::HEROICON[$icon] ?? 'heroicon-o-check', 'lms-icon-opt__svg')->toHtml().'<span>'.e(__(self::B."icons.{$icon}")).'</span></span>';
        }

        return $out;
    }

    /** The closest panel icon to each of the portal's, for the picker only. */
    private const HEROICON = [
        'users' => 'heroicon-o-users', 'report' => 'heroicon-o-document-chart-bar', 'calendar' => 'heroicon-o-calendar-days', 'map' => 'heroicon-o-map',
        'shield' => 'heroicon-o-shield-check', 'heart' => 'heroicon-o-heart', 'star' => 'heroicon-o-star', 'check' => 'heroicon-o-check-circle',
        'play' => 'heroicon-o-play-circle', 'book' => 'heroicon-o-book-open', 'clock' => 'heroicon-o-clock', 'globe' => 'heroicon-o-globe-alt',
        'chat' => 'heroicon-o-chat-bubble-left-right', 'award' => 'heroicon-o-trophy', 'laptop' => 'heroicon-o-computer-desktop', 'phone' => 'heroicon-o-device-phone-mobile',
    ];

    /** @return array<string, string> the teachers' cards on the page */
    private static function cards(): array
    {
        $out = [];
        foreach (app(PeopleApi::class)->teacherCards() as $card) {
            if ($card->onPage()) {
                $out[$card->id] = $card->displayName;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function plans(): array
    {
        $out = [];
        foreach (app(BillingApi::class)->planOptions() as $plan) {
            $out[$plan->id] = $plan->name;
        }

        return $out;
    }
}
