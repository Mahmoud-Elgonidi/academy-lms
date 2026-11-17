<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Models\SitePage;

/**
 * The owner or an admin throws the draft away: it becomes the published page
 * again. Refused while nothing has been published. The draft's revision goes
 * one up (an editor still open on the old one is told to reload), and the
 * published revision follows it (the two are the same page again). Audited.
 */
final class DiscardSiteDraft
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** @throws ValidationException */
    public function handle(): SitePage
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () {
            $page = SitePage::lockedForUpdate();
            if ($page->published === null) {
                throw ValidationException::withMessages(['discard' => __('site::builder.errors.nothing_published')]);
            }
            $revision = $page->draft_revision + 1;
            $page->forceFill([
                'draft' => $page->published, 'draft_revision' => $revision, 'published_revision' => $revision,
                'draft_saved_at' => now(), 'draft_saved_by' => auth()->id(),
            ])->save();
            $this->audit->record('site.page_discarded', 'site_pages', null, ['revision' => $revision]);

            return $page;
        });
    }
}
