<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Site\Filament\Pages\SiteBuilder;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;
use Tests\Support\AcademySite;
use Tests\Support\SitePages;

uses(AcademySite::class, SitePages::class);

/*
 * The page builder's screen (/admin/site/builder; docs/architecture/site-builder.md,
 * 4): the owner and admins only; it opens on the academy's page (its old one
 * as blocks at first); adding, copying, hiding and moving blocks saves the
 * draft; the words and the audience reach GET v1/site once published; the
 * preview opens the portal with a token; templates and the history work
 * from its menu.
 */

beforeEach(function () {
    gc_collect_cycles();
    $this->withoutDefer();
    Mail::fake();
    Storage::fake('public');
    $this->siteAcademy();
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('academy');
});

/** The screen's block keys in order, and each block's type. */
function builderTypes($page): array
{
    return array_values(array_map(fn ($item) => $item['type'], $page->get('data.blocks')));
}

it('opens for the owner and admins only, on the academy\'s page as it is', function () {
    foreach ([$this->sales, $this->scheduler, $this->accountant] as $someone) {
        $this->actingAs($someone);
        expect(SiteBuilder::canAccess())->toBeFalse();
    }
    $this->actingAs($this->sales);
    $this->get('http://noor.lms.test/admin/site/builder')->assertForbidden();
    $this->actAsTenant($this->academy);   // the request leaves the academy behind

    $this->actingAs($this->admin);
    $page = Livewire::test(SiteBuilder::class)->assertOk()
        ->assertSee(__('site::builder.title', [], 'en'))
        ->assertSee(__('site::builder.types.hero.name', [], 'en'));
    expect(builderTypes($page))->toBe(['hero', 'features', 'subjects', 'teachers', 'steps', 'app', 'pricing', 'testimonials', 'faq', 'form'])
        ->and($page->get('revision'))->toBe(1)
        ->and($page->get('previewToken'))->not->toBe('');
});

it('saves the draft as blocks are added, copied, hidden and moved, and publishes it for the page', function () {
    $this->actingAs($this->owner);
    $screen = Livewire::test(SiteBuilder::class);
    $blocks = $screen->get('data.blocks');
    $keys = array_keys($blocks);

    // Edit the opening's words, hide the questions, move the form first, and add a numbers block.
    $blocks[$keys[0]]['data']['content']['title'] = ['ar' => 'دورات للكبار', 'en' => 'Courses for adults'];
    $blocks[$keys[8]]['data']['hidden'] = true;
    $form = $blocks[$keys[9]];
    unset($blocks[$keys[9]]);
    $blocks = [$keys[9] => $form] + $blocks;
    $blocks['new-stats'] = ['type' => 'stats', 'data' => ['variant' => 'band', 'hidden' => false, 'nav' => ['ar' => '', 'en' => ''], 'style' => ['tone' => 'tint'], 'content' => [
        'items' => [['value' => ['ar' => '١٢', 'en' => '12'], 'label' => ['ar' => 'عامًا', 'en' => 'years']], ['value' => ['ar' => '٣', 'en' => '3'], 'label' => ['ar' => 'لغات', 'en' => 'languages']]],
    ]]];
    // A copy of the opening: a new id.
    $blocks['copy'] = $blocks[$keys[0]];
    $blocks['copy']['data']['hidden'] = true;

    $screen->set(['data.blocks' => $blocks, 'settings.audience' => 'adults'])->assertHasNoErrors();

    $draft = SitePage::current()->draftDoc();
    $types = array_column($draft['blocks'], 'type');
    expect(SitePage::current()->draft_revision)->toBeGreaterThanOrEqual(2)
        ->and($screen->get('revision'))->toBe(SitePage::current()->draft_revision)
        ->and($types[0])->toBe('form')
        ->and(array_slice($types, -2))->toBe(['stats', 'hero'])
        ->and($draft['blocks'][1]['data']['title']['en'])->toBe('Courses for adults')
        ->and($draft['blocks'][count($types) - 1]['id'])->not->toBe($draft['blocks'][1]['id'])
        ->and($draft['blocks'][count($types) - 1]['hidden'])->toBeTrue()
        ->and($draft['settings']['audience'])->toBe('adults');

    unset($screen);
    // Publish from the screen: the page and the old content follow.
    Livewire::test(SiteBuilder::class)->callAction('publish')->assertNotified(__('site::builder.publish.done', [], 'en'));
    expect(SitePagePublish::query()->count())->toBe(1);
    $this->clearTenant();
    $site = $this->getJson('http://noor.lms.test/api/v1/site', ['Accept-Language' => 'en'])->assertOk()->json('data');
    expect($site['page']['settings']['audience'])->toBe('adults')
        ->and($site['content']['audience'])->toBe('adults')
        ->and($site['content']['hero']['title'])->toBe('Courses for adults')
        ->and($site['content']['sections'])->not->toContain('faq')
        ->and(array_column($site['page']['blocks'], 'type'))->toContain('stats')->not->toContain('faq');
});

it('shows a word too long under its field, and saves nothing', function () {
    $this->actingAs($this->owner);
    $screen = Livewire::test(SiteBuilder::class);
    $first = array_key_first($screen->get('data.blocks'));

    $screen->set("data.blocks.{$first}.data.content.title.ar", str_repeat('ب', 130))->call('saveDraft')
        ->assertHasErrors(["data.blocks.{$first}.data.content.title.ar"]);
    expect(SitePage::query()->exists())->toBeFalse();

    $screen->set("data.blocks.{$first}.data.content.title.ar", 'دورات مباشرة للكبار')->call('saveDraft')->assertHasNoErrors();
    expect(SitePage::current()->draftDoc()['blocks'][0]['data']['title']['ar'])->toBe('دورات مباشرة للكبار')
        ->and($screen->get('revision'))->toBe(SitePage::current()->draft_revision)->toBeGreaterThan(1);
});

it('hides and shows a block from its own button, and starts again from a template', function () {
    $this->actingAs($this->owner);
    $screen = Livewire::test(SiteBuilder::class);
    $first = array_key_first($screen->get('data.blocks'));

    $screen->callAction(TestAction::make('toggleHidden')->schemaComponent('blocks', schema: 'blocksForm')->arguments(['item' => $first]));
    expect(SitePage::current()->draftDoc()['blocks'][0]['hidden'])->toBeTrue();

    $screen->callAction('templates', ['template' => 'online_courses'])->assertNotified(__('site::builder.templates.applied', [], 'en'));
    expect(SitePage::current()->template)->toBe('online_courses')
        ->and(builderTypes($screen)[0])->toBe('hero')
        ->and(SitePage::current()->draftDoc()['blocks'][0]['variant'])->toBe('split');
});

it('opens the preview on the portal with a token, at SITE_PREVIEW_URL when it is set', function () {
    config(['site.preview_url' => 'http://localhost:3101']);
    $this->actingAs($this->owner);

    $screen = Livewire::test(SiteBuilder::class)->assertSee('localhost:3101', false);
    $token = $screen->get('previewToken');
    $this->clearTenant();

    $this->getJson('http://noor.lms.test/api/v1/site/preview?token='.$token)->assertOk();
});
