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
 * student's); a supervisor, hidden, who only watches. Nobody else.
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

        return match (true) {
            $userId === $lesson->teacherId => new Seat($userId, $lesson->teacherName, 'teacher', $userId),
            $lesson->studentId !== null && $userId === $lesson->studentId => new Seat($userId, $lesson->studentName, 'student', $userId),
            $lesson->studentId !== null && in_array($userId, $this->people->guardiansOf($lesson->studentId), true) => new Seat($lesson->studentId, $lesson->studentName, 'student', $userId),
            $this->isSupervisor($userId) => new Seat("observer:{$userId}", $user->name, 'observer', $userId),
            default => null,
        };
    }

    private function isSupervisor(string $userId): bool
    {
        $user = app('auth')->getProvider()?->retrieveById($userId);

        return $user !== null && Gate::forUser($user)->allows(Ability::ViewLiveBoard->value);
    }
}
