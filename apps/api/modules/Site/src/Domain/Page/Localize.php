<?php

namespace Modules\Site\Domain\Page;

/**
 * The page in one language, as `GET v1/site` sends it (site-builder.md, 3.1):
 * visible blocks only, in order; each text a string in the request's
 * language, the other's where it is empty, null when neither is set (the
 * portal then says its own words where it has some); files as their address,
 * size and alt; buttons that cannot work left out (a trial with no form, a
 * WhatsApp with no number, a block that is not shown); the live blocks'
 * filters kept for the server (`only`), never sent. Every field is present.
 * Plain loops (Scramble). Pure.
 */
final class Localize
{
    /**
     * @param  array<string, mixed>  $doc  a normalized PageDoc
     * @return array{settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function page(array $doc, PageContext $ctx): array
    {
        $settings = is_array($doc['settings'] ?? null) ? $doc['settings'] : [];
        $visible = [];
        foreach (is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [] as $block) {
            if (is_array($block) && ! ($block['hidden'] ?? false) && Blocks::has((string) ($block['type'] ?? ''))) {
                $visible[] = $block;
            }
        }
        $state = self::state($visible, (string) ($settings['whatsapp'] ?? ''));

        $blocks = [];
        foreach ($visible as $block) {
            $out = self::block($block, $ctx, $state);
            if ($out !== null) {
                $blocks[] = $out;
            }
        }

        return ['settings' => self::settings($settings, $ctx, $state), 'blocks' => $blocks];
    }

    /**
     * What decides whether a button can work: the visible blocks' anchors by id, the form, the number.
     *
     * @param  list<array<string, mixed>>  $visible
     * @return array{anchors: array<string, string>, form: bool, teach: bool, whatsapp: bool}
     */
    public static function state(array $visible, string $whatsapp): array
    {
        $anchors = [];
        $form = false;
        $teach = false;
        foreach ($visible as $block) {
            $anchors[(string) $block['id']] = PageDoc::anchor($block);
            if ($block['type'] === 'form') {
                $form = true;
                $teach = $teach || (bool) ($block['data']['teach'] ?? false);
            }
        }

        return ['anchors' => $anchors, 'form' => $form, 'teach' => $teach, 'whatsapp' => $whatsapp !== ''];
    }

