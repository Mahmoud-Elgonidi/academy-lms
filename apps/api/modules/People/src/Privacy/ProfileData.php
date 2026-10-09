<?php

namespace Modules\People\Privacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\People\Models\TeacherCheck;
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
        $checks = DB::table('teacher_checks')->where('teacher_id', $userId)->orderBy('created_at')
            ->get(['kind', 'reference', 'issued_on', 'expires_on', 'status', 'decision_note', 'file_path', 'file_name', 'created_at'])->values();
        $scan = fn (object $c, int $i) => 'checks/'.($i + 1).'-'.basename((string) $c->file_name);
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
            // A teacher's vetting documents: what they are and where they stand (the scans come with the copy).
            'teacher_checks' => $checks->map(fn (object $c, int $i) => ['kind' => $c->kind, 'reference' => $c->reference, 'issued_on' => $c->issued_on, 'expires_on' => $c->expires_on,
                'status' => $c->status, 'note' => $c->decision_note, 'file' => $c->file_path ? $scan($c, $i) : null, 'at' => $c->created_at])->all(),
        ], $checks->filter(fn (object $c) => $c->file_path !== null)->mapWithKeys(fn (object $c, int $i) => [$c->file_path => $scan($c, $i)])->all());
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
        // A teacher's vetting record stays (who was checked, and when); the scans and certificate numbers go.
        $scans = DB::table('teacher_checks')->where('teacher_id', $userId)->whereNotNull('file_path')->pluck('file_path')->all();
        $checks = DB::table('teacher_checks')->where('teacher_id', $userId)->update(['reference' => null, 'file_path' => null, 'file_name' => null, 'updated_at' => now()]);
        if ($scans !== []) {
            DB::afterCommit(fn () => Storage::disk(TeacherCheck::DISK)->delete($scans));
        }

        return ['profile' => $profiles, 'guardian_links' => $links, 'teacher_checks' => $checks];
    }
}
