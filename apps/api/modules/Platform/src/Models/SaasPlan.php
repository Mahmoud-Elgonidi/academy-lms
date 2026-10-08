<?php

namespace Modules\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Plans\Limit;

/**
 * A SaaS plan the platform sells: its limits (null: none) and the areas it
 * includes. Platform connection only.
 *
 * @property string $id
 * @property string $code
 * @property string $name_ar
 * @property string $name_en
 * @property ?int $max_teachers
 * @property ?int $max_active_students
 * @property ?int $video_minutes
 * @property ?int $storage_mb
 * @property list<string> $features
 * @property ?int $price_minor
 * @property ?string $currency
 * @property bool $is_default
 * @property int $sort
 * @property ?CarbonImmutable $archived_at
 */
final class SaasPlan extends Model
{
    use HasUuids;

    /** Each limit's column. */
    public const COLUMNS = [
        'teachers' => 'max_teachers',
        'active_students' => 'max_active_students',
        'video_minutes' => 'video_minutes',
        'storage_mb' => 'storage_mb',
    ];

    protected $connection = 'pgsql_platform';

    protected $table = 'saas_plans';

    protected $guarded = [];

    protected $attributes = ['features' => '[]'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_default' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function limit(Limit $limit): ?int
    {
        $value = $this->getAttribute(self::COLUMNS[$limit->value]);

        return $value === null ? null : (int) $value;
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }

    /** @param  Builder<self>  $query */
    public function scopeOffered(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public static function default(): ?self
    {
        return self::query()->offered()->where('is_default', true)->first();
    }
}
