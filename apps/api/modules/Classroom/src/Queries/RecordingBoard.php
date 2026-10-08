<?php

namespace Modules\Classroom\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\Classroom\Models\RecordedPerson;
use Modules\Classroom\Models\Recording;

/**
 * The office's recordings screen: every recording with its lesson (when,
 * the student, the teacher) and how often it was opened; and the people
 * lessons record, with whether each agreed (and how), or not yet.
 */
final class RecordingBoard
{
    public const PEOPLE_TABS = ['missing', 'agreed'];

    /** @return Builder<Recording> */
    public function recordings(): Builder
    {
        return Recording::query()
            ->join('sessions as s', fn ($j) => $j->on('s.id', '=', 'recordings.session_id')->on('s.academy_id', '=', 'recordings.academy_id'))
            ->join('users as t', fn ($j) => $j->on('t.id', '=', 's.teacher_id')->on('t.academy_id', '=', 's.academy_id'))
            ->select('recordings.*', 's.starts_at as lesson_at', 's.teacher_id', 't.name as teacher_name')
            ->selectRaw("(select u.name from session_participants p join users u on u.academy_id = p.academy_id and u.id = p.user_id where p.academy_id = s.academy_id and p.session_id = s.id and p.role = 'student' limit 1) as student_name")
            ->selectRaw('(select count(*) from recording_access_logs l where l.academy_id = recordings.academy_id and l.recording_id = recordings.id) as views');
    }

    /** @return Builder<RecordedPerson> */
    public function people(string $tab): Builder
    {
        $live = 'select 1 from recording_consents c where c.academy_id = users.academy_id and c.person_id = users.id and c.withdrawn_at is null';
        $query = RecordedPerson::query()
            ->whereRaw("exists (select 1 from user_roles r where r.academy_id = users.academy_id and r.user_id = users.id and r.role in ('teacher', 'student'))")
            ->where('users.status', 'active')
            ->select('users.id', 'users.name', 'users.academy_id')
            ->selectRaw("(case when exists (select 1 from user_roles r where r.academy_id = users.academy_id and r.user_id = users.id and r.role = 'teacher') then 'teacher' else 'student' end) as role")
            ->selectRaw("(select string_agg(gu.name, ', ' order by gu.name) from guardian_student g join users gu on gu.academy_id = g.academy_id and gu.id = g.guardian_id where g.academy_id = users.academy_id and g.student_id = users.id) as guardians")
            ->selectRaw('(select c.granted_at from recording_consents c where c.academy_id = users.academy_id and c.person_id = users.id and c.withdrawn_at is null) as granted_at')
            ->selectRaw('(select c.via from recording_consents c where c.academy_id = users.academy_id and c.person_id = users.id and c.withdrawn_at is null) as via')
            ->selectRaw('(select g.name from recording_consents c join users g on g.academy_id = c.academy_id and g.id = c.given_by where c.academy_id = users.academy_id and c.person_id = users.id and c.withdrawn_at is null) as given_by_name')
            ->selectRaw('(select c.note from recording_consents c where c.academy_id = users.academy_id and c.person_id = users.id and c.withdrawn_at is null) as note')
            ->selectRaw('(select max(c.withdrawn_at) from recording_consents c where c.academy_id = users.academy_id and c.person_id = users.id) as withdrawn_at');

        return $tab === 'agreed' ? $query->whereRaw("exists ({$live})") : $query->whereRaw("not exists ({$live})");
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'recordings' => Recording::query()->where('status', 'ready')->count(),
            'missing' => $this->people('missing')->count(),
            'agreed' => $this->people('agreed')->count(),
        ];
    }
}
