<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\People\Contracts\PeopleApi;

/** Who a session concerns and how to name it in a message: the student, their guardians, the teacher, the subject. */
final class SessionPeople
{
    public function __construct(private readonly PeopleApi $people) {}

    /** @return array{session_id: string, starts_at: CarbonImmutable, teacher_id: string, teacher: string, student_id: ?string, student: string, subject: Localized, family: list<string>}|null */
    public function of(string $sessionId): ?array
    {
        $row = DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', 'student'))
            ->leftJoin('users as st', fn ($j) => $j->on('st.academy_id', '=', 'p.academy_id')->on('st.id', '=', 'p.user_id'))
            ->where('s.id', $sessionId)
            ->first(['s.id', 's.starts_at', 's.teacher_id', 't.name as teacher', 'p.user_id as student_id', 'st.name as student', 'su.name_ar', 'su.name_en']);
        if ($row === null) {
            return null;
        }

        return [
            'session_id' => $row->id,
            'starts_at' => CarbonImmutable::parse($row->starts_at),
            'teacher_id' => $row->teacher_id,
            'teacher' => $row->teacher,
            'student_id' => $row->student_id,
            'student' => (string) $row->student,
            'subject' => new Localized($row->name_ar, $row->name_en),
            'family' => $row->student_id === null ? [] : [$row->student_id, ...$this->people->guardiansOf($row->student_id)],
        ];
    }
}
