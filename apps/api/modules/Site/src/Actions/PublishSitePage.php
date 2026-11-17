<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Domain\Page\Facts;
use Modules\Site\Domain\Page\PublishRules;
use Modules\Site\Domain\Page\Verdict;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;
use Modules\Site\Support\PageWords;

/**
 * The owner or an admin makes the draft the page (site-builder.md, 4.3):
 * only the revision they reviewed (another save since: review it again), and
 * only when PublishRules finds no blocker. The publish is kept for the
 * history (the last 20), and audited with the revision, how many blocks and
 * which types, never the words.
 */
final class PublishSitePage
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** What the publish rules say of the current draft (the builder's status bar reads the same). */
    public static function verdict(?SitePage $page = null): Verdict
    {
        $page ??= SitePage::current();

        return PublishRules::check($page->draftDoc(), self::facts());
    }

    public static function facts(): Facts
    {
        $media = [];
        foreach (SiteMedia::query()->get(['id', 'kind', 'mime', 'width', 'height']) as $row) {
            $media[$row->id] = ['kind' => $row->kind, 'mime' => $row->mime, 'width' => $row->width, 'height' => $row->height];
        }

        return new Facts($media);
    }

    /** @throws ValidationException */
    public function handle(int $revision): SitePage
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($revision) {
            $page = SitePage::lockedForUpdate();
            if ($revision !== $page->draft_revision) {
                throw ValidationException::withMessages(['revision' => __('site::builder.errors.stale')]);
            }
            $doc = $page->draftDoc();
            $verdict = PublishRules::check($doc, self::facts());
            if (! $verdict->ok()) {
                $messages = [];
                foreach ($verdict->blockers as $i => $item) {
                    $messages["publish.{$i}"] = PageWords::rule($item);
                }
                throw ValidationException::withMessages($messages);
            }
            $now = now();
            $page->forceFill([
                'draft' => $doc,
                'published' => $doc,
                'published_revision' => $page->draft_revision,
                'published_at' => $now,
                'published_by' => auth()->id(),
            ])->save();
            SitePagePublish::query()->create(['revision' => $page->draft_revision, 'page' => $doc, 'published_by' => auth()->id(), 'published_at' => $now]);

            $types = [];
            foreach ($doc['blocks'] as $block) {
                if (! $block['hidden'] && ! in_array($block['type'], $types, true)) {
                    $types[] = $block['type'];
                }
            }
            $this->audit->record('site.page_published', 'site_pages', null, ['revision' => $page->draft_revision, 'blocks' => count($doc['blocks']), 'types' => $types]);

            return $page;
        });
    }
}
