<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Domain\Page\Templates;
use Modules\Site\Models\SitePage;

/**
 * The owner or an admin starts the draft again from a template (online
 * courses, training, children and families, a language school, blank). Only
 * the draft changes: nothing goes live until it is published. Audited with
 * the template's key.
 */
final class ApplySiteTemplate
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** @throws ValidationException */
    public function handle(string $key): SitePage
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        if (! in_array($key, Templates::KEYS, true)) {
            throw ValidationException::withMessages(['template' => __('site::builder.errors.template')]);
        }

        return DB::transaction(function () use ($key) {
            $page = SitePage::lockedForUpdate();
            $audience = (string) ($page->draftDoc()['settings']['audience'] ?? 'families');
            $page->forceFill([
                'draft' => Templates::make($key, $audience), 'template' => $key, 'draft_revision' => $page->draft_revision + 1,
                'draft_saved_at' => now(), 'draft_saved_by' => auth()->id(),
            ])->save();
            $this->audit->record('site.page_template_applied', 'site_pages', null, ['template' => $key]);

            return $page;
        });
    }
}
