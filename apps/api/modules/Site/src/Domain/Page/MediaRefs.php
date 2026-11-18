<?php

namespace Modules\Site\Domain\Page;

/**
 * The media files a page's document uses (site-builder.md, 1.6): every
 * block's images, videos and posters, and the share image of its search
 * settings. A file is in use while the draft, the published page or a kept
 * publish names it. Pure.
 */
final class MediaRefs
{
    /**
     * @param  array<string, mixed>  $doc
     * @return list<string> media ids, each once
     */
    public static function of(array $doc): array
    {
        return array_keys(self::usage($doc));
    }

    /**
     * Where each file is used: the types of the blocks that name it, and
     * "seo" for the share image.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, list<string>>
     */
    public static function usage(array $doc): array
    {
        $out = [];
        foreach (is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [] as $block) {
            if (! is_array($block)) {
                continue;
            }
            $ids = [];
            self::walk($block['data'] ?? null, $ids);
            foreach ($ids as $id) {
                $type = (string) ($block['type'] ?? '');
                if (! in_array($type, $out[$id] ?? [], true)) {
                    $out[$id][] = $type;
                }
            }
        }
        $seo = $doc['settings']['seo']['image']['media'] ?? null;
        if (is_string($seo) && $seo !== '') {
            $out[$seo][] = 'seo';
        }

        return $out;
    }

    /** @param  list<string>  $ids */
    private static function walk(mixed $value, array &$ids): void
    {
        if (! is_array($value)) {
            return;
        }
        if (array_key_exists('media', $value) && array_key_exists('alt', $value) && is_string($value['media']) && $value['media'] !== '') {
            if (! in_array($value['media'], $ids, true)) {
                $ids[] = $value['media'];
            }

            return;
        }
        foreach ($value as $inner) {
            self::walk($inner, $ids);
        }
    }
}
