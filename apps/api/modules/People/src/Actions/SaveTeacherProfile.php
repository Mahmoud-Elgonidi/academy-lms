<?php

namespace Modules\People\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Models\TeacherProfile;

/**
 * Creates or updates what matching knows about a teacher: who they are,
 * the ages they teach, their caps, and the subjects they teach with the
 * levels (from one to another) and categories of each. A subject given
 * without its details keeps the ones it had (an import updating a teacher).
 */
final class SaveTeacherProfile
{
    public function __construct(private readonly CatalogApi $catalog) {}

    public function handle(TeacherProfileData $data): TeacherProfile
    {
        $e = 'people::people.errors.';
        if (! in_array($data->gender, ['female', 'male'], true)) {
            throw ValidationException::withMessages(['gender' => __($e.'gender')]);
        }
        if ($data->ageGroups !== null && array_diff($data->ageGroups, array_column(AgeGroup::cases(), 'value')) !== []) {
            throw ValidationException::withMessages(['age_groups' => __($e.'age_groups')]);
        }
        if ($data->maxDailyLessons !== null && ($data->maxDailyLessons < 1 || $data->maxDailyLessons > 30)) {
            throw ValidationException::withMessages(['max_daily_lessons' => __($e.'max_daily')]);
        }
        if ($data->maxWeeklyMinutes !== null && ($data->maxWeeklyMinutes < 30 || $data->maxWeeklyMinutes > 100 * 60)) {
            throw ValidationException::withMessages(['max_weekly_hours' => __($e.'max_weekly')]);
        }
        $details = [];
        foreach ($data->subjects as $subject) {
            $details[$subject->subjectId] = $this->checked($subject);
        }
        $subjectIds = array_values(array_unique([...$data->subjectIds, ...array_keys($details)]));

        return DB::transaction(function () use ($data, $details, $subjectIds) {
            $profile = TeacherProfile::query()->lockForUpdate()->firstOrNew(['user_id' => $data->teacherId]);
            $profile->fill([
                'gender' => $data->gender,
                'languages' => array_values(array_unique($data->languages ?: ['ar'])),
                'dialect' => $data->dialect,
                'accepts_new' => $data->acceptsNew,
                'accepts_trials' => $data->acceptsTrials,
                'max_weekly_min' => $data->maxWeeklyMinutes,
                'max_daily_lessons' => $data->maxDailyLessons,
            ]);
            if ($data->ageGroups !== null || ! $profile->exists) {
                $profile->age_groups = array_values(array_unique($data->ageGroups ?? []));
            }
            $profile->save();

            $rows = DB::table('teacher_subjects')->where('teacher_id', $data->teacherId);
            (clone $rows)->whereNotIn('subject_id', $subjectIds)->delete();
            $had = (clone $rows)->pluck('subject_id')->all();
            foreach ($subjectIds as $subjectId) {
                $detail = $details[$subjectId] ?? null;
                $levels = $detail === null ? [] : ['level_from_id' => $detail->levelFromId, 'level_to_id' => $detail->levelToId];
                if (! in_array($subjectId, $had, true)) {
                    DB::table('teacher_subjects')->insert(['teacher_id' => $data->teacherId, 'subject_id' => $subjectId] + $levels);
                } elseif ($detail !== null) {
                    DB::table('teacher_subjects')->where('teacher_id', $data->teacherId)->where('subject_id', $subjectId)->update($levels);
                }
                if ($detail !== null) {
                    DB::table('teacher_subject_categories')->where('teacher_id', $data->teacherId)->where('subject_id', $subjectId)->delete();
                    foreach ($detail->categoryIds as $categoryId) {
                        DB::table('teacher_subject_categories')->insert(['teacher_id' => $data->teacherId, 'subject_id' => $subjectId, 'category_id' => $categoryId]);
                    }
                }
            }

            return $profile;
        });
    }

    /** The subject's own levels, in order (from at or below to), and its own categories. */
    private function checked(TeacherSubjectData $subject): TeacherSubjectData
    {
        $e = 'people::people.errors.';
        $levels = array_keys($this->catalog->levels($subject->subjectId));
        $from = $subject->levelFromId === null ? null : array_search($subject->levelFromId, $levels, true);
        $to = $subject->levelToId === null ? null : array_search($subject->levelToId, $levels, true);
        if ($from === false || $to === false) {
            throw ValidationException::withMessages(['subjects' => __($e.'level')]);
        }
        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages(['subjects' => __($e.'level_order')]);
        }
        $categories = array_values(array_unique($subject->categoryIds));
        if (array_diff($categories, array_keys($this->catalog->categories($subject->subjectId))) !== []) {
            throw ValidationException::withMessages(['subjects' => __($e.'category')]);
        }

        return new TeacherSubjectData($subject->subjectId, $subject->levelFromId, $subject->levelToId, $categories);
    }
}
