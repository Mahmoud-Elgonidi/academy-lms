<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

/**
 * Teacher profiles and the schedules in force (for half a year, so the demo
 * has a past). The bookings come next (DemoRevenueSeeder's months, then
 * DemoEnrollmentsSeeder), and the shift proposals after them
 * (DemoShiftProposalsSeeder). Runs after DemoLiveBoardSeeder, inside the demo academy.
 */
final class DemoShiftsSeeder extends Seeder
{
    public function run(PeopleApi $people): void
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

        // Schedules in force since half a year ago (history, so written directly).
        $weeks = [
            'mahmoud' => [[6, '16:00', '22:00'], [1, '16:00', '22:00'], [3, '16:00', '22:00']],
            'nour' => [[6, '17:00', '22:00'], [2, '17:00', '22:00'], [4, '18:00', '21:00']],
            'sara' => [[6, '17:00', '21:00'], [7, '18:00', '22:00'], [2, '16:00', '21:00']],
            'khaled' => [[7, '15:00', '21:00'], [1, '15:00', '21:00'], [2, '15:00', '21:00'], [3, '15:00', '21:00'], [4, '15:00', '21:00']],
            'ahmed' => [[1, '17:00', '23:00'], [2, '17:00', '23:00'], [3, '17:00', '23:00'], [4, '17:00', '23:00']],
            'mona' => [[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '20:00', '23:00']],
        ];
        $since = CarbonImmutable::now()->subMonths(6)->toDateString();
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
    }

    /** @param  list<array{0: int, 1: string, 2: string}>  $blocks */
    private static function week(array $blocks, string $tz): WeeklyShifts
    {
        return WeeklyShifts::fromArray(array_map(fn ($b) => ['weekday' => $b[0], 'start' => $b[1], 'end' => $b[2]], $blocks), $tz);
    }
}
