<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use Modules\Site\Actions\PublishSitePage;
use Modules\Site\Actions\SaveSiteDraft;
use Modules\Site\Models\SitePage;

/**
 * The page builder in tests: files from tests/Fixtures/site (and the demo's
 * own, modules/Site/database/seeders/media) as uploads, and a page saved
 * and published through the same Actions the builder calls. Use with
 * AcademySite, as the owner, inside the academy.
 */
trait SitePages
{
    /** An upload of a fixture's bytes under any name (the name never decides the type). */
    protected function siteFile(string $fixture, ?string $as = null): UploadedFile
    {
        $source = is_file(dirname(__DIR__).'/Fixtures/site/'.$fixture)
            ? dirname(__DIR__).'/Fixtures/site/'.$fixture
            : dirname(__DIR__, 2).'/modules/Site/database/seeders/media/'.$fixture;
        $copy = tempnam(sys_get_temp_dir(), 'site');
        copy($source, $copy);

        return new UploadedFile($copy, $as ?? $fixture, null, null, true);
    }

    /** An upload of a fixture's bytes padded to a size, in KB. */
    protected function bigSiteFile(string $fixture, int $kb, string $as): UploadedFile
    {
        $file = $this->siteFile($fixture, $as);
        $handle = fopen($file->getRealPath(), 'ab');
        $chunk = str_repeat("\0", 1024);
        for ($i = 0; $i < $kb; $i++) {
            fwrite($handle, $chunk);
        }
        fclose($handle);

        return new UploadedFile($file->getRealPath(), $as, null, null, true);
    }

    /** Saves this draft and publishes it, as the signed-in owner. */
    protected function publishPage(array $blocks, array $settings = []): SitePage
    {
        $page = app(SaveSiteDraft::class)->handle(['settings' => $settings, 'blocks' => $blocks], SitePage::current()->draft_revision);

        return app(PublishSitePage::class)->handle($page->draft_revision);
    }
}
