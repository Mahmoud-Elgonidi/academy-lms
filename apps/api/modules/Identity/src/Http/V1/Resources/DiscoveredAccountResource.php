<?php

namespace Modules\Identity\Http\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Platform\Contracts\DTOs\DiscoveredAccount;

/** @property DiscoveredAccount $resource */
final class DiscoveredAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $a = $this->resource;

        return [
            'academy' => [
                'id' => $a->academyId,
                /** Send as X-Academy to sign in to this account. */
                'slug' => $a->academySlug,
                'name_ar' => $a->academyNameAr,
                'name_en' => $a->academyNameEn,
                'logo_url' => $a->logoUrl,
                'colour' => $a->colour,
            ],
            'user' => [
                'id' => $a->userId,
                'name' => $a->userName,
                /** @var list<string> */
                'roles' => $a->roles,
            ],
        ];
    }
}
