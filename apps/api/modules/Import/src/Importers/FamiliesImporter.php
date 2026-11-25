<?php

namespace Modules\Import\Importers;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Import\Support\Cells;
use Modules\Import\Support\Directory;
use Modules\Import\Support\Field;
use Modules\Import\Support\ImportContext;
use Modules\Import\Support\Places;
use Modules\Import\Support\RowOutcome;
use Modules\Import\Support\Text;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Tenancy\AcademyAudience;

/**
 * Students and their guardians from a sheet, a student a row. A guardian is
 * found by number or email, else made, so brothers and sisters on several
 * rows share one parent. A child with no number or email of their own gets
 * an account under the parent (no sign-in of their own); an adult student
 * signs in with theirs. A student already here is left as they are. An
 * academy of adults only (docs/architecture/02, section 29) imports trainees
 * alone: no guardian columns, and every row needs the trainee's own email or
 * number.
 */
final class FamiliesImporter implements Importer
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly PeopleApi $people,
        private readonly Directory $directory,
        private readonly AcademyAudience $audience,
    ) {}

    public function kind(): string
    {
        return 'families';
    }

    public function fields(): array
    {
        if ($this->audience->adultsOnly()) {
            return [
                new Field('student_name', true, ['المتدرّب', 'اسم المتدرّب', 'المتدرب', 'اسم المتدرب', 'المشارك', 'اسم المشارك', 'الطالب', 'اسم الطالب', 'الموظف', 'اسم الموظف', 'trainee', 'trainee name', 'participant', 'employee', 'student', 'student name', 'name', 'الاسم']),
                new Field('student_email', false, ['بريد المتدرّب الإلكتروني', 'بريد المتدرب', 'إيميل المتدرب', 'trainee email', 'student email', 'email', 'البريد الإلكتروني', 'الإيميل', 'الايميل']),
                new Field('student_phone', false, ['هاتف المتدرّب', 'رقم المتدرب', 'جوال المتدرب', 'موبايل المتدرب', 'trainee phone', 'student phone', 'phone', 'mobile', 'whatsapp', 'الهاتف', 'الموبايل', 'الجوال', 'واتساب', 'رقم الواتساب']),
                new Field('gender', false, ['النوع', 'الجنس', 'gender', 'sex']),
                new Field('birth_date', false, ['تاريخ الميلاد', 'سنة الميلاد', 'الميلاد', 'birth date', 'birthday', 'date of birth', 'birth year', 'dob']),
                new Field('timezone', false, ['التوقيت', 'المنطقة الزمنية', 'البلد', 'الدولة', 'المدينة', 'time zone', 'country', 'city']),
                new Field('language', false, ['لغة الرسائل', 'اللغة', 'language']),
            ];
        }

        return [
            new Field('student_name', true, ['الطالب', 'اسم الطالب', 'الطالبة', 'اسم الطالبة', 'student', 'student name', 'name', 'الاسم']),
            new Field('student_email', false, ['إيميل الطالب', 'ايميل الطالب', 'بريد الطالب الإلكتروني', 'بريد الطالب', 'student email']),
            new Field('student_phone', false, ['موبايل الطالب', 'رقم الطالب', 'جوال الطالب', 'هاتف الطالب', 'student phone', 'student mobile']),
            new Field('gender', false, ['نوع الطالب', 'النوع', 'الجنس', 'ولد ولا بنت', 'gender', 'sex']),
            new Field('birth_date', false, ['تاريخ الميلاد', 'سنة الميلاد', 'الميلاد', 'birth date', 'birthday', 'date of birth', 'birth year', 'dob']),
            new Field('timezone', false, ['التوقيت', 'المنطقة الزمنية', 'البلد', 'الدولة', 'المدينة', 'time zone', 'country', 'city']),
            new Field('guardian_name', false, ['ولي الأمر', 'اسم ولي الأمر', 'الأب', 'الأم', 'guardian', 'parent', 'guardian name', 'parent name']),
            new Field('guardian_email', false, ['إيميل ولي الأمر', 'ايميل ولي الامر', 'بريد ولي الأمر الإلكتروني', 'بريد ولي الأمر', 'guardian email', 'parent email', 'email', 'الإيميل', 'الايميل']),
            new Field('guardian_phone', false, ['موبايل ولي الأمر', 'رقم ولي الأمر', 'واتساب ولي الأمر', 'جوال ولي الأمر', 'هاتف ولي الأمر', 'guardian phone', 'parent phone', 'phone', 'mobile', 'whatsapp', 'الموبايل', 'الجوال', 'واتساب', 'رقم الواتساب']),
            new Field('relationship', false, ['صلة القرابة', 'القرابة', 'relationship', 'relation']),
            new Field('language', false, ['لغة الرسائل', 'اللغة', 'language']),
        ];
    }

    public function import(array $row, ImportContext $context): RowOutcome
    {
        $e = 'import::rows.errors.';
        // An adults academy's sheet has no guardian columns.
        $row += ['guardian_name' => '', 'guardian_email' => '', 'guardian_phone' => '', 'relationship' => ''];
        if ($row['student_name'] === '') {
            throw ValidationException::withMessages(['student_name' => __($e.'name')]);
        }
        $phone = fn (string $field) => $row[$field] === '' ? null
            : (Phone::normalize($row[$field], $context->callingCode) ?? throw ValidationException::withMessages([$field => __($e.'phone', ['value' => $row[$field]])]));
        $email = fn (string $field) => $row[$field] === '' ? null
            : (filter_var($row[$field], FILTER_VALIDATE_EMAIL) ? $row[$field] : throw ValidationException::withMessages([$field => __($e.'email', ['value' => $row[$field]])]));
        [$studentEmail, $studentPhone, $guardianEmail, $guardianPhone] = [$email('student_email'), $phone('student_phone'), $email('guardian_email'), $phone('guardian_phone')];
        $timezone = $row['timezone'] === '' ? $context->timezone : (Places::timezone($row['timezone']) ?? throw ValidationException::withMessages(['timezone' => __($e.'timezone', ['value' => $row['timezone']])]));
        $gender = $row['gender'] === '' ? null : (Cells::gender($row['gender']) ?? throw ValidationException::withMessages(['gender' => __($e.'gender', ['value' => $row['gender']])]));
        $birth = $row['birth_date'] === '' ? null : (Cells::date($row['birth_date']) ?? throw ValidationException::withMessages(['birth_date' => __($e.'date', ['value' => $row['birth_date']])]));
        $locale = match (Text::key($row['language'])) {
            '' => $context->locale,
            'عربي', 'العربيه', 'arabic', 'ar' => 'ar',
            'انجليزي', 'الانجليزيه', 'english', 'en' => 'en',
            default => throw ValidationException::withMessages(['language' => __($e.'language', ['value' => $row['language']])]),
        };
        $hasGuardian = $row['guardian_name'] !== '' || $guardianEmail !== null || $guardianPhone !== null;
        if ($hasGuardian && ($guardianEmail === null && $guardianPhone === null)) {
            throw ValidationException::withMessages(['guardian_phone' => __($e.'guardian_contact')]);
        }
        if (! $hasGuardian && $studentEmail === null && $studentPhone === null) {
            throw ValidationException::withMessages(['student_phone' => __($e.'family_contact')]);
        }

        $notes = [];
        $new = [];

        // The guardian: found, or made.
        $guardianId = null;
        if ($hasGuardian) {
            $found = $this->directory->byContact($guardianEmail, $guardianPhone);
            if ($found !== null && ! in_array('guardian', $found->roles, true)) {
                throw ValidationException::withMessages(['guardian_phone' => __($e.'taken', ['name' => $found->name])]);
            }
            if ($found === null) {
                if ($row['guardian_name'] === '') {
                    throw ValidationException::withMessages(['guardian_name' => __($e.'guardian_name')]);
                }
                $guardianId = $this->identity->createUser(new NewUser(name: $row['guardian_name'], roles: [Role::Guardian], email: $guardianEmail, phoneE164: $guardianPhone, locale: $locale, timezone: $timezone));
                $new[] = $guardianId;
                $notes[] = __('import::rows.notes.new_guardian', ['name' => $row['guardian_name']]);
            } else {
                $guardianId = $found->id;
                $notes[] = __('import::rows.notes.guardian_found', ['name' => $found->name]);
            }
        }

        // The student: by their own contact, or by name under this guardian.
        $own = $studentEmail !== null || $studentPhone !== null;
        $student = $own ? $this->directory->byContact($studentEmail, $studentPhone) : null;
        if ($student !== null && ! in_array('student', $student->roles, true)) {
            throw ValidationException::withMessages(['student_phone' => __($e.'taken', ['name' => $student->name])]);
        }
        $studentId = $student?->id ?? ($guardianId ? $this->directory->childNamed($guardianId, $row['student_name']) : null);
        if ($studentId !== null) {
            if ($guardianId !== null && ! in_array($guardianId, $this->people->guardiansOf($studentId), true)) {
                $this->people->linkGuardian($guardianId, $studentId, $this->relationship($row['relationship']), $this->people->guardiansOf($studentId) === []);
            }

            return new RowOutcome('skipped', [__('import::rows.notes.student_exists'), ...$notes], ['student' => $studentId, 'guardian' => $guardianId], $new);
        }

        $studentId = $this->identity->createUser(new NewUser(
            name: $row['student_name'],
            roles: [Role::Student],
            email: $studentEmail,
            phoneE164: $studentPhone,
            username: $own ? null : 'learner.'.Str::lower(Str::random(12)),
            locale: $locale,
            timezone: $timezone,
            isManagedMinor: ! $own,
        ));
        if ($own) {
            $new[] = $studentId;
        }
        if ($guardianId !== null) {
            $this->people->linkGuardian($guardianId, $studentId, $this->relationship($row['relationship']), true);
        }
        if ($birth !== null) {
            $this->people->saveStudentProfile($studentId, $birth, null);
        }
        if ($gender !== null) {
            $this->people->saveStudentDetails($studentId, new StudentDetails(gender: $gender));
        }

        return new RowOutcome('created', [$own ? __('import::rows.notes.student_own') : __('import::rows.notes.student_child'), ...$notes], ['student' => $studentId, 'guardian' => $guardianId], $new);
    }

    private function relationship(string $text): string
    {
        return match (Text::key($text)) {
            'اب', 'الاب', 'والد', 'father', 'dad' => 'father',
            'ام', 'الام', 'والده', 'mother', 'mom', 'mum' => 'mother',
            'اخ', 'اخت', 'الاخ', 'الاخت', 'brother', 'sister', 'sibling' => 'sibling',
            'جد', 'جده', 'grandparent', 'grandfather', 'grandmother' => 'grandparent',
            '', 'ولي امر', 'parent' => 'parent',
            default => 'other',
        };
    }
}
