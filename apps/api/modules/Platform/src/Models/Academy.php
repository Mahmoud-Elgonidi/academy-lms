<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Platform\Database\Factories\AcademyFactory;
use Modules\Platform\Enums\AcademyStatus;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * Platform-side record of an academy. Read and written only through the
 * BYPASSRLS platform connection; other modules see a TenantRef instead.
 *
 * @property string $id
 * @property string $slug
 * @property string $name_ar
 * @property string $name_en
 * @property AcademyStatus $status
 * @property string $default_timezone
 * @property string $default_locale
 * @property string $base_currency
 * @property ?string $country
 * @property string $time_format h12 or h24 (SharedKernel\Time\TimeFormat)
 * @property array<string, mixed> $settings
 */
final class Academy extends Model
{
    /** @use HasFactory<AcademyFactory> */
    use HasFactory;

    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $guarded = [];

    protected $attributes = [
        'settings' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'status' => AcademyStatus::class,
            'settings' => 'array',
        ];
    }

    /** @return HasMany<AcademyDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(AcademyDomain::class);
    }

    public function toTenantRef(): TenantRef
    {
        return new TenantRef(
            id: $this->id,
            slug: $this->slug,
            nameAr: $this->name_ar,
            nameEn: $this->name_en,
            defaultTimezone: $this->default_timezone,
            defaultLocale: $this->default_locale,
            active: $this->status === AcademyStatus::Active,
            timeFormat: (string) ($this->time_format ?? 'h12'),
        );
    }

    protected static function newFactory(): AcademyFactory
    {
        return AcademyFactory::new();
    }
}
