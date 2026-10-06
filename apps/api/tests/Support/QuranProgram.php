<?php

namespace Tests\Support;

use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Models\Program;

/**
 * A small published program: level 1 with two units (3 lessons, then 2), and
 * level 2 with one unit of 2 lessons. Lesson ids land in $this->lessons, in
 * teaching order; the third lesson weighs 2 and needs two scores of 4+.
 */
trait QuranProgram
{
    protected function publishQuranProgram(string $subjectId): Program
    {
        $program = app(SaveProgram::class)->handle(null, ['subject_id' => $subjectId, 'name_ar' => 'نور البيان', 'name_en' => 'Nour Al-Bayan']);
        $version = $program->workingVersion();
        $save = app(SaveNode::class);
        $this->lessons = [];

        foreach ([['المستوى الأول', 'Level 1', [['الحروف', 'Letters', 3], ['الحركات', 'Vowels', 2]]], ['المستوى الثاني', 'Level 2', [['المدود', 'Prolongation', 2]]]] as [$lar, $len, $units]) {
            $level = $save->handle($version, null, ['kind' => 'level', 'title_ar' => $lar, 'title_en' => $len]);
            foreach ($units as [$uar, $uen, $count]) {
                $unit = $save->handle($version, null, ['kind' => 'unit', 'parent_id' => $level->id, 'title_ar' => $uar, 'title_en' => $uen]);
                for ($i = 1; $i <= $count; $i++) {
                    $n = count($this->lessons) + 1;
                    $this->lessons[] = $save->handle($version, null, [
                        'kind' => 'lesson', 'parent_id' => $unit->id, 'title_ar' => "درس {$n}", 'title_en' => "Lesson {$n}",
                        'weight' => $n === 3 ? 2 : 1, 'est_sessions' => 2, 'mastery_rule' => $n === 3 ? 'score_twice' : 'mastered_once',
                    ])->id;
                }
            }
        }
        app(PublishVersion::class)->handle($version, null);

        return $program;
    }
}
