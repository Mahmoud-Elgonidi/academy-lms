<?php

namespace Modules\Platform\Actions\Site;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Site\PlatformSite;
use Modules\Platform\Site\SiteContent;

/**
 * An owner saves what the platform's page says (Site\SiteContent); it shows
 * on the page at once. Audited with the sections shown, never the words.
 */
final class SaveSiteContent
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed> the content kept
     */
    public function handle(PlatformAdmin $admin, array $content): array
    {
        if (! $admin->is_owner || ! $admin->is_active) {
            throw new AuthorizationException;
        }
        $clean = SiteContent::normalize($content);

        DB::connection('pgsql_platform')->transaction(function () use ($admin, $clean) {
            PlatformSite::query()->updateOrCreate(['id' => 1], ['content' => $clean, 'updated_by' => $admin->id, 'updated_at' => now()]);
            $this->audit->record(null, 'platform.site_saved', 'platform_site', '1', ['shown' => $clean['sections']]);
        });

        return $clean;
    }
}
