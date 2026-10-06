<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Actions\Shifts\CommentOnShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\SaveShiftProposal;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

/**
 * Teacher profiles, the schedules in force, weekly bookings for the coming
 * weeks, and three shift proposals for the review screen: Sara reorganising
 * her week around university (three booked students affected), Ahmed adding
 * Friday (no conflicts) and Mona answering a counter-offer.
 * Runs after DemoLiveBoardSeeder, inside the demo academy.
 */
final class DemoShiftsSeeder extends Seeder
{
    public function run(PeopleApi $people, ScheduleSession $schedule, SaveShiftProposal $save, CounterShiftProposal $counter, CommentOnShiftProposal $comment): void
    {
        $user = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $subject = fn (string $en) => DB::table('subjects')->where('name_en', $en)->value('id');
        $admin = $user('owner');

        $profiles = [
            'mahmoud' => ['male', ['ar'], 'مصرية', ['Quran memorisation', 'Tajweed']],
            'nour' => ['female', ['ar', 'en'], 'مصرية', ['Quran memorisation', 'Tajweed']],
            'sara' => ['female', ['ar'], 'مصرية', ['Tajweed', 'Quran memorisation']],
            'khaled' => ['male', ['ar', 'en'], 'مصرية', ['Arabic', 'Arabic grammar']],
            'ahmed' => ['male', ['en', 'ar'], null, ['English', 'Arabic']],
            'mona' => ['female', ['ar', 'en'], 'شامية', ['Arabic', 'Quran memorisation']],
        ];
        foreach ($profiles as $key => [$gender, $languages, $dialect, $subjects]) {
            $people->saveTeacherProfile(new TeacherProfileData($user($key), $gender, $languages, array_map($subject, $subjects), $dialect, maxWeeklyMinutes: 30 * 60));
        }

        // Schedules in force since a month ago (history, so written directly).
        $weeks = [
            'mahmoud' => [[6, '16:00', '22:00'], [1, '16:00', '22:00'], [3, '16:00', '22:00']],
            'nour' => [[6, '17:00', '22:00'], [2, '17:00', '22:00'], [4, '18:00', '21:00']],
            'sara' => [[6, '17:00', '21:00'], [7, '18:00', '22:00'], [2, '16:00', '21:00']],
            'khaled' => [[7, '15:00', '21:00'], [1, '15:00', '21:00'], [2, '15:00', '21:00'], [3, '15:00', '21:00'], [4, '15:00', '21:00']],
            'ahmed' => [[1, '17:00', '23:00'], [2, '17:00', '23:00'], [3, '17:00', '23:00'], [4, '17:00', '23:00']],
            'mona' => [[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '20:00', '23:00']],
        ];
        $since = CarbonImmutable::now()->subMonth()->toDateString();
        foreach ($weeks as $key => $blocks) {
            $teacherId = $user($key);
            $tz = DB::table('users')->where('id', $teacherId)->value('timezone');
            $version = ShiftSchedule::query()->create([
                'teacher_id' => $teacherId, 'version' => 1, 'timezone' => $tz, 'status' => 'active',
                'effective' => "[{$since},)", 'approved_by' => $admin,
            ]);
            foreach (self::week($blocks, $tz)->blocks as $block) {
                $version->rules()->create(['teacher_id' => $teacherId, 'weekday' => $block->weekday, 'local_start' => $block->start(), 'duration_min' => $block->durationMin]);
            }
        }

        // Weekly bookings for the next eight weeks, on the teacher's clock.
        $series = [
            ['sara', 'maryam', 'Tajweed', 2, '17:00', 45],
            ['sara', 'adam', 'Arabic', 2, '19:00', 45],
            ['sara', 'hamza', 'Quran memorisation', 7, '20:00', 30],
            ['nour', 'yousef', 'Quran memorisation', 6, '18:00', 30],
            ['mahmoud', 'omar', 'Tajweed', 1, '19:00', 45],
            ['khaled', 'ziad', 'Arabic grammar', 3, '18:00', 45],
            ['mona', 'salma', 'Arabic', 7, '19:00', 45],
            ['ahmed', 'layan', 'English', 2, '20:00', 30],
        ];
        foreach ($series as [$teacher, $student, $subjectName, $weekday, $time, $minutes]) {
            $teacherId = $user($teacher);
            $tz = DB::table('users')->where('id', $teacherId)->value('timezone');
            $day = CarbonImmutable::now($tz)->addDay()->startOfDay();
            $day = $day->addDays(($weekday - $day->dayOfWeekIso + 7) % 7);
            for ($i = 0; $i < 8; $i++, $day = $day->addWeek()) {
                $schedule->handle(new NewSession($teacherId, $user($student), $subject($subjectName), CarbonImmutable::parse($day->toDateString().' '.$time, $tz), $minutes, $tz), $admin);
            }
        }

        // Sara: university on Tuesday afternoons; moves Tuesday to the evening and adds Wednesday.
        $saraTz = 'Africa/Cairo';
        $proposal = $save->handle(
            $user('sara'),
            self::week([[6, '17:00', '22:00'], [7, '18:00', '20:00'], [2, '21:00', '23:00'], [3, '17:00', '21:00']], $saraTz),
            CarbonImmutable::now($saraTz)->addDays(19)->startOfDay(),
            'الترم ده عندي محاضرات في الجامعة يوم التلات من 4 لـ 9، فشلت التلات العصر وزوّدت بالليل، وضفت يوم الأربع.',
        );
        $comment->handle($proposal, $admin, 'admin', 'تمام يا أستاذة. عندنا مريم وآدم يوم التلات العصر. ينفع مريم تكمّل معاكي التلات 21:00؟');
        $comment->handle($proposal, $user('sara'), 'teacher', 'مريم ينفع 21:00. آدم صعب عشان فرق التوقيت، يتنقل أحسن.');
        DB::table('shift_proposals')->where('id', $proposal->id)->update(['submitted_at' => CarbonImmutable::now()->subHours(5)]);

        // Ahmed: adds Friday evening only, so it can start tomorrow.
        $save->handle(
            $user('ahmed'),
            self::week([[1, '17:00', '23:00'], [2, '17:00', '23:00'], [3, '17:00', '23:00'], [4, '17:00', '23:00'], [5, '18:00', '22:00']], 'Africa/Cairo'),
            CarbonImmutable::now('Africa/Cairo')->addDays(2)->startOfDay(),
            'ممكن أزوّد الجمعة بالليل عشان الطلب على الإنجليزي.',
        );

        // Mona asked for Saturday mornings; the admins countered with Saturday evening.
        $mona = $user('mona');
        $save->handle($mona, self::week([[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '10:00', '14:00']], 'Asia/Amman'), CarbonImmutable::now('Asia/Amman')->addDays(20)->startOfDay(), 'السبت الصبح أنسب لي من الليل.');
        $counter->handle($mona, self::week([[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '10:00', '13:00'], [6, '20:00', '23:00']], 'Asia/Amman'), CarbonImmutable::now('Asia/Amman')->addDays(20)->startOfDay(), 'نخلي السبت الصبح لحد 1 ونحافظ على السبت بالليل عشان سلمى وطلاب أمريكا.', $admin);
    }

    /** @param  list<array{0: int, 1: string, 2: string}>  $blocks */
    private static function week(array $blocks, string $tz): WeeklyShifts
    {
        return WeeklyShifts::fromArray(array_map(fn ($b) => ['weekday' => $b[0], 'start' => $b[1], 'end' => $b[2]], $blocks), $tz);
    }
}
