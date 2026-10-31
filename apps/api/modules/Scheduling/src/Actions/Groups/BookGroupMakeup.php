<?php

namespace Modules\Scheduling\Actions\Groups;

use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Support\CourseDays;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * A makeup lesson in a free seat of another group's lesson of the same
 * subject (docs/architecture/02, "الحصص الجماعية"): the student sits in
 * that one lesson only, paid from makeup credit kept for group makeup (or
 * kept for either), valid on that day. Never their own group's lesson,
 * never past the group's seats, and only where they could join the group
 * itself (its ages, minors and the teacher's clearance).
 */
final class BookGroupMakeup
{
    public function __construct(
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
        private readonly PeopleApi $people,
        private readonly MinorsGuard $minors,
        private readonly CourseDays $days,
        private readonly GroupSeats $seats,
    ) {}

    public function handle(string $sessionId, string $enrollmentId, ?string $actorId): Session
    {
        $course = $this->enrollments->find($enrollmentId);
        if ($course === null || $course->creditAccountId === null) {
            CreateGroup::refuse('enrollment', 'no_group_makeup');
        }

        return DB::transaction(function () use ($sessionId, $course, $actorId) {
            $lesson = Session::query()->whereKey($sessionId)->lock('for no key update')->first();
            if ($lesson === null || $lesson->group_id === null) {
                CreateGroup::refuse('session', 'not_group_lesson');
            }
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['group:'.$lesson->group_id]);
            $group = LessonGroup::query()->findOrFail($lesson->group_id);
            if ($lesson->status !== SessionStatus::Scheduled || $lesson->starts_at->isPast()) {
                CreateGroup::refuse('session', 'lesson_closed');
            }
            if ($lesson->subject_id !== $course->subjectId) {
                CreateGroup::refuse('session', 'other_subject');
            }
            if ($group->members()->where('student_id', $course->studentId)->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $this->days->today()->toDateString()))->exists()) {
                CreateGroup::refuse('session', 'own_group');
            }
            $students = SessionParticipant::query()->where('session_id', $lesson->id)->where('role', ParticipantRole::Student->value)->where('is_active', true)->pluck('user_id')->all();
            CreateGroup::refuse('session', GroupRules::seat(count($students), $group->capacity), ['capacity' => $group->capacity]);
            $this->days->assertOpen($course, $lesson->starts_at);

            $age = AgeGroup::forAge($this->people->studentProfile($course->studentId)->age)?->value;
            $minors = $this->people->minors([$course->studentId, ...$students]);
            CreateGroup::refuse('session', GroupRules::ages($group->age_group, $age, (bool) ($minors[$course->studentId] ?? false),
                array_map(fn (string $id) => (bool) ($minors[$id] ?? false), $students), SchedulingSettings::current()->allow_mixed_ages), [
                    'age' => $group->age_group === null ? '' : AgeGroup::from($group->age_group)->label(),
                ]);
            $this->minors->assert($lesson->teacher_id, [$course->studentId, ...$students]);

            if (! $this->seats->seat($lesson, $course->studentId, $course->id, $actorId, FundingStatus::Makeup)) {
                CreateGroup::refuse('session', 'student_busy');
            }
            $key = $this->seats->reserveKey($lesson->id, $course->creditAccountId, $course->studentId);
            if (! $this->credits->reserveFor($lesson->id, $course->creditAccountId, $lesson->durationMinutes(), $lesson->starts_at, $key, forMakeup: true, makeupFor: 'group')) {
                CreateGroup::refuse('session', 'no_group_makeup');
            }

            return $lesson->refresh();
        });
    }
}
