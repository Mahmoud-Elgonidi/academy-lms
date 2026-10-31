<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\TimeFormat;
use Modules\SharedKernel\Time\ViewerClock;

/**
 * Who a session concerns and how to name it in a message: the student, their
 * guardians, the teacher, the subject. A group lesson has a student per
 * seat: of() names one (the one asked for, else the first), and seats()
 * gives each seat on its own, so a family only ever reads its own child's
 * name (docs/architecture/02, "الحصص الجماعية": no classmate's details).
 */
final class SessionPeople
{
    public function __construct(private readonly PeopleApi $people) {}

    /** @return array{session_id: string, starts_at: CarbonImmutable, teacher_id: string, teacher: string, student_id: ?string, student: string, subject: Localized, family: list<string>, group: bool}|null */
    public function of(string $sessionId, ?string $studentId = null): ?array
    {
        return $this->rows($sessionId, $studentId, true)[0] ?? null;
    }

    /**
     * One entry per student seated in the lesson (one for a one-to-one lesson), each with only that student's family.
     *
     * @return list<array{session_id: string, starts_at: CarbonImmutable, teacher_id: string, teacher: string, student_id: ?string, student: string, subject: Localized, family: list<string>, group: bool}>
     */
    public function seats(string $sessionId): array
    {
        return $this->rows($sessionId, null, false);
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sessionId, ?string $studentId, bool $one): array
    {
        $lesson = DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->where('s.id', $sessionId)
            ->first(['s.id', 's.starts_at', 's.teacher_id', 's.group_id', 't.name as teacher', 't.time_format', 'su.name_ar', 'su.name_en']);
        if ($lesson === null) {
            return [];
        }
        $students = DB::table('session_participants as p')
            ->join('users as st', fn ($j) => $j->on('st.academy_id', '=', 'p.academy_id')->on('st.id', '=', 'p.user_id'))
            ->where('p.session_id', $sessionId)->where('p.role', 'student')
            ->when($studentId, fn ($q) => $q->where('p.user_id', $studentId))
            ->orderBy('p.user_id')
            ->when($one, fn ($q) => $q->limit(1))
            ->get(['p.user_id', 'st.name']);
        if ($students->isEmpty() && $one) {
            $students = collect([(object) ['user_id' => null, 'name' => '']]);
        }

        return $students->map(fn (object $st) => [
            'session_id' => $lesson->id,
            'starts_at' => CarbonImmutable::parse($lesson->starts_at),
            'teacher_id' => $lesson->teacher_id,
            'teacher' => $lesson->teacher,
            'student_id' => $st->user_id,
            'student' => (string) $st->name,
            'subject' => new Localized($lesson->name_ar, $lesson->name_en),
            'family' => $st->user_id === null ? [] : [$st->user_id, ...$this->people->guardiansOf($st->user_id)],
            'group' => $lesson->group_id !== null,
            'group_id' => $lesson->group_id,
            // How the teacher's messages name a group lesson: the group (subject and weekly times), never its first student.
            'group_name' => $lesson->group_id === null ? null : $this->groupName((string) $lesson->group_id, $lesson->name_ar, $lesson->name_en, TimeFormat::of($lesson->time_format)),
        ])->values()->all();
    }

    /**
     * A group by its subject and weekly times on its teacher's clock, in both languages and the teacher's way of
     * reading a clock (their own, else the academy's): "مجموعة النحو (الاثنين والخميس 4:00 م)".
     */
    public function groupName(string $groupId, string $subjectAr, string $subjectEn, ?TimeFormat $format = null): Localized
    {
        $format ??= app(ViewerClock::class)->academy();
        $times = DB::table('recurring_slots')->where('group_id', $groupId)->where('status', 'active')
            ->orderBy('local_start')->orderBy('weekday')->get(['weekday', 'local_start']);
        $line = function (string $locale) use ($times, $format): string {
            $order = array_flip($locale === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7]);

            return $times->sortBy(fn (object $t) => [$order[(int) $t->weekday] ?? 0, $t->local_start])->groupBy(fn (object $t) => substr((string) $t->local_start, 0, 5))
                ->map(fn ($same, string $start) => $same->map(fn (object $t) => trans('scheduling::shifts.days.'.$t->weekday, [], $locale))->unique()->implode(trans('scheduling::find.free.and', [], $locale))
                    .' '.Clock::wall($start, $format, $locale))
                ->implode(' · ');
        };

        return new Localized(
            trans('scheduling::group_outcomes.teacher_name', ['subject' => $subjectAr, 'times' => $line('ar')], 'ar'),
            trans('scheduling::group_outcomes.teacher_name', ['subject' => $subjectEn, 'times' => $line('en')], 'en'),
        );
    }
}
