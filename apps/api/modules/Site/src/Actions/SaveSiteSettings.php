<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;

/**
 * The owner or an admin saves the academy's page: on or off, and what it
 * says (Domain\SiteContent, cleaned and held to its lengths). It shows at
 * once. Audited with whether it is on and the sections shown, never the words.
 */
final class SaveSiteSettings
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  array<string, mixed>  $data  enabled, and the content: hero, features, testimonials, faqs, sections, whatsapp, seo
     *
     * @throws ValidationException
     */
    public function handle(array $data): SiteSettings
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        $content = SiteContent::normalize($data);
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOL);

        return DB::transaction(function () use ($content, $enabled) {
            $row = SiteSettings::query()->lockForUpdate()->first() ?? new SiteSettings;
            $row->fill(['enabled' => $enabled, 'content' => $content, 'updated_by' => auth()->id()])->save();
            $this->audit->record('site.settings_saved', 'site_settings', null, ['state' => $enabled ? 'on' : 'off', 'shown' => $content['sections']]);

            return $row;
        });
    }
}
