<?php

namespace Modules\People\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\People\Contracts\DTOs\PublicTeacher;
use Modules\People\Contracts\DTOs\PublicTeacherSubject;
use Modules\People\Contracts\DTOs\TeacherCardSummary;
use Modules\People\Models\TeacherPublicCard;

/**
 * Teachers' cards for the academy's public page: on it are the cards their
 * teachers switched on and the academy left on, of teachers who teach now
 * (an active account with the teacher role and an active profile), with the
 * subjects and levels their profile says, in the current language. Reads
 * only the card, the profile and the catalogue's names: never an email, a
 * number, pay, KPIs, vetting or a pause.
 */
final class PublicTeachers
{
    /** @return list<PublicTeacher> */
    public function onPage(): array
    {
        $rows = $this->cards()->where('c.shown', true)->where('c.hidden_by_academy', false)
            ->where('u.status', 'active')->where('p.status', 'active')
            ->whereExists(fn ($q) => $q->from('user_roles as r')->whereColumn('r.user_id', 'c.teacher_id')->where('r.role', 'teacher'))
            ->orderBy('c.display_name')->orderBy('c.id')
            ->get(['c.id', 'c.teacher_id', 'c.display_name', 'c.bio', 'c.photo_path', 'p.gender', 'p.languages', 'p.age_groups', 'p.accepts_trials', 'p.accepts_groups']);
        if ($rows->isEmpty()) {
            return [];
        }

        $subjects = $this->subjects($rows->pluck('teacher_id')->all());

        $out = [];
        foreach ($rows as $row) {
            $out[] = new PublicTeacher(
                id: $row->id,
                displayName: $row->display_name,
                bio: $row->bio,
                photoUrl: $row->photo_path !== null ? Storage::disk(TeacherPublicCard::DISK)->url($row->photo_path) : null,
                gender: $row->gender,
                languages: array_values((array) json_decode((string) $row->languages, true)),
                ageGroups: array_values((array) json_decode((string) $row->age_groups, true)),
                subjects: $subjects[$row->teacher_id] ?? [],
                acceptsTrials: (bool) $row->accepts_trials,
                acceptsGroups: (bool) $row->accepts_groups,
            );
        }

        return $out;
    }

    /** @return list<TeacherCardSummary> every card the academy's teachers made, those on the page first */
    public function all(): array
    {
        $rows = $this->cards()
            ->leftJoin('user_roles as r', fn ($j) => $j->on('r.user_id', '=', 'c.teacher_id')->where('r.role', '=', 'teacher'))
            ->orderBy('c.display_name')->orderBy('c.id')
            ->get(['c.id', 'c.display_name', 'c.bio', 'c.photo_path', 'c.shown', 'c.hidden_by_academy', 'u.name', 'u.status', 'p.status as profile', 'r.role']);

        $out = [];
        foreach ($rows as $row) {
            $out[] = new TeacherCardSummary(
                id: $row->id,
                displayName: $row->display_name,
                teacherName: (string) $row->name,
                bio: $row->bio,
                photoUrl: $row->photo_path !== null ? Storage::disk(TeacherPublicCard::DISK)->url($row->photo_path) : null,
                shown: (bool) $row->shown,
                hiddenByAcademy: (bool) $row->hidden_by_academy,
                active: $row->status === 'active' && $row->profile === 'active' && $row->role !== null,
            );
        }
        usort($out, fn (TeacherCardSummary $a, TeacherCardSummary $b) => [! $a->onPage(), $a->displayName] <=> [! $b->onPage(), $b->displayName]);

        return $out;
    }

    /** @return list<PublicTeacherSubject> what this teacher's profile says they teach, as their card shows it */
    public function subjectsOf(string $teacherId): array
    {
        return $this->subjects([$teacherId])[$teacherId] ?? [];
    }

    /**
     * @param  list<string>  $teacherIds
     * @return array<string, list<PublicTeacherSubject>> teacher id => their subjects by name, with the levels they teach
     */
    private function subjects(array $teacherIds): array
    {
        $name = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';
        $rows = DB::table('teacher_subjects as ts')
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'ts.academy_id')->on('s.id', '=', 'ts.subject_id'))
            ->leftJoin('subject_levels as lf', fn ($j) => $j->on('lf.academy_id', '=', 'ts.academy_id')->on('lf.id', '=', 'ts.level_from_id'))
            ->leftJoin('subject_levels as lt', fn ($j) => $j->on('lt.academy_id', '=', 'ts.academy_id')->on('lt.id', '=', 'ts.level_to_id'))
            ->whereIn('ts.teacher_id', $teacherIds)
            ->orderBy("s.{$name}")
            ->get(['ts.teacher_id', "s.{$name} as subject", "lf.{$name} as level_from", "lt.{$name} as level_to"]);
        $out = [];
        foreach ($rows as $row) {
            $out[$row->teacher_id][] = new PublicTeacherSubject((string) $row->subject, $row->level_from, $row->level_to);
        }

        return $out;
    }

    private function cards(): Builder
    {
        return DB::table('teacher_public_cards as c')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'c.academy_id')->on('u.id', '=', 'c.teacher_id'))
            ->join('teacher_profiles as p', fn ($j) => $j->on('p.academy_id', '=', 'c.academy_id')->on('p.user_id', '=', 'c.teacher_id'));
    }
}
