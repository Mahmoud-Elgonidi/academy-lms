<?php

namespace Modules\Classroom\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Who may watch a lesson's recording, and who may start and stop one. Staff
 * with recordings.view watch any; the lesson's teacher their own, and its
 * student and their guardians theirs, when the academy allows it. Starting
 * and stopping is for whoever runs the lesson: its teacher, or a supervisor
 * watching it.
 */
final class RecordingAccess
{
    public function __construct(
        private readonly AttendanceApi $lessons,
        private readonly ClassroomAccess $classroom,
        private readonly PeopleApi $people,
    ) {}

    /** staff | teacher | family, or null when they may not watch it */
    public function watcherAs(string $userId, Recording $recording, ?LessonCard $lesson = null): ?string
    {
        if (! DB::table('users')->where('id', $userId)->where('status', 'active')->exists()) {
            return null;
        }
        $user = app('auth')->getProvider()?->retrieveById($userId);
        if ($user !== null && Gate::forUser($user)->allows(Ability::ViewRecordings->value)) {
            return 'staff';
        }
        $lesson ??= $this->lessons->lesson($recording->session_id);
        $settings = ClassroomSettings::current();

        return match (true) {
            $lesson === null => null,
            $lesson->teacherId === $userId => $settings->recording_teacher ? 'teacher' : null,
            $lesson->studentId !== null && ($userId === $lesson->studentId || in_array($userId, $this->people->guardiansOf($lesson->studentId), true)) => $settings->recording_family ? 'family' : null,
            default => null,
        };
    }

    /** The lesson's teacher, or a supervisor in it. */
    public function controls(string $userId, LessonCard $lesson): bool
    {
        return in_array($this->classroom->seatFor($userId, $lesson)?->role, ['teacher', 'observer'], true);
    }
}
