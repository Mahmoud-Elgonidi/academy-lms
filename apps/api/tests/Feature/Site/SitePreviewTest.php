<?php

use Illuminate\Support\Facades\Mail;
use Modules\Site\Actions\SaveSiteDraft;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SiteSettings;
use Modules\Site\Support\PreviewLinks;
use Tests\Support\AcademySite;

uses(AcademySite::class);

/*
 * The builder's preview (docs/architecture/site-builder.md, 3.2): a two-hour
 * token from /admin reads the draft through GET v1/site/preview, even while
 * the page is off, with no form token and never indexed. Another academy's
 * token is nothing here; an expired one says the link ended.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy(enabled: false);
});

it('shows the draft while the page is off, never indexed, with no form token', function () {
    $this->actingAs($this->owner);
    $token = $this->inNoor(function () {
        app(SaveSiteDraft::class)->handle(['blocks' => [['type' => 'hero', 'variant' => 'minimal', 'data' => ['title' => ['en' => 'Being built']]]]], SitePage::current()->draft_revision);

        return PreviewLinks::issue();
    });

    $preview = $this->getJson('http://noor.lms.test/api/v1/site/preview?token='.$token, ['Accept-Language' => 'en'])->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    expect($preview->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($preview->json('data.page.blocks.0.data.title'))->toBe('Being built')
        ->and($preview->json('data.preview'))->toBe(['revision' => 2, 'published_revision' => 1, 'enabled' => false])
        ->and($preview->json('data.form_token'))->toBe('')
        ->and($preview->json('data.page.revision'))->toBe(2);
    // The page itself stays off.
    $this->getJson('http://noor.lms.test/api/v1/site')->assertNotFound();
});

it('reads no token, a forged one or another academy\'s as nothing, and an expired one as ended', function () {
    $token = $this->inNoor(fn () => PreviewLinks::issue());
    $this->getJson('http://noor.lms.test/api/v1/site/preview')->assertNotFound();
    $this->getJson('http://noor.lms.test/api/v1/site/preview?token=abc.def')->assertNotFound();

    $other = $this->createAcademy(['slug' => 'huda']);
    $this->actAsTenant($other);
    SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::defaults()]);
    $theirs = PreviewLinks::issue();
    $this->clearTenant();
    $this->getJson('http://noor.lms.test/api/v1/site/preview?token='.$theirs)->assertNotFound();
    $this->getJson('http://huda.lms.test/api/v1/site/preview?token='.$token)->assertNotFound();

    $this->travel(PreviewLinks::HOURS * 60 + 1)->minutes();
    $this->getJson('http://noor.lms.test/api/v1/site/preview?token='.$token)->assertStatus(410)->assertJsonPath('type', fn ($type) => str_contains((string) $type, 'link-expired'));
});
