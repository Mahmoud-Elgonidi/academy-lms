<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Domain\Page\InvalidPage;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Models\SitePage;
use Modules\Site\Support\PageWords;

/**
 * The owner or an admin saves the page's draft (the builder saves as they
 * edit; site-builder.md, 4.3): its shape and lengths only (Domain\Page\PageDoc;
 * whether it may go live is PublishSitePage's), new ids for new or copied
 * blocks, the revision one up. Refused when someone else saved since this
 * editor read it (the revision it read is not the latest). Nothing changes
 * on the page until it is published. Not audited (publishing is).
 */
final class SaveSiteDraft
{
    public function __construct(private readonly BrandingApi $branding) {}

    /**
     * @param  array<string, mixed>  $doc  a PageDoc: settings and blocks
     * @param  int  $baseRevision  the draft revision the editor started from
     * @param  string  $errorPrefix  where the fields' errors go (the builder's form: "data.")
     *
     * @throws ValidationException
     */
    public function handle(array $doc, int $baseRevision, string $errorPrefix = ''): SitePage
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        try {
            $clean = PageDoc::normalize($doc, $this->branding->fontFamilies());
        } catch (InvalidPage $e) {
            throw PageWords::invalid($e, $errorPrefix);
        }

        return DB::transaction(function () use ($clean, $baseRevision) {
            $page = SitePage::lockedForUpdate();
            if ($baseRevision !== $page->draft_revision) {
                throw ValidationException::withMessages(['revision' => __('site::builder.errors.conflict')]);
            }
            if ($page->exists && $page->draftDoc() === $clean) {
                return $page;
            }
            $page->forceFill([
                'draft' => $clean,
                'draft_revision' => $page->draft_revision + 1,
                'draft_saved_at' => now(),
                'draft_saved_by' => auth()->id(),
            ])->save();

            return $page;
        });
    }
}
