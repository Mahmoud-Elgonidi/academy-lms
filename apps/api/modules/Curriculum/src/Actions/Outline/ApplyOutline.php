<?php

namespace Modules\Curriculum\Actions\Outline;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Saves a reviewed outline (Domain\Outline\OutlineRules rows: what is ticked,
 * under ticked parents) at the end of a draft version, in one transaction:
 * every level, unit and lesson through SaveNode, so a lesson's passage of the
 * Quran is checked against the platform's copy like any other. Refused on a
 * version that is not a draft, with nothing ticked, or past LEVELS_MAX levels
 * or LESSONS_MAX lessons with what the version has. Audited with the counts.
 */
final class ApplyOutline
{
    public function __construct(
        private readonly SaveNode $save,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{levels: int, units: int, lessons: int}
     */
    public function handle(CurriculumVersion $version, array $rows): array
    {
        $l = 'curriculum::outline.errors.';
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => __($l.'not_draft')]);
        }
        // Held to the maxima as ticked (never cut short quietly), then made whole.
        $selected = OutlineRules::selected(array_values($rows));
        $rows = OutlineRules::clean($selected)['rows'];

        return DB::transaction(function () use ($version, $selected, $rows, $l) {
            CurriculumVersion::query()->whereKey($version->id)->lockForUpdate()->first();
            $have = Node::query()->where('version_id', $version->id)->selectRaw("count(*) filter (where kind = 'level') as levels, count(*) filter (where kind = 'lesson') as lessons")->first();
            $why = $rows === [] ? 'empty' : OutlineRules::refusal($selected, (int) $have?->levels, (int) $have?->lessons);
            if ($why !== null) {
                throw ValidationException::withMessages(['version' => __($l.$why, ['levels' => OutlineRules::LEVELS_MAX, 'lessons' => OutlineRules::LESSONS_MAX])]);
            }

            $parents = OutlineRules::parents($rows);
            $made = [];
            foreach ($rows as $i => $row) {
                $made[$i] = $this->save->handle($version, null, [
                    'kind' => $row['kind'],
                    'parent_id' => $parents[$i] === null ? null : $made[$parents[$i]]->id,
                    'title_ar' => $row['title_ar'],
                    'title_en' => $row['title_en'],
                    'objectives' => $row['objective'],
                ] + ($row['quran'] !== null ? ['text_source' => ['kind' => 'quran'] + $row['quran']] : []));
            }

            $counts = OutlineRules::counts($rows);
            $this->audit->record('curriculum.outline_applied', 'curriculum_version', $version->id, $counts);

            return $counts;
        });
    }
}
