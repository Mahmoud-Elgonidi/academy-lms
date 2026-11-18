<?php

use Illuminate\Support\Facades\DB;
use Modules\Site\Domain\Page\LegacyImport;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SitePage;

/*
 * The data migration (docs/architecture/site-builder.md, 5.1): every
 * academy's saved words become its draft and its published page, as blocks
 * that render as before; row level security stays forced on both tables; an
 * academy that already has a page keeps it.
 */

it('makes each academy\'s old page its draft and published page', function () {
    $noor = $this->createAcademy(['slug' => 'noor']);
    $huda = $this->createAcademy(['slug' => 'huda']);
    $owner = DB::connection('pgsql_owner');
    $content = SiteContent::defaults();
    $content['sections'] = ['faq', 'teach'];
    $content['hero']['title_en'] = 'Noor online';
    // Rows in the old shape, each written as its academy (row level security is forced).
    foreach ([[$noor, true, $content], [$huda, false, ['audience' => 'adults']]] as [$academy, $enabled, $saved]) {
        $this->actAsTenant($academy);
        DB::table('site_settings')->insert(['academy_id' => $academy->id, 'enabled' => $enabled, 'content' => json_encode($saved, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
    }
    $this->clearTenant();

    $migration = require base_path('modules/Site/database/migrations/2027_09_10_100100_site_pages_from_settings.php');
    $was = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_owner');
    try {
        $migration->up();
        $migration->up();   // twice: nothing more
    } finally {
        DB::setDefaultConnection($was);
    }

    expect($owner->selectOne('select count(*) as n from site_pages where academy_id in (?, ?)', [$noor->id, $huda->id]))->not->toBeNull()
        ->and($owner->selectOne("select relforcerowsecurity from pg_class where relname = 'site_pages'")->relforcerowsecurity)->toBeTrue()
        ->and($owner->selectOne("select relforcerowsecurity from pg_class where relname = 'site_settings'")->relforcerowsecurity)->toBeTrue();

    $this->actAsTenant($noor);
    $page = SitePage::query()->sole();
    expect($page->draftDoc())->toBe(LegacyImport::fromContent($content))
        ->and($page->publishedDoc())->toBe($page->draftDoc())
        ->and([$page->draft_revision, $page->published_revision, $page->template])->toBe([1, 1, 'kids_families'])
        ->and($page->draftDoc()['blocks'][0]['data']['title']['en'])->toBe('Noor online');

    $this->actAsTenant($huda);
    expect(SitePage::query()->sole()->draftDoc()['settings']['audience'])->toBe('adults');
});
