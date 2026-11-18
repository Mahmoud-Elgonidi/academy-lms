<?php

namespace Modules\Site\Domain\Page;

/**
 * Whether the draft may become the page (site-builder.md, 1.7), read the same
 * way by the builder's status bar and by PublishSitePage. Content rules look
 * at the visible blocks only (a hidden block is never shown); the count of
 * blocks looks at all of them. Pure.
 *
 * Blockers: more than 40 blocks; a second visible hero, form, pricing,
 * teachers, subjects or app; a required field empty in both languages (or a
 * list under its least); a video without a poster; a file that is not this
 * academy's or of the wrong kind; a button to a block that is not on the
 * page, or to an address the rules refuse; no visible block at all.
 *
 * Warnings: no visible form; a text in one language only; images smaller
 * than they will be shown; a JPEG logo; testimonials with none; the opening
 * not first; a button to a hidden block (it is left out).
 */
final class PublishRules
{
    public const HERO_MIN_WIDTH = 1200;

    public const GALLERY_MIN_WIDTH = 600;

    public const SHARE_MIN = [1200, 630];

    /** At most this many "one language" warnings are listed; the rest are counted in one. */
    private const MAX_LANGUAGE_WARNINGS = 8;

    /** @param  array<string, mixed>  $doc */
    public static function check(array $doc, Facts $facts): Verdict
    {
        $blockers = [];
        $warnings = [];
        $blocks = is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [];
        $issue = fn (string $code, ?array $block = null, ?string $field = null, array $params = []) => [
            'code' => $code, 'block' => $block['id'] ?? null, 'type' => $block['type'] ?? null, 'field' => $field, 'params' => $params,
        ];

        if (count($blocks) > Blocks::MAX_BLOCKS) {
            $blockers[] = $issue('too_many_blocks', null, null, ['max' => Blocks::MAX_BLOCKS]);
        }

        $visible = [];
        $all = [];
        foreach ($blocks as $block) {
            $all[$block['id']] = $block;
            if (! ($block['hidden'] ?? false)) {
                $visible[] = $block;
            }
        }
        if ($visible === []) {
            $blockers[] = $issue('nothing_visible');
        }

        $seen = [];
        $language = [];
        $hasForm = false;
        foreach ($visible as $n => $block) {
            $type = $block['type'];
            $seen[$type] = ($seen[$type] ?? 0) + 1;
            if (in_array($type, Blocks::SINGLE, true) && $seen[$type] === 2) {
                $blockers[] = $issue('second_of', $block);
            }
            if ($type === 'form') {
                $hasForm = true;
            }
            if ($type === 'hero' && $n > 0) {
                $warnings[] = $issue('hero_not_first', $block);
            }
            if ($type === 'testimonials' && ($block['data']['items'] ?? []) === []) {
                $warnings[] = $issue('testimonials_empty', $block);
            }
            self::fields(Blocks::fields($type), $block['data'] ?? [], '', $block, $facts, $all, $blockers, $warnings, $language, $issue);
            self::posters($block, $blockers, $issue);
        }

        $settings = is_array($doc['settings'] ?? null) ? $doc['settings'] : [];
        $share = $settings['seo']['image']['media'] ?? null;
        if (is_string($share)) {
            $file = $facts->media($share);
            if ($file === null || $file['kind'] !== 'image') {
                $blockers[] = $issue('media_wrong', null, 'seo.image');
            } elseif (($file['width'] ?? 0) < self::SHARE_MIN[0] || ($file['height'] ?? 0) < self::SHARE_MIN[1]) {
                $warnings[] = $issue('share_small', null, 'seo.image', ['width' => self::SHARE_MIN[0], 'height' => self::SHARE_MIN[1]]);
            }
        }
        $cta = $settings['header']['cta'] ?? null;
        if (is_array($cta)) {
            self::link($cta, 'header.cta', null, $all, $blockers, $warnings, $issue);
        }

        if (! $hasForm && $visible !== []) {
            $warnings[] = $issue('no_form');
        }
        if (count($language) > self::MAX_LANGUAGE_WARNINGS) {
            $more = count($language) - self::MAX_LANGUAGE_WARNINGS;
            $language = array_slice($language, 0, self::MAX_LANGUAGE_WARNINGS);
            $language[] = $issue('one_language_more', null, null, ['count' => $more]);
        }

        return new Verdict($blockers, [...$warnings, ...$language]);
    }

