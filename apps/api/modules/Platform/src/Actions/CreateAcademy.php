<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Enums\DomainKind;
use Modules\Platform\Models\Academy;
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

            return $academy->toTenantRef();
        });
    }
}
