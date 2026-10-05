<?php

namespace Modules\Identity\Http\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerTimezone;

/** @mixin User */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $academy = app(TenantContext::class)->current();
        $viewer = app(ViewerTimezone::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone_e164,
            'username' => $this->username,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            // The zone this response's times are written in (device, else profile, else academy).
            'display_timezone' => $viewer->effective(),
            // The device's clock differs from the profile's: the app offers to update the profile.
            'timezone_mismatch' => $viewer->mismatch(),
            'roles' => array_map(fn (Role $r) => $r->value, $this->roles()),
            'academy' => $academy === null ? null : [
                'id' => $academy->id,
                'slug' => $academy->slug,
                'name_ar' => $academy->nameAr,
                'name_en' => $academy->nameEn,
                'default_timezone' => $academy->defaultTimezone,
            ],
        ];
    }
}
