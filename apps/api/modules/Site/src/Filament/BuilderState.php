<?php

namespace Modules\Site\Filament;

use Illuminate\Support\Str;
use Modules\Site\Domain\Page\Blocks;
use Modules\Site\Domain\Page\RichText;

/**
 * The page builder's form state, to and from a PageDoc. The form keeps each
 * block as a Filament Builder item ({type, data: {id, variant, hidden, nav,
 * anchor, style, content}}), rich text as the editor's TipTap JSON, a button
 * with "none" as its own choice, a file as {media, alt}, and a list the
 * portal fills with its own words with a "{name}_default" switch (on: never
 * set). It also turns a document's paths ("blocks.3.data.items.0.title.ar")
 * into the form's ("data.blocks.{uuid}.data.content.items.{uuid}.title.ar"),
 * so a field shows its own error.
 */
final class BuilderState
{
    /** @return array{blocks: array<string, array<string, mixed>>} */
    public static function blocksToForm(array $doc): array
    {
        $blocks = [];
        foreach ($doc['blocks'] ?? [] as $block) {
            $data = [
                'id' => $block['id'],
                'variant' => $block['variant'],
                'hidden' => (bool) $block['hidden'],
                'nav' => $block['nav'],
                'style' => $block['style'],
                'content' => self::contentToForm(Blocks::fields($block['type']), $block['data'] ?? []),
            ];
            if (isset($block['anchor'])) {
                $data['anchor'] = $block['anchor'];
            }
            $blocks[(string) Str::uuid()] = ['type' => $block['type'], 'data' => $data];
        }

        return ['blocks' => $blocks];
    }

