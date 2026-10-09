<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $academy_id
 * @property string $name_ar
 * @property string $name_en
 */
final class Subject extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['name_ar', 'name_en'];

    /** @return HasMany<SubjectLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(SubjectLevel::class)->orderBy('ordinal');
    }

    /** @return HasMany<SubjectCategory, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(SubjectCategory::class)->orderBy('ordinal');
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
