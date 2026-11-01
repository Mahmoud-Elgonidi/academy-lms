<?php

namespace Modules\Quran\Actions\Settings;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Grading\GradeScale;
use Modules\Quran\Domain\Grading\MistakeTypes;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The academy's Quran settings (docs/architecture/02, section 26), the
 * fields given and nothing else: the riwaya and the mushaf (Hafs and the
 * Madinah mushaf of 604 pages, for now), a new program's default amounts,
 * the daily hifz review, the mistake types and their weights, the grade
 * scale and the memorization threshold, the automatic recitation check,
 * exams and who examines, certificates (title, signature, shown to the
 * family). Audited with the names of the fields changed, never their
 * values; the daily review alone keeps its own entry.
 */
final class SaveQuranSettings
{
    public const DAILY_MAX = 20;

    public const RIWAYAT = ['hafs'];

    public const MUSHAFS = ['madinah_604'];

    public const EXAMINERS = ['other_teacher', 'supervisor', 'any_teacher'];

    public function __construct(private readonly AuditTrail $audit) {}

    /** @param  array<string, mixed>  $data  QuranSettings::FIELDS */
    public function handle(array $data): QuranSettings
    {
        $data = array_intersect_key($data, array_flip(QuranSettings::FIELDS));
        $unit = $data['default_unit'] ?? QuranSettings::current()->default_unit;
        [$min, $max] = ProgramRules::AMOUNTS[$unit] ?? [0.25, 5];
        Validator::make($data, [
            'hifz_daily_pages' => ['sometimes', 'required', 'integer', 'between:1,'.self::DAILY_MAX],
            'riwaya' => ['sometimes', 'required', Rule::in(self::RIWAYAT)],
            'mushaf' => ['sometimes', 'required', Rule::in(self::MUSHAFS)],
            'default_unit' => ['sometimes', 'required', Rule::in(ProgramRules::UNITS)],
            'default_new_amount' => ['sometimes', 'required', 'numeric', 'between:'.$min.','.$max],
            'default_near_portions' => ['sometimes', 'required', 'integer', 'between:0,'.ProgramRules::NEAR_MAX],
            'default_far_pages' => ['sometimes', 'required', 'integer', 'between:0,'.ProgramRules::FAR_MAX],
            'memorization_threshold' => ['sometimes', 'required', Rule::in(Grades::PASSING)],
            'auto_recitation_check' => ['sometimes', 'boolean'],
            'exams_enabled' => ['sometimes', 'boolean'],
            'examiner' => ['sometimes', 'required', Rule::in(self::EXAMINERS)],
            'certificates_enabled' => ['sometimes', 'boolean'],
            'certificate_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'certificate_signature' => ['sometimes', 'nullable', 'string', 'max:120'],
            'certificates_family' => ['sometimes', 'boolean'],
        ], [], trans('quran::settings.attributes'))->validate();
        $errors = [];
        if (array_key_exists('mistake_types', $data) && $data['mistake_types'] !== null) {
            $why = MistakeTypes::errors($data['mistake_types']);
            $why === [] ? $data['mistake_types'] = MistakeTypes::normalize($data['mistake_types']) : $errors['mistake_types'] = array_map(fn ($w) => __('quran::settings.errors.'.$w), $why);
        }
        if (array_key_exists('grade_scale', $data) && $data['grade_scale'] !== null) {
            GradeScale::errors($data['grade_scale']) === [] ? $data['grade_scale'] = GradeScale::normalize($data['grade_scale']) : $errors['grade_scale'] = [__('quran::settings.errors.scale')];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        foreach (['certificate_title', 'certificate_signature'] as $text) {
            if (array_key_exists($text, $data)) {
                $data[$text] = trim(strip_tags((string) $data[$text])) ?: null;
            }
        }

        $settings = QuranSettings::current();
        $settings->fill($data);
        $changed = array_keys($settings->getDirty());
        if ($changed === []) {
            return $settings;
        }
        $settings->save();
        if ($changed === ['hifz_daily_pages']) {
            $this->audit->record('quran.daily_pages_saved', 'quran_settings', $settings->academy_id, ['pages' => $settings->hifz_daily_pages]);
        } else {
            $this->audit->record('quran.settings_saved', 'quran_settings', $settings->academy_id, ['quran_fields' => $changed]);
        }

        return $settings;
    }
}
