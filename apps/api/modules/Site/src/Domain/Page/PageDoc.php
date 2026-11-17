<?php

namespace Modules\Site\Domain\Page;

/**
 * The academy's page as the builder keeps it, in `site_pages.draft` and
 * `.published` (site-builder.md, 1.2): {v, settings, blocks}. normalize()
 * keeps the known fields of each block's type (Blocks) and drops the rest,
 * cleans every word and holds it to its length, makes ids for blocks that
 * have none (or a taken one), and turns an unknown variant into the type's
 * first. It checks shape and lengths only: whether the page may go live is
 * PublishRules'. A list the portal fills with its own words keeps "never
 * set" (no key) apart from "none" ([]). Plain loops (Scramble). Pure.
 */
final class PageDoc
{
    public const VERSION = 1;

    public const MAX_BYTES = 262144;

    public const AUDIENCES = ['families', 'adults'];

    public const ACCENTS = ['primary', 'attention', 'info'];

    public const MOODS = ['light', 'warm', 'night'];

    public const CORNERS = ['brand', 'sharp', 'soft', 'round'];

    public const MOTION = ['subtle', 'full', 'none'];

    public const HEADER_STYLES = ['overlay', 'solid', 'minimal'];

    public const ACTIONS = ['trial', 'teach', 'sign_in', 'whatsapp', 'block', 'url'];

