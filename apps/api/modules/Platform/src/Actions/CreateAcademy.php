<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Enums\DomainKind;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Tenancy\TenantRef;

final class CreateAcademy
{
    public function handle(NewAcademy $data): TenantRef
    {
        Validator::make([
            'slug' => $data->slug,
            'base_currency' => $data->baseCurrency,
            'default_timezone' => $data->defaultTimezone,
            'default_locale' => $data->defaultLocale,
            'country' => $data->country,
            'custom_domain' => $data->customDomain,
            'plan' => $data->plan,
        ], [
            'slug' => [
                'required', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/',
                Rule::notIn(config('platform.reserved_slugs')),
                Rule::unique('pgsql_platform.academies', 'slug'),
            ],
            'base_currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'default_timezone' => ['required', 'timezone:all'],
            'default_locale' => ['required', Rule::in(['ar', 'en'])],
            'country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'custom_domain' => ['nullable', 'regex:/^(?=.{4,253}$)([a-z0-9-]+\.)+[a-z]{2,}$/', Rule::unique('pgsql_platform.academy_domains', 'host')],
            'plan' => ['nullable', Rule::exists('pgsql_platform.saas_plans', 'code')->whereNull('archived_at')],
        ])->validate();

        return DB::connection('pgsql_platform')->transaction(function () use ($data): TenantRef {
            $academy = Academy::query()->create([
                'slug' => $data->slug,
                'name_ar' => $data->nameAr,
                'name_en' => $data->nameEn,
                'status' => AcademyStatus::Active,
                'base_currency' => $data->baseCurrency,
                'default_timezone' => $data->defaultTimezone,
                'default_locale' => $data->defaultLocale,
                'country' => $data->country,
            ]);

            $academy->domains()->create([
                'host' => $data->slug.'.'.config('platform.domain'),
                'kind' => DomainKind::Subdomain,
                'verified_at' => now(),
            ]);

            // A custom domain stays unverified until DNS is confirmed (P3).
            if ($data->customDomain !== null) {
                $academy->domains()->create([
                    'host' => $data->customDomain,
                    'kind' => DomainKind::Custom,
                ]);
            }

            // It starts on the plan chosen, or the one new academies start on.
            $plan = $data->plan !== null ? SaasPlan::query()->where('code', $data->plan)->first() : SaasPlan::default();
            if ($plan !== null) {
                $actor = AuditActor::current();
                SaasSubscription::query()->create([
                    'academy_id' => $academy->id, 'plan_id' => $plan->id, 'reason' => 'New academy',
                    'changed_by' => $actor->type === 'platform' ? $actor->id : null, 'started_at' => now(),
                ]);
            }

            return $academy->toTenantRef();
        });
    }
}
