<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Payroll\Domain\RateLine;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * What the academy pays its teachers, in one currency (docs/architecture/01,
 * section 13: "جدول الأسعار"). The default card covers every teacher who has
 * no card of their own.
 *
 * @property string $id
 * @property string $name
 * @property string $currency
 * @property bool $is_default
 */
final class RateCard extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['name', 'currency', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return HasMany<RateCardLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(RateCardLine::class);
    }

    /** @return HasMany<TeacherRateCard, $this> */
    public function teachers(): HasMany
    {
        return $this->hasMany(TeacherRateCard::class);
    }

    /** @return list<RateLine> */
    public function rateLines(): array
    {
        return $this->lines->map(fn (RateCardLine $l) => $l->toRateLine())->values()->all();
    }

    /** The card a teacher is paid from: their own, or the academy's default. */
    public static function forTeacher(string $teacherId): ?self
    {
        $own = TeacherRateCard::query()->where('teacher_id', $teacherId)->value('rate_card_id');

        return self::query()->with('lines')->when($own, fn ($q) => $q->whereKey($own), fn ($q) => $q->where('is_default', true))->first();
    }
}
