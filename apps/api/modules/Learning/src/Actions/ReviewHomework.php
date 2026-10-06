<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Contracts\Events\HomeworkReviewed;
use Modules\Learning\Models\Homework;

/** The teacher marks handed-in homework: a grade from 1 to 5 and feedback the family sees. */
final class ReviewHomework
{
    public function handle(Homework $homework, string $actorId, ?int $grade, ?string $feedback): Homework
    {
        if ($homework->status !== 'submitted') {
            throw ValidationException::withMessages(['homework' => __('learning::homework.errors.not_submitted')]);
        }
        Validator::make(['grade' => $grade, 'feedback' => $feedback], [
            'grade' => ['nullable', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:3000'],
        ], [], trans('learning::homework.attributes'))->validate();

        return DB::transaction(function () use ($homework, $actorId, $grade, $feedback) {
            $homework->fill([
                'status' => 'reviewed', 'grade' => $grade, 'feedback' => trim((string) $feedback) ?: null,
                'reviewed_by' => $actorId, 'reviewed_at' => CarbonImmutable::now(),
            ])->save();
            DB::afterCommit(fn () => event(new HomeworkReviewed($homework->academy_id, $homework->id, $homework->student_id, $grade)));

            return $homework;
        });
    }
}
