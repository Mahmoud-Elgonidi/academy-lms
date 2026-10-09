<?php

namespace Modules\People\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * A person's profile (a student's birth date, stage and details; a
 * teacher's languages and preferences), who they follow or who follows them
 * as guardian, and the consents given for a child. Erasing blanks the
 * profile and undoes the guardian links; the consents stay as given (what a
 * guardian agreed to, and when, is the academy's evidence), and a guardian
 * cannot go while a child with no way in of their own has nobody else.
 */
final class ProfileData implements PersonalDataSource
{
    public function key(): string
    {
        return 'profile';
    }

    public function export(string $userId): PersonalDataPart
    {
        $student = DB::table('student_profiles')->where('user_id', $userId)->first(['birth_date', 'stage', 'gender', 'country', 'school_grade', 'teacher_gender']);
        $teacher = DB::table('teacher_profiles')->where('user_id', $userId)->first(['gender', 'languages', 'dialect', 'age_groups', 'accepts_new', 'accepts_trials']);
        $people = fn (string $mine, string $theirs) => DB::table('guardian_student as g')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', "g.{$theirs}"))
            ->where("g.{$mine}", $userId)->orderBy('u.name')->get(['u.name', 'g.relationship', 'g.is_primary', 'g.receives_reports'])
            ->map(fn (object $r) => (array) $r)->all();

        return new PersonalDataPart([
            'student' => $student === null ? null : (array) $student,
            'teacher' => $teacher === null ? null : ['languages' => json_decode((string) $teacher->languages, true), 'age_groups' => json_decode((string) $teacher->age_groups, true)]
                + array_diff_key((array) $teacher, ['languages' => 1, 'age_groups' => 1]),
            'children' => $people('guardian_id', 'student_id'),
            'guardians' => $people('student_id', 'guardian_id'),
            'guardian_consents' => DB::table('guardian_consents')->where(fn ($q) => $q->where('student_id', $userId)->orWhere('guardian_id', $userId))
                ->orderBy('granted_at')->get(['purpose', 'version', 'via', 'granted_at', 'withdrawn_at'])->map(fn (object $r) => (array) $r)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        // Children with no way in of their own whose only guardian this is (and who are not going themselves).
        $alone = DB::table('guardian_student as g')
            ->join('users as c', fn ($j) => $j->on('c.academy_id', '=', 'g.academy_id')->on('c.id', '=', 'g.student_id'))
            ->where('g.guardian_id', $userId)->where('c.is_managed_minor', true)->where('c.status', '!=', 'erased')
            ->whereNotExists(fn ($q) => $q->from('guardian_student as o')->whereColumn('o.academy_id', 'g.academy_id')
                ->whereColumn('o.student_id', 'g.student_id')->where('o.guardian_id', '!=', $userId))
            ->orderBy('c.name')->pluck('c.name')->all();

        return $alone === [] ? [] : [__('people::privacy.only_guardian', ['children' => implode('، ', $alone)])];
    }

    public function erase(string $userId): array
    {
        $profiles = DB::table('student_profiles')->where('user_id', $userId)
            ->update(['birth_date' => null, 'gender' => null, 'country' => null, 'school_grade' => null, 'teacher_gender' => null, 'updated_at' => now()]);
        $profiles += DB::table('teacher_profiles')->where('user_id', $userId)
            ->update(['gender' => null, 'languages' => '[]', 'dialect' => null, 'accepts_new' => false, 'accepts_trials' => false, 'updated_at' => now()]);
        $links = DB::table('guardian_student')->where(fn ($q) => $q->where('guardian_id', $userId)->orWhere('student_id', $userId))->delete();

        return ['profile' => $profiles, 'guardian_links' => $links];
    }
}
