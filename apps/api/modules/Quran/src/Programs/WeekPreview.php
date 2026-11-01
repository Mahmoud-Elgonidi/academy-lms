<?php

namespace Modules\Quran\Programs;

use Modules\Quran\Domain\Programs\LessonPortion;
use Modules\Quran\Domain\Programs\MushafLayout;
use Modules\Quran\Domain\Programs\Presets;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Domain\Programs\ProgramShape;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;

/**
 * What a program's first lessons would hold, for the program builder's
 * "a typical week" (docs/architecture/02, section 26): a learner starting
 * at the program's beginning (or already knowing some pages, which the far
 * review then cycles over), each new part passed, as LessonPortion makes
 * them from the platform's copy; and about how many lessons the whole scope
 * takes. Nothing is kept.
 */
final class WeekPreview
{
    public const MAX_LESSONS = 7;

    public function __construct(
        private readonly Mushaf $mushaf,
        private readonly ProgramPresenter $present,
    ) {}

    /**
     * @param  array<string, mixed>  $program  ProgramRules' fields
     * @param  list<int>  $known  pages the learner already knows
     * @return array{errors: array<string, string>, lessons: list<array<string, mixed>>, total: ?int}
     */
    public function of(array $program, int $lessons, array $known = []): array
    {
        $errors = ProgramRules::errors($program + ['name' => 'preview'], $this->mushaf->surahVerses(), Presets::NOORANI_LESSONS);
        $layout = $this->mushaf->layout();
        $noorani = ($program['track'] ?? null) === 'noorani';
        if ($errors === [] && ! $noorani && $layout === null) {
            $errors['scope_from_surah'] = 'no_text';
        }
        if ($errors === [] && ($program['unit'] ?? null) === 'quarter_hizb' && ! $layout?->hasQuarters()) {
            $errors['unit'] = 'quarters';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'lessons' => [], 'total' => null];
        }
        $shape = ProgramShape::fromArray(ProgramRules::normalize($program + ['name' => 'preview']));
        $layout ??= new MushafLayout([1 => 1], [1], [1]);
        $memorized = $shape->memorizes() || $shape->track === 'review' || $shape->track === 'tilawa' ? $known : [];
        $at = LessonPortion::start($shape, $layout, $memorized);
        $far = null;
        $out = [];
        for ($n = 1; $n <= max(1, min(self::MAX_LESSONS, $lessons)); $n++) {
            if ($at === null && $shape->hasNew()) {
                break;
            }
            $portion = LessonPortion::make($shape, $at, $layout, $memorized, $far);
            $out[] = ['number' => $n] + $this->present->portion($portion) + [
                'pages' => ['new' => $portion->newPages, 'near' => $portion->nearPages, 'far' => $portion->farPages],
            ];
            if ($shape->memorizes()) {
                $memorized = array_values(array_unique([...$memorized, ...$portion->completedPages]));
            }
            if ($portion->farPages !== []) {
                $far = $portion->farPages[array_key_last($portion->farPages)];
            }
            // Review and consolidation brings nothing new: the learner stays where they are, the far review moves on.
            $at = $shape->hasNew() ? $portion->next : $at;
        }

        return ['errors' => [], 'lessons' => $out, 'total' => $this->total($shape, $layout)];
    }

    /** About how many lessons the scope's new parts take (null when nothing is new). */
    private function total(ProgramShape $shape, MushafLayout $layout): ?int
    {
        if (! $shape->hasNew()) {
            return null;
        }
        if ($shape->isNoorani()) {
            return $shape->toVerse - $shape->fromVerse + 1;
        }
        $weight = 0.0;
        foreach (LessonPortion::order($shape, $layout) as $index) {
            $weight += $layout->weight($index, $shape->unit);
        }

        return max(1, (int) ceil($weight / $shape->newAmount - 1e-9));
    }
}
