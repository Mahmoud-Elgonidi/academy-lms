<?php

namespace Modules\Site\Queries;

use Modules\Site\Domain\Page\MediaRefs;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;

/**
 * The current academy's files in use, and where (site-builder.md, 1.6): a
 * file named by the draft, the published page or any kept publish is in use
 * and cannot be deleted; site:sweep leaves it in place.
 */
final class MediaInUse
{
    /** @return array<string, list<string>> media id => the block types (and "seo") that use it */
    public static function all(): array
    {
        $page = SitePage::query()->first();
        $docs = [];
        if ($page !== null) {
            $docs[] = $page->draft;
            if ($page->published !== null) {
                $docs[] = $page->published;
            }
        }
        foreach (SitePagePublish::query()->orderByDesc('published_at')->limit(SitePagePublish::KEEP)->pluck('page') as $kept) {
            $docs[] = $kept;
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
