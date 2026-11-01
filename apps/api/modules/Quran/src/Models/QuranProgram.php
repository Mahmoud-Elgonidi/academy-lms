<?php

namespace Modules\Quran\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Quran\Domain\Programs\ProgramShape;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One of the academy's Quran programs (docs/architecture/02, section 26):
 * its own curriculum of the Quran, from which each lesson's portion is made
 * (Domain\Programs). Saved only through Actions\Programs\SaveQuranProgram.
 *
 * @property string $id
 * @property string $name
 * @property string $track hifz | tilawa | talqin | noorani | review | ijaza
 * @property ?string $preset
 * @property ?int $scope_from_surah
 * @property int $scope_from_verse
 * @property ?int $scope_to_surah
 * @property int $scope_to_verse
 * @property string $direction forward | backward
 * @property string $unit verses | lines | page | quarter_hizb | lesson
 * @property float $new_amount
 * @property int $near_portions
 * @property int $far_pages
 * @property string $pass_grade
 * @property string $exam_stops none | juz | surah
 * @property bool $counts_for_certificate
 * @property ?CarbonImmutable $archived_at
 */
final class QuranProgram extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'name', 'track', 'preset', 'scope_from_surah', 'scope_from_verse', 'scope_to_surah', 'scope_to_verse', 'direction', 'unit',
        'new_amount', 'near_portions', 'far_pages', 'pass_grade', 'exam_stops', 'counts_for_certificate', 'archived_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_from_surah' => 'integer', 'scope_from_verse' => 'integer', 'scope_to_surah' => 'integer', 'scope_to_verse' => 'integer',
            'new_amount' => 'float', 'near_portions' => 'integer', 'far_pages' => 'integer', 'counts_for_certificate' => 'boolean',
            'archived_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    public function shape(): ProgramShape
    {
        return ProgramShape::fromArray($this->attributesToArray());
    }
}
