<?php

namespace Modules\Quran\Actions\Passages;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Models\LessonPassage;
use Modules\Quran\Texts\QuranText;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * Says which passage of the Quran a curriculum lesson reads (surah, verses
 * the platform's copy has, at most VerseRange::VERSES_MAX), or takes it off
 * (null). Only while the academy's plan has the Quran and the platform has
 * its copy; the lesson must be a lesson of the academy's curriculum.
 */
final class SetLessonPassage
{
    public function __construct(
        private readonly QuranText $quran,
        private readonly AcademyPlan $plan,
    ) {}

    public function handle(string $nodeId, ?VerseRange $range): ?LessonPassage
    {
        $this->plan->requireFeature(Feature::Quran);
        $l = 'quran::passages.errors.';
        $fail = fn (string $why, array $replace = []) => throw ValidationException::withMessages(['passage' => __($l.$why, $replace)]);
        if (DB::table('curriculum_nodes')->where('id', $nodeId)->value('kind') !== 'lesson') {
            $fail('lesson_only');
        }
        if ($range === null) {
            LessonPassage::query()->where('node_id', $nodeId)->delete();

            return null;
        }
        if (! $this->quran->available()) {
            $fail('missing');
        }
        $known = $this->quran->surah($range->surah);
        if ($known === null || $range->from < 1 || $range->to < $range->from || $range->to > $known['verses']) {
            $fail('verses', ['max' => $known['verses'] ?? 0]);
        }
        if ($range->to - $range->from + 1 > VerseRange::VERSES_MAX) {
            $fail('too_many', ['max' => VerseRange::VERSES_MAX]);
        }

        return LessonPassage::query()->updateOrCreate(['node_id' => $nodeId], ['surah' => $range->surah, 'from_verse' => $range->from, 'to_verse' => $range->to]);
    }
}
