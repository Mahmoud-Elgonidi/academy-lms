<?php

namespace Modules\Site\Domain\Page;

/**
 * The page an academy had before the builder, as blocks (site-builder.md,
 * 5.1): its saved words (Domain\SiteContent, over the defaults) become the
 * blocks of today's page in today's order, each with its old anchor, the
 * sections it switched off hidden, and the portal's own words wherever the
 * old page said them. It renders exactly as before. The data migration
 * keeps it as each academy's draft and published page, and an academy with
 * no page yet reads the same (Models\SitePage::current()). Pure.
 */
final class LegacyImport
{
    /** The old page's reasons took these icons, in order; any after them, a star. */
    public const FEATURE_ICONS = ['users', 'report', 'calendar', 'map', 'shield', 'heart'];

    /** The blocks' ids are fixed, so the same content always makes the same page. */
    public const IDS = [
        'hero' => 'lhero000', 'features' => 'lwhy0000', 'subjects' => 'lsubject', 'teachers' => 'lteacher', 'steps' => 'lsteps00',
        'app' => 'lapp0000', 'pricing' => 'lpricing', 'testimonials' => 'ltestimo', 'faq' => 'lfaq0000', 'form' => 'ljoin000',
    ];

    /**
     * @param  array<string, mixed>  $content  SiteContent's shape, already over its defaults (SiteSettings::said())
     * @return array{v: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function fromContent(array $content): array
    {
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
        $off = fn (string $section): bool => ! in_array($section, $sections, true);

        $features = [];
        foreach (self::rows($content['features'] ?? null) as $i => $row) {
            $features[] = [
                'icon' => self::FEATURE_ICONS[$i] ?? 'star',
                'title' => self::pair($row, 'title', 60),
                'text' => self::pair($row, 'text', 200),
            ];
        }
        $testimonials = [];
        foreach (self::rows($content['testimonials'] ?? null) as $row) {
            $testimonials[] = [
                'name' => mb_substr(Clean::text($row['name'] ?? ''), 0, 60),
                'role' => self::pair($row, 'role', 60),
                'quote' => self::pair($row, 'quote', 400),
                'photo' => null,
                'rating' => 0,
            ];
        }
        $faqs = [];
        foreach (self::rows($content['faqs'] ?? null) as $row) {
            $faqs[] = ['q' => self::pair($row, 'q', 160), 'a' => self::pair($row, 'a', 800)];
        }
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $why = is_array($content['why'] ?? null) ? $content['why'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        $block = fn (string $type, string $variant, ?string $anchor, string $tone, bool $hidden, array $data) => [
            'id' => self::IDS[$type], 'type' => $type, 'variant' => $variant, 'hidden' => $hidden,
            'nav' => ['ar' => '', 'en' => ''], 'style' => ['tone' => $tone], 'data' => $data,
        ] + ($anchor !== null ? ['anchor' => $anchor] : []);

        $blocks = [
            $block('hero', 'phones', null, 'night', false, ['title' => self::pair($hero, 'title', 120), 'lead' => self::pair($hero, 'lead', 300)]),
            $block('features', 'cards', 'why', 'white', $features === [], [
                'title' => self::pair($why, 'title', 120), 'lead' => self::pair($why, 'lead', 300), 'items' => $features,
            ]),
            $block('subjects', 'cards', 'subjects', 'paper', $off('subjects'), []),
            $block('teachers', 'cards', 'teachers', 'white', $off('teachers'), []),
            $block('steps', 'line', 'start', 'paper', $off('steps'), []),
            $block('app', 'phones', 'app', 'night', $off('app'), []),
            $block('pricing', 'cards', 'pricing', 'white', $off('pricing'), []),
            $block('testimonials', 'marquee', 'testimonials', 'paper', $off('testimonials'), ['items' => $testimonials]),
            $block('faq', 'accordion', 'faq', 'white', $off('faq'), ['items' => $faqs]),
            $block('form', 'split', 'join', 'night', false, ['teach' => ! $off('teach')]),
        ];

        $whatsapp = Clean::phone($content['whatsapp'] ?? '');

        return PageDoc::normalize([
            'settings' => [
                'audience' => $content['audience'] ?? 'families',
                'accent' => 'primary', 'mood' => 'light', 'corners' => 'brand', 'motion' => 'full',
                'header' => ['style' => 'overlay'],
                'whatsapp' => Clean::isPhone($whatsapp) ? $whatsapp : '',
                'floating_whatsapp' => true,
                'seo' => ['title' => self::pair($seo, 'title', 70), 'description' => self::pair($seo, 'description', 170), 'image' => null],
            ],
            'blocks' => $blocks,
        ]);
    }

    /** @return array{ar: string, en: string} "title_ar"/"title_en" → {ar, en}, each held to its length */
    private static function pair(array $row, string $key, int $max): array
    {
        return [
            'ar' => mb_substr(Clean::text($row["{$key}_ar"] ?? ''), 0, $max),
            'en' => mb_substr(Clean::text($row["{$key}_en"] ?? ''), 0, $max),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function rows(mixed $rows): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