    /** One language's word of a pair, the other's when it is empty; null when neither. */
    public static function word(mixed $pair, string $locale): ?string
    {
        if (! is_array($pair)) {
            return null;
        }
        $mine = (string) ($pair[$locale] ?? '');
        $other = (string) ($pair[$locale === 'ar' ? 'en' : 'ar'] ?? '');

        return $mine !== '' ? $mine : ($other !== '' ? $other : null);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array{anchors: array<string, string>, form: bool, teach: bool, whatsapp: bool}  $state
     * @return array<string, mixed>|null null when the block has nothing to show (a video with no file)
     */
    private static function block(array $block, PageContext $ctx, array $state): ?array
    {
        $type = (string) $block['type'];
        $data = self::fields(Blocks::fields($type), is_array($block['data'] ?? null) ? $block['data'] : [], $ctx, $state);
        if ($data === null) {
            return null;
        }
        if ($type === 'subjects') {
            $only = is_array($block['data']['only'] ?? null) ? $block['data']['only'] : [];
            $ids = [];
            foreach ($only as $id) {
                if (in_array($id, $ctx->subjects, true)) {
                    $ids[] = $id;
                }
            }
            $data['subject_ids'] = $only === [] ? $ctx->subjects : $ids;
        }
        if ($type === 'teachers') {
            unset($data['max']);
        }

        return [
            'id' => (string) $block['id'],
            'type' => $type,
            'variant' => (string) $block['variant'],
            'anchor' => PageDoc::anchor($block),
            'nav' => self::word($block['nav'] ?? null, $ctx->locale),
            'style' => $block['style'],
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, list<mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null null when a field the block cannot do without is missing
     */
    private static function fields(array $fields, array $data, PageContext $ctx, array $state): ?array
    {
        $out = [];
        foreach ($fields as $name => $spec) {
            $value = $data[$name] ?? null;
            switch ($spec[0]) {
                case 'text':
                    $word = self::word($value, $ctx->locale);
                    $out[$name] = $word ?? (Blocks::flag($spec, 'req') ? '' : null);
                    break;
                case 'lines':
                    if (! array_key_exists($name, $data) || $value === null) {
                        $out[$name] = Blocks::flag($spec, 'd') ? null : [];
                        break;
                    }
                    $lines = [];
                    foreach ($value as $line) {
                        $word = self::word($line, $ctx->locale);
                        if ($word !== null) {
                            $lines[] = $word;
                        }
                    }
                    $out[$name] = $lines;
                    break;
                case 'rich':
                    $mine = is_array($value[$ctx->locale] ?? null) ? $value[$ctx->locale] : [];
                    $other = is_array($value[$ctx->locale === 'ar' ? 'en' : 'ar'] ?? null) ? $value[$ctx->locale === 'ar' ? 'en' : 'ar'] : [];
                    $out[$name] = $mine !== [] ? $mine : $other;
                    break;
                case 'media':
                    $media = self::media($value, (string) $spec[1], $ctx);
                    if ($media === null && Blocks::flag($spec, 'req')) {
                        return null;
                    }
                    $out[$name] = $media;
                    break;
                case 'link':
                    $out[$name] = is_array($value) ? self::link($value, $ctx->locale, $state) : null;
                    break;
                case 'ids':
                    break;
                case 'plain':
                    $text = (string) $value;
                    if ($text === '' && Blocks::flag($spec, 'req')) {
                        return null;
                    }
                    $out[$name] = $text;
                    break;
                case 'items':
                    if (! array_key_exists($name, $data) || $value === null) {
                        $out[$name] = Blocks::flag($spec, 'd') ? null : [];
                        break;
                    }
                    $items = [];
                    foreach ($value as $item) {
                        $shaped = is_array($item) ? self::fields($spec[3], $item, $ctx, $state) : null;
                        if ($shaped !== null && ! self::blankItem($spec[3], $shaped)) {
                            $items[] = $shaped;
                        }
                    }
                    $out[$name] = $items;
                    break;
                case 'source':
                    $value = is_array($value) ? $value : [];
                    $poster = self::media($value['poster'] ?? null, 'image', $ctx);
                    if (($value['kind'] ?? 'upload') === 'upload') {
                        $video = self::media($value['video'] ?? null, 'video', $ctx);
                        if ($video === null) {
                            return null;
                        }
                        $out[$name] = ['kind' => 'upload', 'video' => $video, 'poster' => $poster];
                    } else {
                        if ((string) ($value['id'] ?? '') === '') {
                            return null;
                        }
                        $out[$name] = ['kind' => (string) $value['kind'], 'id' => (string) $value['id'], 'poster' => $poster];
                    }
                    break;
                default:
                    // enum, bool, int, id, email, phone, url, icon, show: as kept.
                    $out[$name] = $value;
            }
        }

        return $out;
    }

    /** An item whose words are all empty (an editor's new row): left out. */
    private static function blankItem(array $fields, array $item): bool
    {
        foreach ($fields as $name => $spec) {
            if (in_array($spec[0], ['text', 'plain', 'media'], true) && ($item[$name] ?? null) !== null && ($item[$name] ?? null) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array{url: string, width: ?int, height: ?int, alt: string, mime: string}|null */
    private static function media(mixed $ref, string $kind, PageContext $ctx): ?array
    {
        $id = is_array($ref) ? ($ref['media'] ?? null) : null;
        $file = is_string($id) ? ($ctx->media[$id] ?? null) : null;
        if ($file === null || $file['kind'] !== $kind) {
            return null;
        }

        return [
            'url' => $file['url'],
            'width' => $file['width'],
            'height' => $file['height'],
            'alt' => self::word($ref['alt'] ?? null, $ctx->locale) ?? self::word($file['alt'], $ctx->locale) ?? '',
            'mime' => $file['mime'],
        ];
    }

    /**
     * @param  array<string, mixed>  $link
     * @param  array{anchors: array<string, string>, form: bool, teach: bool, whatsapp: bool}  $state
     * @return array{label: string, action: array<string, string>}|null
     */
    public static function link(array $link, string $locale, array $state): ?array
    {
        $action = is_array($link['action'] ?? null) ? $link['action'] : [];
        $kind = (string) ($action['kind'] ?? '');
        $out = match ($kind) {
            'trial' => $state['form'] ? ['kind' => 'trial'] : null,
            'teach' => $state['teach'] ? ['kind' => 'teach'] : null,
            'sign_in' => ['kind' => 'sign_in'],
            'whatsapp' => $state['whatsapp'] ? ['kind' => 'whatsapp'] : null,
            'block' => isset($state['anchors'][(string) ($action['block'] ?? '')]) ? ['kind' => 'block', 'block' => $state['anchors'][(string) $action['block']]] : null,
            'url' => Clean::isLinkUrl((string) ($action['url'] ?? '')) ? ['kind' => 'url', 'url' => (string) $action['url']] : null,
            default => null,
        };

        return $out === null ? null : ['label' => self::word($link['label'] ?? null, $locale) ?? '', 'action' => $out];
    }

    /**
     * @param  array<string, mixed>  $s
     * @param  array{anchors: array<string, string>, form: bool, teach: bool, whatsapp: bool}  $state
     * @return array<string, mixed>
     */
    private static function settings(array $s, PageContext $ctx, array $state): array
    {
        $heading = is_string($s['fonts']['heading'] ?? null) ? $s['fonts']['heading'] : $ctx->brandFonts['heading'];
        $body = is_string($s['fonts']['body'] ?? null) ? $s['fonts']['body'] : $ctx->brandFonts['body'];
        $own = $heading === $ctx->brandFonts['heading'] && $body === $ctx->brandFonts['body'];

        $links = [];
        foreach (is_array($s['footer']['links'] ?? null) ? $s['footer']['links'] : [] as $link) {
            $url = (string) ($link['url'] ?? '');
            $label = self::word($link['label'] ?? null, $ctx->locale);
            if ($label !== null && Clean::isLinkUrl($url)) {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }
        $social = [];
        foreach (is_array($s['footer']['social'] ?? null) ? $s['footer']['social'] : [] as $row) {
            $social[] = ['network' => (string) $row['network'], 'url' => (string) $row['url']];
        }
        $cta = $s['header']['cta'] ?? null;
        $whatsapp = (string) ($s['whatsapp'] ?? '');

        return [
            'audience' => (string) ($s['audience'] ?? 'families'),
            'fonts' => ['heading' => $heading, 'body' => $body, 'url' => $own ? null : ($ctx->fontsUrl)($heading, $body)],
            'accent' => (string) ($s['accent'] ?? 'primary'),
            'mood' => (string) ($s['mood'] ?? 'light'),
            'corners' => (string) ($s['corners'] ?? 'brand'),
            'motion' => (string) ($s['motion'] ?? 'subtle'),
            'header' => [
                'style' => (string) ($s['header']['style'] ?? 'overlay'),
                'cta' => is_array($cta) ? self::link($cta, $ctx->locale, $state) : null,
                'sign_in' => (bool) ($s['header']['sign_in'] ?? true),
                'language_switch' => (bool) ($s['header']['language_switch'] ?? true),
            ],
            'footer' => [
                'text' => self::word($s['footer']['text'] ?? null, $ctx->locale),
                'links' => $links,
                'social' => $social,
            ],
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'floating_whatsapp' => (bool) ($s['floating_whatsapp'] ?? true),
            'seo' => [
                'title' => self::word($s['seo']['title'] ?? null, $ctx->locale),
                'description' => self::word($s['seo']['description'] ?? null, $ctx->locale),
                'image' => self::media($s['seo']['image'] ?? null, 'image', $ctx),
            ],
        ];
    }
}
