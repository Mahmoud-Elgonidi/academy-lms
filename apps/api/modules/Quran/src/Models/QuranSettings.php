<?php

namespace Modules\Quran\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Quran\Domain\Grading\GradeScale;
use Modules\Quran\Domain\Grading\MistakeTypes;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The current academy's Quran settings; defaults when it has no row. Saved
 * only through Actions\Settings\SaveQuranSettings.
 *
 * @property int $hifz_daily_pages pages a day at most in a learner's daily hifz review
 * @property string $riwaya hafs (the only one for now)
 * @property string $mushaf madinah_604
 * @property string $default_unit a new program's unit
 * @property float $default_new_amount
 * @property int $default_near_portions
 * @property int $default_far_pages
 * @property ?list<array{key: string, weight: float, name_ar?: string, name_en?: string}> $mistake_types null: the defaults
 * @property ?array<string, float> $grade_scale null: the default
 * @property string $memorization_threshold the grade a new portion needs when its program names none
 * @property bool $auto_recitation_check recited homework is checked (while the AI helper is on)
 * @property bool $exams_enabled
 * @property string $examiner other_teacher | supervisor | any_teacher
 * @property bool $certificates_enabled
 * @property ?string $certificate_title
 * @property ?string $certificate_signature
 * @property bool $certificates_family whether families see the certificates
 */
final class QuranSettings extends Model
{
    use BelongsToTenant;

    public const FIELDS = [
        'hifz_daily_pages', 'riwaya', 'mushaf', 'default_unit', 'default_new_amount', 'default_near_portions', 'default_far_pages',
        'mistake_types', 'grade_scale', 'memorization_threshold', 'auto_recitation_check', 'exams_enabled', 'examiner',
        'certificates_enabled', 'certificate_title', 'certificate_signature', 'certificates_family',
    ];

    public $incrementing = false;

    protected $table = 'quran_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = self::FIELDS;

    protected $attributes = [
        'hifz_daily_pages' => 3, 'riwaya' => 'hafs', 'mushaf' => 'madinah_604', 'default_unit' => 'page', 'default_new_amount' => 0.5,
        'default_near_portions' => 3, 'default_far_pages' => 2, 'memorization_threshold' => 'good', 'auto_recitation_check' => true,
        'exams_enabled' => false, 'examiner' => 'other_teacher', 'certificates_enabled' => false, 'certificates_family' => true,
    ];

    protected function casts(): array
    {
        return [
            'hifz_daily_pages' => 'integer', 'default_new_amount' => 'float', 'default_near_portions' => 'integer', 'default_far_pages' => 'integer',
            'mistake_types' => 'array', 'grade_scale' => 'array', 'auto_recitation_check' => 'boolean', 'exams_enabled' => 'boolean',
            'certificates_enabled' => 'boolean', 'certificates_family' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    /** @return list<array{key: string, weight: float, name_ar?: string, name_en?: string}> */
    public function mistakeTypes(): array
    {
        return $this->mistake_types ?? MistakeTypes::DEFAULTS;
    }

    /** @return array<string, float> */
    public function weights(): array
    {
        return MistakeTypes::weights($this->mistakeTypes());
    }

    /** @return list<string> */
    public function mistakeKinds(): array
    {
        return array_column($this->mistakeTypes(), 'key');
    }

    /** A kind's name in the reader's language. */
    public function mistakeName(string $key): string
    {
        $type = collect($this->mistakeTypes())->firstWhere('key', $key);
        if ($type !== null && isset($type['name_ar'], $type['name_en'])) {
            return app()->getLocale() === 'ar' ? $type['name_ar'] : $type['name_en'];
        }

        return trans()->has('quran::mushaf.kinds.'.$key) ? __('quran::mushaf.kinds.'.$key) : $key;
    }

    /** @return array<string, float> */
    public function gradeScale(): array
    {
        return $this->grade_scale ?? GradeScale::DEFAULT;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
