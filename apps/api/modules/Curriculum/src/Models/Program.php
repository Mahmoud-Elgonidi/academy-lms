<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $subject_id
 * @property string $name_ar
 * @property string $name_en
 * @property ?string $description_ar
 * @property ?string $description_en
 * @property string $status active | archived
 */
final class Program extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['subject_id', 'name_ar', 'name_en', 'description_ar', 'description_en', 'status'];

    protected $attributes = ['status' => 'active'];

    /** @return HasMany<CurriculumVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class)->orderByDesc('version');
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    /** What the program is, in the current language, the other's where it is empty; null when it has none. */
    public function description(): ?string
    {
        [$mine, $other] = app()->getLocale() === 'ar' ? [$this->description_ar, $this->description_en] : [$this->description_en, $this->description_ar];
        $text = trim((string) ($mine ?: $other));

        return $text === '' ? null : $text;
    }

    /** The version being taught: the published one, else the draft being built. */
    public function workingVersion(): CurriculumVersion
    {
        return $this->versions()->where('status', 'published')->first()
            ?? $this->versions()->where('status', 'draft')->first()
            ?? $this->versions()->create(['version' => 1, 'status' => 'draft']);
    }
}
