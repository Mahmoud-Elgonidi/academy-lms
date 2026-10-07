<?php

namespace Modules\Learning\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Models\Cheer;

/**
 * The game layer in the demo: this week's and last week's challenges come
 * from the lessons and homework the other seeders made. On top, Yousef
 * (kids) already has two stickers in his album from earlier weeks (his
 * chest from last week is waiting to be opened), and both he and Ziad have
 * a cheer from their guardian waiting to be seen.
 */
final class DemoChallengesSeeder extends Seeder
{
    public function run(): void
    {
        $id = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $guardianOf = fn (?string $student) => $student === null ? null : DB::table('guardian_student')->where('student_id', $student)->value('guardian_id');
        $now = CarbonImmutable::now();

        $yousef = $id('yousef@demo.test');
        if ($yousef !== null) {
            $week = $now->setTimezone((string) (DB::table('users')->where('id', $yousef)->value('timezone') ?: 'UTC'))
                ->startOfWeek(ChallengeCatalog::WEEK_STARTS)->startOfDay();
            foreach ([3, 2] as $i => $weeksAgo) {
                $period = $week->subWeeks($weeksAgo);
                ChallengeClaim::query()->create([
                    'student_id' => $yousef, 'challenge' => 'chest', 'period' => $period->toDateString(),
                    'points' => (int) ChallengeCatalog::reward('kids', 'chest'), 'sticker' => ChallengeCatalog::sticker($i),
                    'claimed_at' => $period->addDays(6)->setTime(19, 0),
                ]);
            }
        }

        foreach ([['yousef@demo.test', 'clap', 3], ['ziad@demo.test', 'fire', 20]] as [$email, $kind, $hoursAgo]) {
            $student = $id($email);
            $guardian = $guardianOf($student);
            if ($student !== null && $guardian !== null) {
                Cheer::query()->create(['student_id' => $student, 'from_id' => $guardian, 'kind' => $kind, 'created_at' => $now->subHours($hoursAgo)]);
            }
        }
    }
}
