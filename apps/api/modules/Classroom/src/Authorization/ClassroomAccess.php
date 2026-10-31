<?php

namespace Modules\Classroom\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Classroom\Video\Seat;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Who may enter a lesson's room, and as what: its teacher; its student, or a
 * guardian at the student's device (as the student, so attendance is the
 * student's); a supervisor, hidden, who only watches. Nobody else. A child
 * whose account waits for a guardian's consent does not come in by their own
 * link or account (a guardian can still bring them in from theirs). A
 * group lesson seats each of its students, and each one's guardians as
 * that student (a guardian of two in it, as the first of them).
 */
final class ClassroomAccess
{
    public function __construct(private readonly PeopleApi $people) {}

    public function seatFor(string $userId, LessonCard $lesson): ?Seat
    {
        $user = DB::table('users')->where('id', $userId)->where('status', 'active')->first(['id', 'name']);
        if ($user === null) {
            return null;
        }

        if ($userId === $lesson->teacherId) {
            return new Seat($userId, $lesson->teacherName, 'teacher', $userId);
        }
        if ($lesson->hasStudent($userId)) {
            return $this->waitsForConsent($userId, $lesson) ? null : new Seat($userId, $this->nameOf($lesson, $userId), 'student', $userId);
        }
        foreach ($lesson->studentIds() as $studentId) {
            if (in_array($userId, $this->people->guardiansOf($studentId), true)) {
                return new Seat($studentId, $this->nameOf($lesson, $studentId), 'student', $userId);
            }
        }

        return $this->isSupervisor($userId) ? new Seat("observer:{$userId}", $user->name, 'observer', $userId) : null;
    }

    /** One of the lesson's own students, whose account still waits for a guardian's consent. */
    public function waitsForConsent(string $userId, LessonCard $lesson): bool
    {
        return $lesson->hasStudent($userId) && $this->people->consentState($userId)->waiting();
    }

    private function nameOf(LessonCard $lesson, string $studentId): string
    {
        foreach ($lesson->students as $student) {
            if ($student['id'] === $studentId) {
                return $student['name'];
            }
        }

        return $lesson->studentName;
    }

    private function isSupervisor(string $userId): bool
    {
        // The academy's users by name: under the API's token guard the current guard has no provider of its own.
        $user = app('auth')->createUserProvider('users')?->retrieveById($userId);

        return $user !== null && Gate::forUser($user)->allows(Ability::ViewLiveBoard->value);
    }
}