    /**
     * @param  array<string, list<mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $all
     */
    private static function fields(array $fields, array $data, string $prefix, array $block, Facts $facts, array $all, array &$blockers, array &$warnings, array &$language, \Closure $issue): void
    {
        foreach ($fields as $name => $spec) {
            $field = $prefix.$name;
            $value = $data[$name] ?? null;
            switch ($spec[0]) {
                case 'text':
                    $ar = (string) ($value['ar'] ?? '');
                    $en = (string) ($value['en'] ?? '');
                    if ($ar === '' && $en === '' && Blocks::flag($spec, 'req')) {
                        $blockers[] = $issue('required', $block, $field);
                    } elseif (($ar === '') !== ($en === '')) {
                        $language[] = $issue('one_language', $block, $field, ['missing' => $ar === '' ? 'ar' : 'en']);
                    }
                    break;
                case 'plain':
                    if ((string) $value === '' && Blocks::flag($spec, 'req')) {
                        $blockers[] = $issue('required', $block, $field);
                    }
                    break;
                case 'lines':
                    foreach (is_array($value) ? $value : [] as $i => $line) {
                        if ((($line['ar'] ?? '') === '') !== (($line['en'] ?? '') === '')) {
                            $language[] = $issue('one_language', $block, "{$field}.{$i}", ['missing' => ($line['ar'] ?? '') === '' ? 'ar' : 'en']);
                        }
                    }
                    break;
                case 'rich':
                    $ar = is_array($value['ar'] ?? null) && $value['ar'] !== [];
                    $en = is_array($value['en'] ?? null) && $value['en'] !== [];
                    if ($ar !== $en) {
                        $language[] = $issue('one_language', $block, $field, ['missing' => $ar ? 'en' : 'ar']);
                    }
                    break;
                case 'media':
                    $id = is_array($value) ? ($value['media'] ?? null) : null;
                    if ($id === null) {
                        if (Blocks::flag($spec, 'req')) {
                            $blockers[] = $issue('required', $block, $field);
                        }
                        break;
                    }
                    $file = $facts->media($id);
                    if ($file === null || $file['kind'] !== $spec[1]) {
                        $blockers[] = $issue('media_wrong', $block, $field);
                        break;
                    }
                    $width = (int) ($file['width'] ?? 0);
                    if ($block['type'] === 'hero' && $name === 'media' && $width < self::HERO_MIN_WIDTH) {
                        $warnings[] = $issue('image_small', $block, $field, ['width' => self::HERO_MIN_WIDTH]);
                    }
                    if ($block['type'] === 'gallery' && $name === 'media' && $width < self::GALLERY_MIN_WIDTH) {
                        $warnings[] = $issue('image_small', $block, $field, ['width' => self::GALLERY_MIN_WIDTH]);
                    }
                    if ($block['type'] === 'logos' && $file['mime'] === 'image/jpeg') {
                        $warnings[] = $issue('logo_jpeg', $block, $field);
                    }
                    break;
                case 'link':
                    if ($value === null) {
                        if (Blocks::flag($spec, 'req')) {
                            $blockers[] = $issue('required', $block, $field);
                        }
                        break;
                    }
                    self::link($value, $field, $block, $all, $blockers, $warnings, $issue);
                    break;
                case 'items':
                    $items = is_array($value) ? $value : null;
                    // A list the portal fills with its own words, never set: fine.
                    if ($items !== null && count($items) < (int) $spec[1]) {
                        $blockers[] = $issue('too_few', $block, $field, ['min' => (int) $spec[1]]);
                    }
                    foreach ($items ?? [] as $i => $item) {
                        self::fields($spec[3], is_array($item) ? $item : [], "{$field}.{$i}.", $block, $facts, $all, $blockers, $warnings, $language, $issue);
                    }
                    break;
                case 'source':
                    $kind = $value['kind'] ?? 'upload';
                    if ($kind === 'upload') {
                        self::fields(['video' => ['media', 'video', 'req']], is_array($value) ? $value : [], "{$field}.", $block, $facts, $all, $blockers, $warnings, $language, $issue);
                    } elseif ((string) ($value['id'] ?? '') === '') {
                        $blockers[] = $issue('required', $block, "{$field}.id");
                    }
                    self::fields(['poster' => ['media', 'image']], is_array($value) ? $value : [], "{$field}.", $block, $facts, $all, $blockers, $warnings, $language, $issue);
                    break;
            }
        }
    }

    /** A video the academy uploaded needs a poster: the first paint, and the picture while it does not play. */
    private static function posters(array $block, array &$blockers, \Closure $issue): void
    {
        $data = $block['data'] ?? [];
        if (in_array($block['type'], ['hero', 'image_text'], true) && ($data['video'] ?? null) !== null && ($data['poster'] ?? null) === null) {
            $blockers[] = $issue('poster', $block, 'poster');
        }
        if ($block['type'] === 'video' && ($data['source']['kind'] ?? null) === 'upload' && ($data['source']['video'] ?? null) !== null && ($data['source']['poster'] ?? null) === null) {
            $blockers[] = $issue('poster', $block, 'source.poster');
        }
    }

    /** @param  array<string, array<string, mixed>>  $all */
    private static function link(array $link, string $field, ?array $block, array $all, array &$blockers, array &$warnings, \Closure $issue): void
    {
        $action = $link['action'] ?? [];
        if (($action['kind'] ?? null) === 'block') {
            $target = $all[$action['block'] ?? ''] ?? null;
            if ($target === null) {
                $blockers[] = $issue('link_block', $block, $field);
            } elseif ($target['hidden'] ?? false) {
                $warnings[] = $issue('link_hidden', $block, $field);
            }
        }
        if (($action['kind'] ?? null) === 'url' && ! Clean::isLinkUrl((string) ($action['url'] ?? ''))) {
            $blockers[] = $issue('link_url', $block, $field);
        }
    }
}
