<?php

namespace Modules\People\Actions\Directory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\IdentityApi;

/**
 * Turns a teacher's, student's or guardian's account off (they can no longer
 * sign in, and are signed out) or on again, through Identity (audited). Not
 * while it would strand lessons: a teacher with running courses or lessons
 * ahead, a student with running courses, or the only guardian of a child
 * with running courses. Those move or end first.
 */
final class SetPersonActive
{
    /** A course still running, paused or waiting for its first payment. */
    private const RUNNING = ['pending_payment', 'active', 'paused'];

    public function __construct(private readonly IdentityApi $identity) {}

    public function handle(string $userId, bool $active): void
    {
        if (! $active) {
            $e = 'people::directory.errors.';
            $courses = fn () => DB::table('enrollments')->whereIn('status', self::RUNNING);
            if ($courses()->where('teacher_id', $userId)->exists()
                || DB::table('sessions')->where('teacher_id', $userId)->where('status', 'scheduled')->where('starts_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['status' => __($e.'teacher_busy')]);
            }
            if ($courses()->where('student_id', $userId)->exists()) {
                throw ValidationException::withMessages(['status' => __($e.'student_busy')]);
            }
            $alone = DB::table('guardian_student as gs')
                ->join('users as s', fn ($j) => $j->on('s.academy_id', '=', 'gs.academy_id')->on('s.id', '=', 'gs.student_id'))
                ->where('gs.guardian_id', $userId)->where('s.is_managed_minor', true)
                ->whereNotExists(fn ($q) => $q->from('guardian_student as o')->whereColumn('o.student_id', 'gs.student_id')->where('o.guardian_id', '!=', $userId))
                ->whereExists(fn ($q) => $q->from('enrollments as e')->whereColumn('e.student_id', 'gs.student_id')->whereIn('e.status', self::RUNNING))
                ->exists();
            if ($alone) {
                throw ValidationException::withMessages(['status' => __($e.'guardian_busy')]);
            }
        }

        $this->identity->setActive($userId, $active);
    }
}