    /** @return array<string, mixed> */
    public static function settingsToForm(array $settings): array
    {
        $settings['header']['cta'] = self::linkToForm($settings['header']['cta'] ?? null);
        $settings['seo']['image'] = $settings['seo']['image'] ?? ['media' => null, 'alt' => null];

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $blocksState  the form's "data" ({blocks})
     * @param  array<string, mixed>  $settingsState  the form's "settings"
     * @return array{settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function toDoc(array $blocksState, array $settingsState): array
    {
        $blocks = [];
        foreach (is_array($blocksState['blocks'] ?? null) ? $blocksState['blocks'] : [] as $item) {
            if (! is_array($item) || ! is_string($item['type'] ?? null) || ! Blocks::has($item['type'])) {
                continue;
            }
            $d = is_array($item['data'] ?? null) ? $item['data'] : [];
            $block = [
                'id' => (string) ($d['id'] ?? ''),
                'type' => $item['type'],
                'variant' => $d['variant'] ?? null,
                'hidden' => (bool) ($d['hidden'] ?? false),
                'nav' => $d['nav'] ?? null,
                'style' => $d['style'] ?? [],
                'data' => self::contentFromForm(Blocks::fields($item['type']), is_array($d['content'] ?? null) ? $d['content'] : []),
            ];
            if (is_string($d['anchor'] ?? null) && $d['anchor'] !== '') {
                $block['anchor'] = $d['anchor'];
            }
            $blocks[] = $block;
        }

        $settings = $settingsState;
        $settings['header']['cta'] = self::linkFromForm($settingsState['header']['cta'] ?? null);
        $settings['seo']['image'] = self::mediaFromForm($settingsState['seo']['image'] ?? null);
        foreach (['heading', 'body'] as $role) {
            $font = $settingsState['fonts'][$role] ?? null;
            $settings['fonts'][$role] = is_string($font) && $font !== '' ? $font : null;
        }
        $settings['footer']['links'] = array_values(is_array($settingsState['footer']['links'] ?? null) ? $settingsState['footer']['links'] : []);
        $settings['footer']['social'] = array_values(is_array($settingsState['footer']['social'] ?? null) ? $settingsState['footer']['social'] : []);

        return ['settings' => $settings, 'blocks' => $blocks];
    }

    /**
     * A document's path → the form's, walking the form's lists by position.
     *
     * @param  array<string, mixed>  $blocksState
     */
    public static function formPath(string $docPath, array $blocksState): ?string
    {
        if (str_starts_with($docPath, 'settings.')) {
            return $docPath;
        }
        if (preg_match('/^blocks\.(\d+)(?:\.(data|nav)(?:\.(.*))?)?$/', $docPath, $m) !== 1) {
            return null;
        }
        $keys = array_keys(is_array($blocksState['blocks'] ?? null) ? $blocksState['blocks'] : []);
        $key = $keys[(int) $m[1]] ?? null;
        if ($key === null) {
            return null;
        }
        $base = "data.blocks.{$key}.data";
        if (($m[2] ?? '') === 'nav') {
            return $base.'.nav'.(isset($m[3]) && $m[3] !== '' ? '.'.$m[3] : '');
        }
        $rest = isset($m[3]) && $m[3] !== '' ? explode('.', $m[3]) : [];
        $node = $blocksState['blocks'][$key]['data']['content'] ?? [];
        $out = [];
        foreach ($rest as $segment) {
            if (ctype_digit($segment) && is_array($node) && ! array_is_list($node)) {
                $segment = (string) (array_keys($node)[(int) $segment] ?? $segment);
            }
            $out[] = $segment;
            $node = is_array($node) ? ($node[$segment] ?? null) : null;
        }

        return $base.'.content'.($out !== [] ? '.'.implode('.', $out) : '');
    }

    /**
     * @param  array<string, list<mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function contentToForm(array $fields, array $data): array
    {
        $out = [];
        foreach ($fields as $name => $spec) {
            $value = $data[$name] ?? null;
            switch ($spec[0]) {
                case 'rich':
                    $out[$name] = ['ar' => RichText::toEditor($value['ar'] ?? []), 'en' => RichText::toEditor($value['en'] ?? [])];
                    break;
                case 'link':
                    $out[$name] = self::linkToForm($value);
                    break;
                case 'media':
                    $out[$name] = is_array($value) ? $value : ['media' => null, 'alt' => null];
                    break;
                case 'lines':
                    $out[$name] = is_array($value) ? $value : [];
                    if (Blocks::flag($spec, 'd')) {
                        $out[$name.'_default'] = ! array_key_exists($name, $data);
                    }
                    break;
                case 'items':
                    $items = [];
                    foreach (is_array($value) ? $value : [] as $item) {
                        $items[] = self::contentToForm($spec[3], is_array($item) ? $item : []);
                    }
                    $out[$name] = $items;
                    if (Blocks::flag($spec, 'd')) {
                        $out[$name.'_default'] = ! array_key_exists($name, $data);
                    }
                    break;
                case 'source':
                    $out[$name] = [
                        'kind' => $value['kind'] ?? 'upload',
                        'video' => $value['video'] ?? ['media' => null, 'alt' => null],
                        'poster' => $value['poster'] ?? ['media' => null, 'alt' => null],
                        'id' => $value['id'] ?? '',
                    ];
                    break;
                case 'enum':
                    $out[$name] = is_int($value) ? (string) $value : $value;
                    break;
                case 'int':
                    $out[$name] = (string) $value;
                    break;
                default:
                    $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, list<mixed>>  $fields
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private static function contentFromForm(array $fields, array $form): array
    {
        $out = [];
        foreach ($fields as $name => $spec) {
            $value = $form[$name] ?? null;
            switch ($spec[0]) {
                case 'rich':
                    $out[$name] = ['ar' => RichText::fromEditor($value['ar'] ?? null), 'en' => RichText::fromEditor($value['en'] ?? null)];
                    break;
                case 'link':
                    $out[$name] = self::linkFromForm($value);
                    break;
                case 'media':
                    $out[$name] = self::mediaFromForm($value);
                    break;
                case 'lines':
                    if (Blocks::flag($spec, 'd') && ($form[$name.'_default'] ?? false)) {
                        break;
                    }
                    $out[$name] = array_values(is_array($value) ? $value : []);
                    break;
                case 'items':
                    if (Blocks::flag($spec, 'd') && ($form[$name.'_default'] ?? false)) {
                        break;
                    }
                    $items = [];
                    foreach (is_array($value) ? $value : [] as $item) {
                        $items[] = self::contentFromForm($spec[3], is_array($item) ? $item : []);
                    }
                    $out[$name] = $items;
                    break;
                case 'source':
                    $value = is_array($value) ? $value : [];
                    $out[$name] = [
                        'kind' => $value['kind'] ?? 'upload',
                        'video' => self::mediaFromForm($value['video'] ?? null),
                        'poster' => self::mediaFromForm($value['poster'] ?? null),
                        'id' => (string) ($value['id'] ?? ''),
                    ];
                    break;
                default:
                    $out[$name] = $value;
            }
        }

        return $out;
    }

    /** @return array{label: array{ar: string, en: string}, action: array<string, string>} */
    private static function linkToForm(mixed $link): array
    {
        if (! is_array($link)) {
            return ['label' => ['ar' => '', 'en' => ''], 'action' => ['kind' => 'none', 'block' => null, 'url' => '']];
        }

        return ['label' => $link['label'], 'action' => ['kind' => $link['action']['kind'], 'block' => $link['action']['block'] ?? null, 'url' => $link['action']['url'] ?? '']];
    }

    private static function linkFromForm(mixed $form): ?array
    {
        $kind = is_array($form) ? ($form['action']['kind'] ?? null) : null;
        if (! is_string($kind) || $kind === '' || $kind === 'none') {
            return null;
        }
        $action = ['kind' => $kind];
        if ($kind === 'block') {
            $action['block'] = (string) ($form['action']['block'] ?? '');
        }
        if ($kind === 'url') {
            $action['url'] = (string) ($form['action']['url'] ?? '');
        }

        return ['label' => $form['label'] ?? ['ar' => '', 'en' => ''], 'action' => $action];
    }

    private static function mediaFromForm(mixed $form): ?array
    {
        $id = is_array($form) ? ($form['media'] ?? null) : (is_string($form) ? $form : null);

        return is_string($id) && Str::isUuid($id) ? ['media' => $id, 'alt' => is_array($form) ? ($form['alt'] ?? null) : null] : null;
    }
}
