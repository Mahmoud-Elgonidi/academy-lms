<?php

namespace Modules\Site\Domain\Page;

/**
 * The page builder's blocks (docs/architecture/site-builder.md, 1.3): each
 * type's variants, its default tone, whether a page holds one at most, and
 * the fields of its data. One table read by everything else: PageDoc cleans
 * by it, Localize shapes the API by it, PublishRules checks by it, and the
 * builder draws its forms from the same names. Pure.
 *
 * A field is a list: [kind, ...options]. Kinds:
 *  text N [req] [d]    {ar, en}, each at most N characters; (d): empty means the portal's default wording
 *  lines N M [d]       a list of at most M texts of N; (d): absent means default, [] means none
 *  rich                {ar: nodes, en: nodes} (RichText)
 *  media image|video   {media: uuid, alt: text|null} | null
 *  link [req] [kind]   {label: text 40, action} | null; with a kind, absent means a link of that kind
 *  enum [values]       the first is the default
 *  bool default
 *  int min max default
 *  ids N               a list of at most N uuids (the live blocks' `only`)
 *  id                  one uuid or null
 *  plain N [req]       one string in no language
 *  email, phone, url   null when empty
 *  icon                one of ICONS
 *  show [keys]         {key: bool}, each true by default
 *  items min max {fields}
 *  source              the video block's source
 */
final class Blocks
{
    public const MAX_BLOCKS = 40;

    /** Types a page holds one of at most. */
    public const SINGLE = ['hero', 'form', 'pricing', 'teachers', 'subjects', 'app'];

    /** Types whose content comes from elsewhere in the academy (their data holds display options and filters). */
    public const LIVE = ['subjects', 'teachers', 'pricing', 'app', 'form'];

    public const TONES = ['paper', 'white', 'tint', 'brand', 'night'];

    public const ALIGN = ['start', 'center'];

    public const SPACE = ['compact', 'normal', 'roomy'];

    public const WIDTH = ['narrow', 'normal', 'wide', 'full'];

    /** The portal's icon set (apps/web/src/components/site/icons.tsx). */
    public const ICONS = ['users', 'report', 'calendar', 'map', 'shield', 'heart', 'star', 'check', 'play', 'book', 'clock', 'globe', 'chat', 'award', 'laptop', 'phone'];

    /** The old page's anchors, kept by the migrated blocks so links such as /ar#pricing still land. */
    public const ANCHOR = '/^[a-z][a-z0-9-]{0,31}$/';

    /** Every block's id: 8 characters, made by the server. */
    public const ID = '/^[a-z0-9]{8}$/';

    private const HEADING = ['eyebrow' => ['text', 40], 'title' => ['text', 120, 'd'], 'lead' => ['text', 300, 'd']];

    /** @var array<string, array{variants: list<string>, tone: string, fields: array<string, list<mixed>>}>|null */
    private static ?array $types = null;

