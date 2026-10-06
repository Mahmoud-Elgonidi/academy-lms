<?php

namespace Modules\People\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Models\TeacherProfile;

/** Creates or updates what matching knows about a teacher, and the subjects they teach. */
final class SaveTeacherProfile
{
    public function handle(TeacherProfileData $data): TeacherProfile
    {
        if (! in_array($data->gender, ['female', 'male'], true)) {
            throw ValidationException::withMessages(['gender' => __('people::people.errors.gender')]);
        }

        return DB::transaction(function () use ($data) {
            $profile = TeacherProfile::query()->firstOrNew(['user_id' => $data->teacherId]);
            $profile->fill([
                'gender' => $data->gender,
                'languages' => array_values(array_unique($data->languages ?: ['ar'])),
                'dialect' => $data->dialect,
                'accepts_new' => $data->acceptsNew,
                'accepts_trials' => $data->acceptsTrials,
                'max_weekly_min' => $data->maxWeeklyMinutes,
            ])->save();

            DB::table('teacher_subjects')->where('teacher_id', $data->teacherId)->delete();
            foreach (array_unique($data->subjectIds) as $subjectId) {
                DB::table('teacher_subjects')->insert(['teacher_id' => $data->teacherId, 'subject_id' => $subjectId]);
            }

            return $profile;
        });
    }
}
