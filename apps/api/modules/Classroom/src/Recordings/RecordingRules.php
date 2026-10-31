<?php

namespace Modules\Classroom\Recordings;

use Illuminate\Support\Facades\DB;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\RecordingConsent;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;

/**
 * Who has to agree before a lesson is recorded, and who may agree for whom
 * (docs/architecture/02, "تسجيل الحصص"): everyone the lesson records, its
 * teacher and its student (in a group lesson every student seated in it). A teacher agrees for themself (or the office
 * records it from their contract); an adult student for themself; a child
 * (one whose account needs a guardian's consent) only through a guardian.
 */
final class RecordingRules
{
    public function __construct(private readonly PeopleApi $people) {}

    /**
     * The people a lesson records, and whether each agreed.
     *
     * @return list<array{id: string, name: string, role: string, agreed: bool}>
     */
    public function people(LessonCard $lesson): array
    {
        $people = [['id' => $lesson->teacherId, 'name' => $lesson->teacherName, 'role' => 'teacher']];
        foreach ($lesson->students !== [] ? $lesson->students : array_filter([$lesson->studentId === null ? null : ['id' => $lesson->studentId, 'name' => $lesson->studentName]]) as $student) {
            $people[] = ['id' => $student['id'], 'name' => $student['name'], 'role' => 'student'];
        }
        $agreed = $this->consents(array_column($people, 'id'));

        return array_map(fn (array $p) => $p + ['agreed' => isset($agreed[$p['id']])], $people);
    }

    /** @return list<string> the names of who has not agreed yet */
    public function missing(LessonCard $lesson): array
    {
        return array_values(array_map(fn (array $p) => $p['name'], array_filter($this->people($lesson), fn (array $p) => ! $p['agreed'])));
    }

    /** Video or sound only: the academy's choice, or by age (video when a child is in it, for their safety; sound for adults). */
    public function mediaFor(LessonCard $lesson, ClassroomSettings $settings): string
    {
        return match ($settings->recording_media) {
            'video', 'audio' => $settings->recording_media,
            default => array_filter($lesson->studentIds(), fn (string $id) => $this->isChild($id)) !== [] ? 'video' : 'audio',
        };
    }

    /** A student whose account needs a guardian's consent: a guardian agrees to their recording too. */
    public function isChild(string $studentId): bool
    {
        return $this->people->consentState($studentId)->needed;
    }

    /** How someone may agree for a person: for themself (a teacher, an adult student), as a child's guardian, or not at all. */
    public function wayFor(string $actorId, string $personId): ?string
    {
        return match ($this->roleOf($personId)) {
            'teacher' => $actorId === $personId ? 'self' : null,
            'student' => $this->isChild($personId)
                ? (in_array($actorId, $this->people->guardiansOf($personId), true) ? 'guardian' : null)
                : ($actorId === $personId ? 'self' : null),
            default => null,
        };
    }

    /** teacher | student | null (someone a lesson does not record) */
    public function roleOf(string $personId): ?string
    {
        $roles = DB::table('user_roles')->where('user_id', $personId)->whereIn('role', ['teacher', 'student'])->pluck('role')->all();

        return in_array('teacher', $roles, true) ? 'teacher' : (in_array('student', $roles, true) ? 'student' : null);
    }

    /**
     * @param  list<string>  $personIds
     * @return array<string, RecordingConsent> the live consents, by person
     */
    public function consents(array $personIds): array
    {
        return $personIds === [] ? [] : RecordingConsent::query()->live()->whereIn('person_id', $personIds)->get()->keyBy('person_id')->all();
    }
}