    /** @return array<string, array{variants: list<string>, tone: string, fields: array<string, list<mixed>>}> */
    public static function all(): array
    {
        if (self::$types !== null) {
            return self::$types;
        }
        $h = self::HEADING;
        $pair = ['title' => ['text', 60], 'text' => ['text', 200]];

        return self::$types = [
            'hero' => ['variants' => ['centered', 'split', 'video', 'phones', 'minimal'], 'tone' => 'paper', 'fields' => [
                'kicker' => ['text', 60, 'd'],
                'title' => ['text', 120, 'req'],
                'lead' => ['text', 300],
                'primary' => ['link', 'trial'],
                'secondary' => ['link', 'sign_in'],
                'trust' => ['lines', 60, 4, 'd'],
                'media' => ['media', 'image'],
                'video' => ['media', 'video'],
                'poster' => ['media', 'image'],
                'media_side' => ['enum', ['end', 'start']],
                'media_shape' => ['enum', ['rounded', 'arch', 'circle', 'bleed']],
                'scrim' => ['enum', ['medium', 'light', 'strong']],
                'show' => ['show', ['kicker', 'lead', 'buttons', 'trust', 'media']],
            ]],
            'features' => ['variants' => ['cards', 'icons', 'list', 'numbers'], 'tone' => 'white', 'fields' => $h + [
                'items' => ['items', 1, 9, ['icon' => ['icon'], 'title' => ['text', 60, 'req'], 'text' => ['text', 200]]],
                'columns' => ['enum', [3, 2]],
            ]],
            'subjects' => ['variants' => ['cards', 'list', 'chips'], 'tone' => 'paper', 'fields' => $h + [
                'only' => ['ids', 50],
                'show_levels' => ['bool', true],
                'show_programs' => ['bool', true],
                'show_categories' => ['bool', true],
                'ask_label' => ['text', 40, 'd'],
            ]],
            'teachers' => ['variants' => ['cards', 'portraits', 'compact'], 'tone' => 'white', 'fields' => $h + [
                'only' => ['ids', 50],
                'max' => ['int', 1, 24, 12],
                'show_bio' => ['bool', true],
                'show_languages' => ['bool', true],
                'show_ages' => ['bool', true],
                'show_badges' => ['bool', true],
            ]],
            'pricing' => ['variants' => ['cards', 'table'], 'tone' => 'white', 'fields' => $h + [
                'only' => ['ids', 12],
                'highlight' => ['id'],
                'highlight_label' => ['text', 30, 'd'],
                'per_lesson' => ['bool', true],
                'note' => ['text', 300],
            ]],
            'steps' => ['variants' => ['line', 'cards'], 'tone' => 'paper', 'fields' => $h + [
                'items' => ['items', 0, 6, $pair, 'd'],
            ]],
            'app' => ['variants' => ['phones', 'image', 'plain'], 'tone' => 'night', 'fields' => $h + [
                'points' => ['items', 0, 4, $pair, 'd'],
                'show_code' => ['bool', true],
                'show_stores' => ['bool', true],
                'media' => ['media', 'image'],
            ]],
            'testimonials' => ['variants' => ['marquee', 'grid', 'single'], 'tone' => 'paper', 'fields' => $h + [
                'items' => ['items', 0, 12, [
                    'name' => ['plain', 60, 'req'],
                    'role' => ['text', 60],
                    'quote' => ['text', 400, 'req'],
                    'photo' => ['media', 'image'],
                    'rating' => ['int', 0, 5, 0],
                ]],
            ]],
            'faq' => ['variants' => ['accordion', 'two_columns'], 'tone' => 'white', 'fields' => $h + [
                'items' => ['items', 1, 20, ['q' => ['text', 160, 'req'], 'a' => ['text', 800, 'req']]],
            ]],
            'stats' => ['variants' => ['band', 'cards'], 'tone' => 'tint', 'fields' => $h + [
                'items' => ['items', 2, 6, ['value' => ['text', 12, 'req'], 'label' => ['text', 60, 'req']]],
            ]],
            'rich_text' => ['variants' => ['article', 'two_columns'], 'tone' => 'white', 'fields' => $h + [
                'body' => ['rich'],
            ]],
            'image_text' => ['variants' => ['side', 'overlap', 'stacked'], 'tone' => 'white', 'fields' => $h + [
                'body' => ['rich'],
                'media' => ['media', 'image'],
                'video' => ['media', 'video'],
                'poster' => ['media', 'image'],
                'media_side' => ['enum', ['start', 'end']],
                'bullets' => ['lines', 120, 6],
                'link' => ['link'],
            ]],
            'gallery' => ['variants' => ['grid', 'masonry', 'carousel'], 'tone' => 'white', 'fields' => $h + [
                'items' => ['items', 2, 24, ['media' => ['media', 'image', 'req'], 'caption' => ['text', 120]]],
                'ratio' => ['enum', ['auto', '1:1', '4:3', '3:2', '16:9']],
            ]],
            'video' => ['variants' => ['framed', 'wide', 'side'], 'tone' => 'white', 'fields' => $h + [
                'source' => ['source'],
                'caption' => ['text', 200],
                'ratio' => ['enum', ['16:9', '4:3', '1:1', '9:16']],
                'autoplay_muted' => ['bool', false],
            ]],
            'logos' => ['variants' => ['row', 'marquee', 'grid'], 'tone' => 'white', 'fields' => $h + [
                'items' => ['items', 2, 24, ['media' => ['media', 'image', 'req'], 'name' => ['plain', 60, 'req'], 'url' => ['url']]],
                'mono' => ['bool', true],
            ]],
            'cta' => ['variants' => ['banner', 'card', 'split'], 'tone' => 'brand', 'fields' => [
                'title' => ['text', 120, 'req'],
                'lead' => ['text', 300],
                'primary' => ['link', 'trial', 'req'],
                'secondary' => ['link'],
                'media' => ['media', 'image'],
            ]],
            'form' => ['variants' => ['split', 'centered'], 'tone' => 'night', 'fields' => $h + [
                'points' => ['lines', 120, 5, 'd'],
                'teach' => ['bool', false],
                'whatsapp_button' => ['bool', true],
            ]],
            'contact' => ['variants' => ['cards', 'band'], 'tone' => 'paper', 'fields' => $h + [
                'email' => ['email'],
                'phone' => ['phone'],
                'address' => ['text', 300],
                'hours' => ['text', 120],
                'whatsapp' => ['bool', true],
            ]],
            'spacer' => ['variants' => ['space', 'divider'], 'tone' => 'white', 'fields' => [
                'size' => ['enum', ['md', 'sm', 'lg']],
            ]],
        ];
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** @return list<string> */
    public static function variants(string $type): array
    {
        return self::all()[$type]['variants'] ?? [];
    }

    /** @return array<string, list<mixed>> */
    public static function fields(string $type): array
    {
        return self::all()[$type]['fields'] ?? [];
    }

    /** The tone a block of this type and variant takes when none is set: night for the phones and video openings. */
    public static function tone(string $type, string $variant): string
    {
        if ($type === 'hero' && in_array($variant, ['phones', 'video'], true)) {
            return 'night';
        }

        return self::all()[$type]['tone'] ?? 'white';
    }

    /** Whether a field spec carries a flag ('req', 'd'). */
    public static function flag(array $spec, string $flag): bool
    {
        return in_array($flag, array_slice($spec, 1), true);
    }
}
