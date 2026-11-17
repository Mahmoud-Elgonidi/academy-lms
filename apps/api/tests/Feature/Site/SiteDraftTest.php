<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\Site\Actions\ApplySiteTemplate;
use Modules\Site\Actions\DiscardSiteDraft;
use Modules\Site\Actions\PublishSitePage;
use Modules\Site\Actions\RestoreSitePage;
use Modules\Site\Actions\SaveSiteDraft;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;
use Tests\Support\AcademySite;
use Tests\Support\SitePages;

uses(AcademySite::class, SitePages::class);

/*
 * The draft and its publishing (docs/architecture/site-builder.md, 4.3): only
 * the owner and admins; a draft save checks shape and lengths, makes ids,
 * counts its revision and is refused when someone else saved since; it is
 * not audited. Publishing takes the revision reviewed, refuses blockers,
 * keeps the version for the history and is audited without the words; so
 * are discarding, starting from a template and restoring.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy();
    $this->actAsTenant($this->academy);
    $this->actingAs($this->owner);
});

it('saves the draft for the owner and admins only, counting revisions, refusing a stale one, never audited', function () {
    $page = app(SaveSiteDraft::class)->handle(['blocks' => [['type' => 'hero', 'data' => ['title' => ['ar' => 'مسودة']]], ['type' => 'hero', 'data' => []]]], 1);

    expect($page->exists)->toBeTrue()
        ->and($page->draft_revision)->toBe(2)
        ->and($page->published_revision)->toBe(1)          // what showed before stays published
        ->and($page->unpublished())->toBeTrue()
        ->and($page->draft['blocks'])->toHaveCount(2)       // a draft may break the publish rules
        ->and($page->draft['blocks'][0]['id'])->not->toBe($page->draft['blocks'][1]['id'])
        ->and($page->draft_saved_by)->toBe($this->owner->id)
        ->and(DB::table('audit_logs')->where('action', 'like', 'site.%')->count())->toBe(0);

    // The same draft again changes nothing.
    expect(app(SaveSiteDraft::class)->handle($page->draft, 2)->draft_revision)->toBe(2);

    // Someone else saved since this editor read it.
    expect(fn () => app(SaveSiteDraft::class)->handle(['blocks' => []], 1))->toThrow(ValidationException::class);
    // Too long: the field's own message.
    try {
        app(SaveSiteDraft::class)->handle(['blocks' => [['type' => 'hero', 'data' => ['title' => ['ar' => str_repeat('ب', 121)]]]]], 2);
        $this->fail('saved');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['blocks.0.data.title.ar']);
    }

    foreach ([$this->sales, $this->scheduler] as $someone) {
        $this->actingAs($someone);
        expect(fn () => app(SaveSiteDraft::class)->handle(['blocks' => []], 2))->toThrow(AuthorizationException::class)
            ->and(fn () => app(PublishSitePage::class)->handle(2))->toThrow(AuthorizationException::class);
    }
});

it('publishes the reviewed revision only, refuses blockers, keeps it in the history, and audits it without the words', function () {
    $page = app(SaveSiteDraft::class)->handle(['blocks' => [['type' => 'hero', 'data' => ['title' => ['ar' => '', 'en' => '']]], ['type' => 'form']]], 1);

    expect(fn () => app(PublishSitePage::class)->handle($page->draft_revision))->toThrow(ValidationException::class)
        ->and(SitePagePublish::query()->count())->toBe(0);

    $page = app(SaveSiteDraft::class)->handle(['blocks' => [['type' => 'hero', 'data' => ['title' => ['ar' => 'دورات الإنجليزية']]], ['type' => 'faq', 'hidden' => true], ['type' => 'form']]], $page->draft_revision);
    expect(fn () => app(PublishSitePage::class)->handle($page->draft_revision - 1))->toThrow(ValidationException::class);

    $published = app(PublishSitePage::class)->handle($page->draft_revision);
    expect($published->published)->toBe($published->draft)
        ->and($published->published_revision)->toBe($published->draft_revision)
        ->and($published->published_by)->toBe($this->owner->id)
        ->and($published->unpublished())->toBeFalse()
        ->and(SitePagePublish::query()->sole()->revision)->toBe($published->draft_revision);

    $audit = DB::table('audit_logs')->where('action', 'site.page_published')->sole();
    expect(json_decode($audit->data, true))->toEqual(['revision' => 3, 'blocks' => 3, 'types' => ['hero', 'form']])
        ->and($audit->data)->not->toContain('الإنجليزية')
        ->and(AuditEntries::describe($audit, 'UTC')['title'])->toContain('3');
});

it('discards the draft back to the published page, starts from a template, and restores a version, each audited', function () {
    $this->publishPage([['type' => 'hero', 'data' => ['title' => ['ar' => 'أولى']]], ['type' => 'form']]);
    $first = SitePagePublish::query()->sole();
    $this->publishPage([['type' => 'hero', 'data' => ['title' => ['ar' => 'ثانية']]], ['type' => 'form']]);
    app(SaveSiteDraft::class)->handle(['blocks' => [['type' => 'stats']]], SitePage::current()->draft_revision);

    $page = app(DiscardSiteDraft::class)->handle();
    expect($page->draft)->toBe($page->published)
        ->and($page->unpublished())->toBeFalse()
        ->and($page->draft['blocks'][0]['data']['title']['ar'])->toBe('ثانية');

    $page = app(ApplySiteTemplate::class)->handle('online_courses');
    expect($page->template)->toBe('online_courses')
        ->and($page->draft['blocks'][0]['variant'])->toBe('split')
        ->and($page->published['blocks'][0]['data']['title']['ar'])->toBe('ثانية')   // nothing live yet
        ->and(fn () => app(ApplySiteTemplate::class)->handle('nope'))->toThrow(ValidationException::class);

    $page = app(RestoreSitePage::class)->handle($first->id);
    expect($page->draft['blocks'][0]['data']['title']['ar'])->toBe('أولى')
        ->and($page->published['blocks'][0]['data']['title']['ar'])->toBe('ثانية')
        ->and(fn () => app(RestoreSitePage::class)->handle('not-an-id'))->toThrow(ValidationException::class);

    expect(DB::table('audit_logs')->orderBy('created_at')->pluck('action')->all())
        ->toContain('site.page_discarded', 'site.page_template_applied', 'site.page_restored')
        ->and(json_decode(DB::table('audit_logs')->where('action', 'site.page_template_applied')->value('data'), true))->toBe(['template' => 'online_courses'])
        ->and(json_decode(DB::table('audit_logs')->where('action', 'site.page_restored')->value('data'), true))->toBe(['revision' => $first->revision]);

    // Discarding with nothing published is refused.
    SitePage::query()->update(['published' => null, 'published_revision' => null]);
    expect(fn () => app(DiscardSiteDraft::class)->handle())->toThrow(ValidationException::class);
});

it('never changes a kept version', function () {
    $this->publishPage([['type' => 'hero', 'data' => ['title' => ['ar' => 'x']]], ['type' => 'form']]);
    $kept = SitePagePublish::query()->sole();

    expect(fn () => $kept->forceFill(['revision' => 99])->save())->toThrow(LogicException::class);
});
