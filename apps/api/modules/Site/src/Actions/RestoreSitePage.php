<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;

/**
 * The owner or an admin brings an earlier publish back into the draft, to
 * review it and publish it again. It does not go live by itself. Audited
 * with the revision it came from.
 */
final class RestoreSitePage
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** @throws ValidationException */
    public function handle(string $publishId): SitePage
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        $kept = Str::isUuid($publishId) ? SitePagePublish::query()->find($publishId) : null;
        if ($kept === null) {
            throw ValidationException::withMessages(['restore' => __('site::builder.errors.no_publish')]);
        }

        return DB::transaction(function () use ($kept) {
            $page = SitePage::lockedForUpdate();
            $page->forceFill([
                'draft' => SitePage::read($kept->page), 'draft_revision' => $page->draft_revision + 1,
                'draft_saved_at' => now(), 'draft_saved_by' => auth()->id(),
            ])->save();
            $this->audit->record('site.page_restored', 'site_pages', null, ['revision' => $kept->revision]);

            return $page;
        });
    }
}
