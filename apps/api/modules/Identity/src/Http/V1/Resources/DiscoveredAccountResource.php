<?php

namespace Modules\Identity\Http\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Platform\Contracts\DTOs\DiscoveredAccount;
use Modules\Platform\Contracts\TenantDirectory;

/** @property DiscoveredAccount $resource */
final class DiscoveredAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $a = $this->resource;
        $directory = app(TenantDirectory::class);
        $tenant = $directory->bySlug($a->academySlug);
        // The academy's own pages, on its own address and in the asked language: whoever adds the account
        // can read what this academy does with their data first.
        $page = fn (string $route): ?string => $tenant === null ? null
            : rtrim($directory->baseUrl($tenant), '/').route($route, ['lang' => app()->getLocale()], false);

        return [
            'academy' => [
                'id' => $a->academyId,
                /** Send as X-Academy to sign in to this account. */
                'slug' => $a->academySlug,
                'name_ar' => $a->academyNameAr,
                'name_en' => $a->academyNameEn,
                'logo_url' => $a->logoUrl,
                'colour' => $a->colour,
                /** The academy's privacy notice, on its own address. */
                'privacy_url' => $page('privacy.notice'),
                /** The academy's terms, on its own address. */
                'terms_url' => $page('privacy.terms'),
            ],
            'user' => [
                'id' => $a->userId,
                'name' => $a->userName,
                'roles' => $a->roles,
            ],
        ];
    }
}
