<?php

namespace Modules\Site\Queries;

use Modules\Site\Domain\Page\MediaRefs;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;

/**
 * The current academy's files in use, and where (site-builder.md, 1.6): a
 * file named by the draft or the published page is in use and cannot be
 * deleted. site:sweep also keeps a file a kept publish names (all()), so a
 * restore finds it; the academy may still delete one only an older version
 * names (live()), and that version then shows without it.
 */
final class MediaInUse
{
    /** @return array<string, list<string>> media id => the block types (and "seo") the draft and the published page use it in */
    public static function live(): array
    {
        return self::of(false);
    }

    /** @return array<string, list<string>> media id => the block types (and "seo") that use it, kept publishes included */
    public static function all(): array
    {
        return self::of(true);
    }

    /** @return array<string, list<string>> */
    private static function of(bool $kept): array
    {
        $page = SitePage::query()->first();
        $docs = [];
        if ($page !== null) {
            $docs[] = $page->draft;
            if ($page->published !== null) {
                $docs[] = $page->published;
            }
        }
        if ($kept) {
            foreach (SitePagePublish::query()->orderByDesc('published_at')->limit(SitePagePublish::KEEP)->pluck('page') as $old) {
                $docs[] = $old;
            }
        }
        $out = [];
        foreach ($docs as $doc) {
            $doc = is_string($doc) ? json_decode($doc, true) : $doc;
            foreach (MediaRefs::usage(is_array($doc) ? $doc : []) as $id => $where) {
                $out[$id] = array_values(array_unique([...($out[$id] ?? []), ...$where]));
            }
        }

        return $out;
    }
}
