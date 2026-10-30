<?php

namespace Modules\Identity\Http\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\TimeFormat;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerClock;
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
            // Whether the person may change their email and mobile themselves (me/contact): not a child the academy runs under a guardian, never while the platform team is signed in as them.
            'can_change_contact' => ! $this->is_managed_minor && AuditActor::current()->onBehalfOf === null,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            // The zone this response's times are written in (device, else profile, else academy).
            'display_timezone' => $viewer->effective(),
            // display_timezone as people read it, in the reader's language: "القاهرة (UTC+2)".
            'timezone_label' => Timezones::label($viewer->effective()),
            // The device's clock differs from the profile's: the app offers to update the profile.
            'timezone_mismatch' => $viewer->mismatch(),
            // How this person reads a clock: h12 (5:00 PM) or h24 (17:00), their own choice else the academy's.
            'time_format' => (TimeFormat::of(data_get($this->resource, 'time_format')) ?? app(ViewerClock::class)->academy())->value,
            // Their own choice (h12, h24), or null when they follow the academy's.
            'time_format_choice' => TimeFormat::of(data_get($this->resource, 'time_format'))?->value,
            'roles' => array_map(fn (Role $r) => $r->value, $this->roles()),
            // The areas the academy's plan includes (crm, online_payments, import, custom_branding, payroll): the apps show only these.
            'plan_features' => $academy === null ? [] : $this->planFeatures(),
            'academy' => $academy === null ? null : [
                'id' => $academy->id,
                'slug' => $academy->slug,
                'name_ar' => $academy->nameAr,
                'name_en' => $academy->nameEn,
                'default_timezone' => $academy->defaultTimezone,
                // default_timezone as people read it, in the reader's language: "الرياض (UTC+3)".
                'timezone_label' => Timezones::label($academy->defaultTimezone),
                // The academy's clock for everyone who has not chosen their own: h12 or h24.
                'time_format' => (TimeFormat::of($academy->timeFormat) ?? TimeFormat::DEFAULT)->value,
            ],
        ];
    }

    /** @return list<string> */
    private function planFeatures(): array
    {
        $plan = app(AcademyPlan::class);
        $features = [];
        foreach (Feature::cases() as $feature) {
            if ($plan->has($feature)) {
                $features[] = $feature->value;
            }
        }

        return $features;
    }
}
