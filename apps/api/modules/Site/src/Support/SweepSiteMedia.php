<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Models\SitePagePublish;
use Modules\Site\Queries\MediaInUse;

/**
 * The page builder's part of site:sweep, in the current academy: the
 * publishing history keeps its last 20; a library file nothing uses is
 * marked, one used again is unmarked, and one unused for 7 days leaves (the
 * row now, the file after commit).
 */
final class SweepSiteMedia
{
    /** @return array{publishes: int, files: int} what left */
    public static function run(): array
    {
        $keep = SitePagePublish::query()->orderByDesc('published_at')->orderByDesc('revision')->limit(SitePagePublish::KEEP)->pluck('id')->all();
        $publishes = (int) SitePagePublish::query()->whereNotIn('id', $keep)->delete();

        $used = array_keys(MediaInUse::all());
        SiteMedia::query()->whereIn('id', $used)->whereNotNull('unused_since')->update(['unused_since' => null]);
        SiteMedia::query()->whereNotIn('id', $used)->whereNull('unused_since')->update(['unused_since' => now()]);

        $files = 0;
        foreach (SiteMedia::query()->whereNotIn('id', $used)->where('unused_since', '<', now()->subDays(SiteMedia::UNUSED_DAYS))->get() as $media) {
            DB::transaction(function () use ($media) {
                $path = $media->path;
                $media->delete();
                DB::afterCommit(fn () => Storage::disk(SiteMedia::DISK)->delete($path));
            });
            $files++;
        }

        return ['publishes' => $publishes, 'files' => $files];
    }
}
