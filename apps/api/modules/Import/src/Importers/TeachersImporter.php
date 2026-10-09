<?php

namespace Modules\Import\Importers;

use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
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
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Contact\Phone;

/**
 * Teachers from a sheet: an account (found by email or number, else made)
 * and their profile: gender, the subjects they teach (a subject the academy
 * does not have yet is added), the languages they explain in, trials, and
 * their weekly cap. A teacher already here gets their profile updated.
 */
final class TeachersImporter implements Importer
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly PeopleApi $people,
        private readonly CatalogApi $catalog,
        private readonly Directory $directory,
    ) {}

    public function kind(): string
    {
        return 'teachers';
    }

    public function fields(): array
    {
        return [
            new Field('name', true, ['الاسم', 'اسم المدرس', 'المدرس', 'الاسم بالكامل', 'full name', 'teacher', 'teacher name']),
            new Field('email', false, ['الإيميل', 'الايميل', 'البريد', 'البريد الإلكتروني', 'e-mail', 'mail']),
            new Field('phone', false, ['الموبايل', 'الجوال', 'رقم الموبايل', 'رقم الجوال', 'واتساب', 'الواتساب', 'رقم الواتساب', 'الهاتف', 'التليفون', 'mobile', 'whatsapp', 'phone number']),
            new Field('gender', true, ['النوع', 'الجنس', 'sex']),
            new Field('timezone', false, ['التوقيت', 'المنطقة الزمنية', 'البلد', 'الدولة', 'المدينة', 'time zone', 'country', 'city']),
            new Field('subjects', true, ['المواد', 'المادة', 'بيدرس', 'subject']),
            new Field('languages', false, ['لغة الشرح', 'اللغات', 'اللغة', 'language']),
            new Field('trials', false, ['تجريبي', 'حصص تجريبية', 'بيدي تجريبي', 'accepts trials', 'trial lessons']),
            new Field('max_hours', false, ['أقصى ساعات', 'الساعات في الأسبوع', 'ساعات الأسبوع', 'max hours', 'hours per week', 'weekly hours']),
            new Field('ages', false, ['الأعمار', 'الفئة العمرية', 'الفئات العمرية', 'بيدرس لمين', 'ages', 'age groups']),
            new Field('max_daily', false, ['أقصى حصص في اليوم', 'حصص اليوم', 'الحصص في اليوم', 'max lessons a day', 'lessons per day', 'daily lessons']),
        ];
    }

    public function import(array $row, ImportContext $context): RowOutcome
    {
        $e = 'import::rows.errors.';
        $email = $row['email'] !== '' ? $row['email'] : null;
        $phone = $row['phone'] !== '' ? Phone::normalize($row['phone'], $context->callingCode) : null;
        if ($row['name'] === '') {
            throw ValidationException::withMessages(['name' => __($e.'name')]);
        }
        if ($row['phone'] !== '' && $phone === null) {
            throw ValidationException::withMessages(['phone' => __($e.'phone', ['value' => $row['phone']])]);
        }
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => __($e.'email', ['value' => $email])]);
        }
        if ($email === null && $phone === null) {
            throw ValidationException::withMessages(['email' => __($e.'contact')]);
        }
        $gender = Cells::gender($row['gender']) ?? throw ValidationException::withMessages(['gender' => __($e.'gender', ['value' => $row['gender']])]);
        $timezone = $row['timezone'] === '' ? $context->timezone : (Places::timezone($row['timezone']) ?? throw ValidationException::withMessages(['timezone' => __($e.'timezone', ['value' => $row['timezone']])]));
        $trials = Cells::yesNo($row['trials'], true) ?? throw ValidationException::withMessages(['trials' => __($e.'yes_no', ['value' => $row['trials']])]);
        $maxHours = $row['max_hours'] === '' ? null : (Cells::integer($row['max_hours']) ?? throw ValidationException::withMessages(['max_hours' => __($e.'number', ['value' => $row['max_hours']])]));
        $languages = $this->languages($row['languages']);
        $ages = $row['ages'] === '' ? null : $this->ages($row['ages']);
        $maxDaily = $row['max_daily'] === '' ? null : (Cells::integer($row['max_daily']) ?? throw ValidationException::withMessages(['max_daily' => __($e.'number', ['value' => $row['max_daily']])]));

        $notes = [];
        $subjects = [];
        foreach (Text::list($row['subjects']) as $name) {
            $id = $this->directory->subject($name);
            if ($id === null) {
                $id = $this->catalog->createSubject($name, $name);
                $notes[] = __('import::rows.notes.new_subject', ['name' => $name]);
            }
            $subjects[] = $id;
        }
        if ($subjects === []) {
            throw ValidationException::withMessages(['subjects' => __($e.'subjects')]);
        }

        $existing = $this->directory->byContact($email, $phone);
        if ($existing !== null && ! in_array('teacher', $existing->roles, true)) {
            throw ValidationException::withMessages(['email' => __($e.'taken', ['name' => $existing->name])]);
        }
        $id = $existing?->id ?? $this->identity->createUser(new NewUser(
            name: $row['name'], roles: [Role::Teacher], email: $email, phoneE164: $phone, locale: $context->locale, timezone: $timezone,
        ));
        $this->people->saveTeacherProfile(new TeacherProfileData($id, $gender, $languages, array_values(array_unique($subjects)), null, true, $trials,
            $maxHours === null ? null : $maxHours * 60, $ages, $maxDaily));

        $names = implode('، ', Text::list($row['subjects']));
        array_unshift($notes, __($existing ? 'import::rows.notes.teacher_updated' : 'import::rows.notes.new_teacher', ['subjects' => $names]));

        return new RowOutcome($existing ? 'updated' : 'created', $notes, ['teacher' => $id], $existing ? [] : [$id]);
    }

    /** @return list<string> the ages they teach (People's AgeGroup values) */
    private function ages(string $text): array
    {
        $groups = [];
        foreach (Text::list(str_replace([' و ', ' and '], ',', $text)) as $a) {
            $groups[] = match (Text::key($a)) {
                'اطفال', 'طفل', 'ابتدائي', 'kids', 'children', 'child' => 'kids',
                'اعدادي', 'متوسط', 'middle', 'middleschool' => 'middle',
                'ثانوي', 'teen', 'teens', 'secondary' => 'teen',
                'كبار', 'بالغين', 'adults', 'adult' => 'adults',
                default => throw ValidationException::withMessages(['ages' => __('import::rows.errors.age_group', ['value' => $a])]),
            };
        }

        return array_values(array_unique($groups));
    }

    /** @return list<string> ISO codes; Arabic when empty */
    private function languages(string $text): array
    {
        $codes = [];
        foreach (Text::list(str_replace([' و ', ' and '], ',', $text)) as $l) {
            $codes[] = match (Text::key($l)) {
                'عربي', 'العربيه', 'عربيه', 'arabic', 'ar' => 'ar',
                'انجليزي', 'الانجليزيه', 'انجليزيه', 'english', 'en' => 'en',
                'فرنساوي', 'الفرنسيه', 'french', 'fr' => 'fr',
                'تركي', 'التركيه', 'turkish', 'tr' => 'tr',
                'اردو', 'urdu', 'ur' => 'ur',
                'ملايو', 'malay', 'ms' => 'ms',
                default => throw ValidationException::withMessages(['languages' => __('import::rows.errors.language', ['value' => $l])]),
            };
        }

        return array_values(array_unique($codes)) ?: ['ar'];
    }
}