    /** A social network and the hosts its addresses may be on. */
    public const SOCIAL = [
        'instagram' => ['instagram.com'],
        'x' => ['x.com', 'twitter.com'],
        'facebook' => ['facebook.com', 'fb.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'tiktok' => ['tiktok.com'],
        'linkedin' => ['linkedin.com'],
        'snapchat' => ['snapchat.com'],
        'telegram' => ['t.me', 'telegram.me'],
    ];

    public const MAX_FOOTER_LINKS = 6;

    public const MAX_SOCIAL = 8;

    /**
     * The document cleaned, or the problems that stop it being kept.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>|null  $fonts  the families a page may use (BrandingApi::fontFamilies()); null takes any name
     * @return array{v: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     *
     * @throws InvalidPage
     */
    public static function normalize(array $input, ?array $fonts = null): array
    {
        $problems = new Problems;
        $input = self::upgrade($input);
        $settings = self::settings(is_array($input['settings'] ?? null) ? $input['settings'] : [], $fonts, $problems);

        $blocks = [];
        $ids = [];
        $anchors = [];
        $i = 0;
        foreach (is_array($input['blocks'] ?? null) ? $input['blocks'] : [] as $raw) {
            if (! is_array($raw) || ! is_string($raw['type'] ?? null) || ! Blocks::has($raw['type'])) {
                continue;
            }
            $block = self::block($raw, "blocks.{$i}", $problems);
            if (! is_string($block['id']) || preg_match(Blocks::ID, $block['id']) !== 1 || isset($ids[$block['id']])) {
                do {
                    $block['id'] = self::newId();
                } while (isset($ids[$block['id']]));
            }
            $ids[$block['id']] = true;
            if (isset($block['anchor']) && (isset($anchors[$block['anchor']]) || str_starts_with($block['anchor'], 'b-'))) {
                unset($block['anchor']);
            }
            if (isset($block['anchor'])) {
                $anchors[$block['anchor']] = true;
            }
            $blocks[] = $block;
            $i++;
        }

        $doc = ['v' => self::VERSION, 'settings' => $settings, 'blocks' => $blocks];
        if (strlen((string) json_encode($doc, JSON_UNESCAPED_UNICODE)) > self::MAX_BYTES) {
            $problems->add('blocks', 'too_big', ['kb' => (int) (self::MAX_BYTES / 1024)]);
        }
        $problems->throwIfAny();

        return $doc;
    }

    /** A page with nothing on it: the settings' defaults and no blocks. */
    public static function blank(): array
    {
        return self::normalize(['settings' => [], 'blocks' => []]);
    }

    /**
     * An older document read as this version (there is only version 1 for now).
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    public static function upgrade(array $doc): array
    {
        $doc['v'] = self::VERSION;

        return $doc;
    }

    /** A new block's id: 8 letters and digits. */
    public static function newId(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $id = '';
        for ($i = 0; $i < 8; $i++) {
            $id .= $chars[random_int(0, 35)];
        }

        return $id;
    }

    /** The id the page gives a block's element: its old anchor, the form's `join`, else "b-{id}". */
    public static function anchor(array $block): string
    {
        if (($block['type'] ?? null) === 'form') {
            return 'join';
        }

        return isset($block['anchor']) && is_string($block['anchor']) ? $block['anchor'] : 'b-'.$block['id'];
    }

    /**
     * One block's envelope and data, cleaned.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function block(array $raw, string $path, Problems $problems): array
    {
        $type = (string) $raw['type'];
        $variants = Blocks::variants($type);
        $variant = in_array($raw['variant'] ?? null, $variants, true) ? $raw['variant'] : $variants[0];
        $style = is_array($raw['style'] ?? null) ? $raw['style'] : [];

        $block = [
            'id' => is_string($raw['id'] ?? null) ? $raw['id'] : '',
            'type' => $type,
            'variant' => $variant,
            'hidden' => filter_var($raw['hidden'] ?? false, FILTER_VALIDATE_BOOL),
            'nav' => self::text($raw['nav'] ?? null, 24, "{$path}.nav", $problems),
        ];
        if (is_string($raw['anchor'] ?? null) && preg_match(Blocks::ANCHOR, $raw['anchor']) === 1) {
            $block['anchor'] = $raw['anchor'];
        }
        $block['style'] = $type === 'spacer'
            ? ['tone' => self::pick($style['tone'] ?? null, Blocks::TONES, Blocks::tone($type, $variant)), 'align' => 'start', 'space' => 'normal', 'width' => 'normal', 'eyebrow' => false]
            : [
                'tone' => self::pick($style['tone'] ?? null, Blocks::TONES, Blocks::tone($type, $variant)),
                'align' => self::pick($style['align'] ?? null, Blocks::ALIGN),
                'space' => self::pick($style['space'] ?? null, Blocks::SPACE, 'normal'),
                'width' => self::pick($style['width'] ?? null, Blocks::WIDTH, 'normal'),
                'eyebrow' => filter_var($style['eyebrow'] ?? false, FILTER_VALIDATE_BOOL),
            ];

        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $block['data'] = self::fields(Blocks::fields($type), $data, "{$path}.data", $problems);

        return $block;
    }

    /**
     * @param  array<string, list<mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function fields(array $fields, array $data, string $path, Problems $problems): array
    {
        $out = [];
        foreach ($fields as $name => $spec) {
            $present = array_key_exists($name, $data) && $data[$name] !== null;
            $value = $data[$name] ?? null;
            $p = "{$path}.{$name}";
            switch ($spec[0]) {
                case 'text':
                    $out[$name] = self::text($value, (int) $spec[1], $p, $problems);
                    break;
                case 'lines':
                    if (! $present && Blocks::flag($spec, 'd')) {
                        break;
                    }
                    $out[$name] = self::lines($value, (int) $spec[1], (int) $spec[2], $p, $problems);
                    break;
                case 'rich':
                    $value = is_array($value) ? $value : [];
                    $out[$name] = [
                        'ar' => RichText::clean($value['ar'] ?? [], "{$p}.ar", $problems),
                        'en' => RichText::clean($value['en'] ?? [], "{$p}.en", $problems),
                    ];
                    break;
                case 'media':
                    $out[$name] = self::media($value, $p, $problems);
                    break;
                case 'link':
                    $default = is_string($spec[1] ?? null) && in_array($spec[1], self::ACTIONS, true) ? $spec[1] : null;
                    $out[$name] = array_key_exists($name, $data)
                        ? self::link($value, $p, $problems)
                        : ($default !== null ? ['label' => ['ar' => '', 'en' => ''], 'action' => ['kind' => $default]] : null);
                    break;
                case 'enum':
                    $out[$name] = self::pick($value, $spec[1]);
                    break;
                case 'bool':
                    $out[$name] = $present ? filter_var($value, FILTER_VALIDATE_BOOL) : (bool) $spec[1];
                    break;
                case 'int':
                    $out[$name] = is_numeric($value) ? max((int) $spec[1], min((int) $spec[2], (int) $value)) : (int) $spec[3];
                    break;
                case 'ids':
                    $ids = [];
                    foreach (is_array($value) ? $value : [] as $id) {
                        $id = Clean::uuid($id);
                        if ($id !== null && ! in_array($id, $ids, true)) {
                            $ids[] = $id;
                        }
                    }
                    if (count($ids) > (int) $spec[1]) {
                        $problems->add($p, 'too_many', ['max' => (int) $spec[1]]);
                    }
                    $out[$name] = $ids;
                    break;
                case 'id':
                    $out[$name] = Clean::uuid($value);
                    break;
                case 'plain':
                    $text = Clean::text($value);
                    if (Clean::length($text) > (int) $spec[1]) {
                        $problems->add($p, 'too_long', ['max' => (int) $spec[1]]);
                    }
                    $out[$name] = $text;
                    break;
                case 'email':
                    $email = strtolower(Clean::text($value));
                    if ($email !== '' && ! Clean::isEmail($email)) {
                        $problems->add($p, 'email');
                    }
                    $out[$name] = $email === '' ? null : $email;
                    break;
                case 'phone':
                    $phone = Clean::phone($value);
                    if ($phone !== '' && ! Clean::isPhone($phone)) {
                        $problems->add($p, 'phone');
                    }
                    $out[$name] = $phone === '' ? null : $phone;
                    break;
                case 'url':
                    $url = Clean::text($value);
                    if ($url !== '' && ! Clean::isHttps($url)) {
                        $problems->add($p, 'https');
                    }
                    $out[$name] = $url === '' ? null : $url;
                    break;
                case 'icon':
                    $out[$name] = in_array($value, Blocks::ICONS, true) ? $value : 'check';
                    break;
                case 'show':
                    $show = [];
                    foreach ($spec[1] as $key) {
                        $show[$key] = is_array($value) && array_key_exists($key, $value) ? filter_var($value[$key], FILTER_VALIDATE_BOOL) : true;
                    }
                    $out[$name] = $show;
                    break;
                case 'items':
                    if (! $present && Blocks::flag($spec, 'd')) {
                        break;
                    }
                    $items = [];
                    $n = 0;
                    foreach (is_array($value) ? $value : [] as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $items[] = self::fields($spec[3], $item, "{$p}.{$n}", $problems);
                        $n++;
                    }
                    if (count($items) > (int) $spec[2]) {
                        $problems->add($p, 'too_many', ['max' => (int) $spec[2]]);
                    }
                    $out[$name] = $items;
                    break;
                case 'source':
                    $out[$name] = self::source($value, $p, $problems);
                    break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $in
     * @param  list<string>|null  $fonts
     * @return array<string, mixed>
     */
    private static function settings(array $in, ?array $fonts, Problems $problems): array
    {
        $header = is_array($in['header'] ?? null) ? $in['header'] : [];
        $footer = is_array($in['footer'] ?? null) ? $in['footer'] : [];
        $seo = is_array($in['seo'] ?? null) ? $in['seo'] : [];
        $font = function (mixed $name) use ($fonts): ?string {
            if (! is_string($name) || $name === '' || mb_strlen($name) > 60) {
                return null;
            }

            return $fonts === null || in_array($name, $fonts, true) ? $name : null;
        };

        $links = [];
        foreach (is_array($footer['links'] ?? null) ? $footer['links'] : [] as $n => $link) {
            if (! is_array($link)) {
                continue;
            }
            $url = Clean::text($link['url'] ?? '');
            $label = self::text($link['label'] ?? null, 40, "settings.footer.links.{$n}.label", $problems);
            if ($url === '' && $label['ar'] === '' && $label['en'] === '') {
                continue;
            }
            if (! Clean::isLinkUrl($url)) {
                $problems->add("settings.footer.links.{$n}.url", 'link_url');
            }
            $links[] = ['label' => $label, 'url' => $url];
        }
        if (count($links) > self::MAX_FOOTER_LINKS) {
            $problems->add('settings.footer.links', 'too_many', ['max' => self::MAX_FOOTER_LINKS]);
        }

        $social = [];
        foreach (is_array($footer['social'] ?? null) ? $footer['social'] : [] as $n => $row) {
            if (! is_array($row)) {
                continue;
            }
            $network = is_string($row['network'] ?? null) && isset(self::SOCIAL[$row['network']]) ? $row['network'] : null;
            $url = Clean::text($row['url'] ?? '');
            if ($url === '') {
                continue;
            }
            if ($network === null || ! self::onNetwork($url, $network)) {
                $problems->add("settings.footer.social.{$n}.url", 'social', ['network' => (string) ($row['network'] ?? '')]);
            }
            $social[] = ['network' => $network ?? 'instagram', 'url' => $url];
        }
        if (count($social) > self::MAX_SOCIAL) {
            $problems->add('settings.footer.social', 'too_many', ['max' => self::MAX_SOCIAL]);
        }

        $whatsapp = Clean::phone($in['whatsapp'] ?? '');
        if ($whatsapp !== '' && ! Clean::isPhone($whatsapp)) {
            $problems->add('settings.whatsapp', 'phone');
        }

        return [
            'audience' => self::pick($in['audience'] ?? null, self::AUDIENCES),
            'fonts' => [
                'heading' => $font(is_array($in['fonts'] ?? null) ? ($in['fonts']['heading'] ?? null) : null),
                'body' => $font(is_array($in['fonts'] ?? null) ? ($in['fonts']['body'] ?? null) : null),
            ],
            'accent' => self::pick($in['accent'] ?? null, self::ACCENTS),
            'mood' => self::pick($in['mood'] ?? null, self::MOODS),
            'corners' => self::pick($in['corners'] ?? null, self::CORNERS),
            'motion' => self::pick($in['motion'] ?? null, self::MOTION),
            'header' => [
                'style' => self::pick($header['style'] ?? null, self::HEADER_STYLES),
                'cta' => array_key_exists('cta', $header)
                    ? self::link($header['cta'], 'settings.header.cta', $problems)
                    : ['label' => ['ar' => '', 'en' => ''], 'action' => ['kind' => 'trial']],
                'sign_in' => array_key_exists('sign_in', $header) ? filter_var($header['sign_in'], FILTER_VALIDATE_BOOL) : true,
                'language_switch' => array_key_exists('language_switch', $header) ? filter_var($header['language_switch'], FILTER_VALIDATE_BOOL) : true,
            ],
            'footer' => [
                'text' => self::text($footer['text'] ?? null, 300, 'settings.footer.text', $problems),
                'links' => $links,
                'social' => $social,
            ],
            'whatsapp' => $whatsapp,
            'floating_whatsapp' => array_key_exists('floating_whatsapp', $in) ? filter_var($in['floating_whatsapp'], FILTER_VALIDATE_BOOL) : true,
            'seo' => [
                'title' => self::text($seo['title'] ?? null, 70, 'settings.seo.title', $problems),
                'description' => self::text($seo['description'] ?? null, 170, 'settings.seo.description', $problems),
                'image' => self::media($seo['image'] ?? null, 'settings.seo.image', $problems),
            ],
        ];
    }

    /** @return array{ar: string, en: string} */
    public static function text(mixed $value, int $max, string $path, Problems $problems): array
    {
        $value = is_array($value) ? $value : [];
        $out = [];
        foreach (Clean::LOCALES as $locale) {
            $text = Clean::text($value[$locale] ?? '');
            if (Clean::length($text) > $max) {
                $problems->add("{$path}.{$locale}", 'too_long', ['max' => $max]);
            }
            $out[$locale] = $text;
        }

        return $out;
    }

    /** @return list<array{ar: string, en: string}> */
    private static function lines(mixed $value, int $max, int $count, string $path, Problems $problems): array
    {
        $out = [];
        $n = 0;
        foreach (is_array($value) ? $value : [] as $line) {
            $pair = self::text($line, $max, "{$path}.{$n}", $problems);
            $n++;
            if ($pair['ar'] !== '' || $pair['en'] !== '') {
                $out[] = $pair;
            }
        }
        if (count($out) > $count) {
            $problems->add($path, 'too_many', ['max' => $count]);
        }

        return $out;
    }

    /** @return array{media: string, alt: array{ar: string, en: string}|null}|null */
    private static function media(mixed $value, string $path, Problems $problems): ?array
    {
        if (is_string($value)) {
            $value = ['media' => $value];
        }
        $id = is_array($value) ? Clean::uuid($value['media'] ?? null) : null;
        if ($id === null) {
            return null;
        }
        $alt = is_array($value['alt'] ?? null) ? self::text($value['alt'], 200, "{$path}.alt", $problems) : null;

        return ['media' => $id, 'alt' => $alt !== null && ($alt['ar'] !== '' || $alt['en'] !== '') ? $alt : null];
    }

    /** @return array{label: array{ar: string, en: string}, action: array<string, string>}|null */
    private static function link(mixed $value, string $path, Problems $problems): ?array
    {
        if (! is_array($value) || ! is_array($value['action'] ?? null)) {
            return null;
        }
        $kind = $value['action']['kind'] ?? null;
        if (! in_array($kind, self::ACTIONS, true)) {
            return null;
        }
        $action = ['kind' => $kind];
        if ($kind === 'block') {
            $block = $value['action']['block'] ?? null;
            if (! is_string($block) || preg_match(Blocks::ID, $block) !== 1) {
                $problems->add("{$path}.action.block", 'link_block');
            }
            $action['block'] = is_string($block) ? substr($block, 0, 8) : '';
        }
        if ($kind === 'url') {
            $url = Clean::text($value['action']['url'] ?? '');
            if (! Clean::isLinkUrl($url)) {
                $problems->add("{$path}.action.url", 'link_url');
            }
            $action['url'] = mb_substr($url, 0, 500);
        }

        return ['label' => self::text($value['label'] ?? null, 40, "{$path}.label", $problems), 'action' => $action];
    }

    /** @return array<string, mixed> */
    private static function source(mixed $value, string $path, Problems $problems): array
    {
        $value = is_array($value) ? $value : [];
        $kind = in_array($value['kind'] ?? null, ['upload', 'youtube', 'vimeo'], true) ? $value['kind'] : 'upload';
        $poster = self::media($value['poster'] ?? null, "{$path}.poster", $problems);
        if ($kind === 'upload') {
            return ['kind' => 'upload', 'video' => self::media($value['video'] ?? null, "{$path}.video", $problems), 'poster' => $poster];
        }
        $raw = Clean::text($value['id'] ?? ($value['url'] ?? ''));
        $id = $kind === 'youtube' ? Clean::youtube($raw) : Clean::vimeo($raw);
        if ($raw !== '' && $id === null) {
            $problems->add("{$path}.id", $kind);
        }

        return ['kind' => $kind, 'id' => $id ?? '', 'poster' => $poster];
    }

    /** The value when it is one of the options (compared as written), else the default (the first option). */
    private static function pick(mixed $value, array $options, mixed $default = null): mixed
    {
        if (is_scalar($value)) {
            foreach ($options as $option) {
                if ((string) $option === (string) $value) {
                    return $option;
                }
            }
        }

        return $default ?? $options[0];
    }

    private static function onNetwork(string $url, string $network): bool
    {
        if (! Clean::isHttps($url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::SOCIAL[$network] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
