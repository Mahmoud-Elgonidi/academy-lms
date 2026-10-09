<?php

namespace Modules\People\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\People\Actions\SaveStudentDetails;
use Modules\People\Actions\SaveTeacherProfile;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Models\TeacherProfile;

/**
 * The rest of the demo people's profiles: each teacher's ages, daily cap,
 * and the levels and categories of their subjects; each student's gender,
 * country, grade and who should teach them. Last in the demo, so the caps
 * do not stand in the way of the bookings made before. After DemoCatalogSeeder.
 */
final class DemoPeopleSeeder extends Seeder
{
    public function run(SaveTeacherProfile $teachers, SaveStudentDetails $students): void
    {
        $user = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $subject = fn (string $en) => DB::table('subjects')->where('name_en', $en)->value('id');
        $level = fn (string $subjectEn, string $en) => DB::table('subject_levels')->where('subject_id', $subject($subjectEn))->where('name_en', $en)->value('id');
        $category = fn (string $subjectEn, string $en) => DB::table('subject_categories')->where('subject_id', $subject($subjectEn))->where('name_en', $en)->value('id');
        $teaches = fn (string $s, ?string $from, ?string $to, array $categories = []) => new TeacherSubjectData(
            $subject($s), $from ? $level($s, $from) : null, $to ? $level($s, $to) : null, array_map(fn ($c) => $category($s, $c), $categories),
        );

        $profiles = [
            'mahmoud' => [['kids', 'middle'], 6, [$teaches('Quran memorisation', 'Noorani Qaida', '10 juz', ['New memorisation', 'Revision']), $teaches('Tajweed', 'Beginner', 'Intermediate')]],
            'nour' => [[], 8, [$teaches('Quran memorisation', null, null), $teaches('Tajweed', null, null)]],
            'sara' => [['teen', 'adults'], 6, [$teaches('Tajweed', 'Intermediate', null, ['Hafs', 'Al-Jazariyya']), $teaches('Quran memorisation', '5 juz', null, ['Revision', 'Ijazah'])]],
            'khaled' => [['middle', 'teen', 'adults'], 7, [$teaches('Arabic', 'Foundations', 'Level 3', ['Egyptian curriculum', 'Saudi curriculum']), $teaches('Arabic grammar', 'Al-Ajurrumiyya', 'Qatr al-Nada')]],
            'ahmed' => [['kids', 'middle', 'teen'], 8, [$teaches('English', 'A1', 'B2', ['Conversation', 'British curriculum', 'American curriculum']), $teaches('Arabic', 'Foundations', 'Level 1', ['For non-native speakers'])]],
            'mona' => [['kids'], 5, [$teaches('Arabic', 'Level 1', 'Level 3', ['Saudi curriculum']), $teaches('Quran memorisation', 'Noorani Qaida', 'Juz Tabarak', ['New memorisation'])]],
        ];
        foreach ($profiles as $key => [$ages, $daily, $subjects]) {
            $profile = TeacherProfile::query()->findOrFail($user($key));
            $teachers->handle(new TeacherProfileData($profile->user_id, $profile->gender, $profile->languages, [], $profile->dialect,
                $profile->accepts_new, $profile->accepts_trials, $profile->max_weekly_min, $ages, $daily, $subjects));
        }

        $countries = ['Asia/Qatar' => 'QA', 'Europe/London' => 'GB', 'Asia/Riyadh' => 'SA', 'Asia/Dubai' => 'AE', 'Asia/Kuwait' => 'KW', 'Africa/Cairo' => 'EG', 'Asia/Amman' => 'JO'];
        $preferences = ['maryam' => 'female_only', 'layan' => 'female_only', 'salma' => 'prefer_female', 'adam' => 'male_only'];
        $genders = ['omar' => 'male', 'yousef' => 'male', 'maryam' => 'female', 'hamza' => 'male', 'adam' => 'male', 'layan' => 'female', 'salma' => 'female', 'zain' => 'male', 'ziad' => 'male', 'rana' => 'female'];
        foreach ($genders as $key => $gender) {
            $student = DB::table('users as u')->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
                ->where('u.email', "{$key}@demo.test")->first(['u.id', 'u.timezone', 'p.birth_date']);
            if ($student === null) {
                continue;
            }
            $age = $student->birth_date ? (int) CarbonImmutable::parse($student->birth_date)->diffInYears(CarbonImmutable::now()) : null;
            $grade = match (true) {
                $age === null => null,
                $age >= 18 => 'university',
                $age <= 5 => 'kg2',
                default => 'g'.min(12, $age - 5),
            };
            $students->handle($student->id, new StudentDetails($gender, $countries[$student->timezone] ?? null, $grade, $preferences[$key] ?? null));
        }
    }
}
