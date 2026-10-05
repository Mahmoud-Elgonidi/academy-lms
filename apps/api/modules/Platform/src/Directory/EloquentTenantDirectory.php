<?php

namespace Modules\Platform\Directory;

use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\AcademyDomain;
use Modules\SharedKernel\Tenancy\TenantRef;

final class EloquentTenantDirectory implements TenantDirectory
{
    public function byId(string $id): ?TenantRef
    {
        if (! self::isUuid($id)) {
            return null;
        }

        return Academy::query()->find($id)?->toTenantRef();
    }

    public function bySlug(string $slug): ?TenantRef
    {
        return Academy::query()->where('slug', $slug)->first()?->toTenantRef();
    }

    public function byHost(string $host): ?TenantRef
    {
        $host = strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
        $platformDomain = strtolower(config('platform.domain'));

        if ($host === '' || $host === $platformDomain) {
            return null;
        }

        $suffix = '.'.$platformDomain;
        if (str_ends_with($host, $suffix)) {
            $slug = substr($host, 0, -strlen($suffix));

            return str_contains($slug, '.') ? null : $this->bySlug($slug);
        }

        return AcademyDomain::query()
            ->where('host', $host)
            ->whereNotNull('verified_at')
            ->first()
            ?->academy
            ?->toTenantRef();
    }

    public function active(): array
    {
        return Academy::query()
            ->where('status', AcademyStatus::Active->value)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Academy $academy) => $academy->toTenantRef())
            ->all();
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }
}
