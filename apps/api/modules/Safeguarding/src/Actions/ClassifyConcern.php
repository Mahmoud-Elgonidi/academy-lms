<?php

namespace Modules\Safeguarding\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Safeguarding\Enums\ConcernCategory;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;

/** A lead puts right what a concern is about: its kind, the child, the teacher it may be about, and whether it is urgent. */
final class ClassifyConcern
{
    public function handle(string $concernId, string $category, ?string $studentId, ?string $teacherId, bool $urgent, string $actorId): Concern
    {
        $kind = ConcernCategory::tryFrom($category) ?? throw ValidationException::withMessages(['category' => __('safeguarding::concerns.errors.category')]);
        foreach (['student' => [$studentId, 'student'], 'teacher' => [$teacherId, 'teacher']] as $field => [$id, $role]) {
            if ($id !== null && ! DB::table('user_roles')->where('user_id', $id)->where('role', $role)->exists()) {
                throw ValidationException::withMessages([$field => __('safeguarding::concerns.errors.'.$field)]);
            }
        }

        return DB::transaction(function () use ($concernId, $kind, $studentId, $teacherId, $urgent, $actorId) {
            $concern = Concern::query()->whereKey($concernId)->lockForUpdate()->firstOrFail();
            $before = ['category' => $concern->category->value, 'student' => $concern->student_id, 'teacher' => $concern->teacher_id, 'urgent' => $concern->urgent];
            $concern->update(['category' => $kind, 'student_id' => $studentId, 'teacher_id' => $teacherId, 'urgent' => $urgent]);
            $after = ['category' => $kind->value, 'student' => $studentId, 'teacher' => $teacherId, 'urgent' => $urgent];
            if ($before !== $after) {
                ConcernEntry::event($concern, 'classified', $actorId, ['from' => $before, 'to' => $after]);
            }

            return $concern;
        });
    }
}
