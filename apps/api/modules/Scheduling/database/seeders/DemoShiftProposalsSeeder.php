<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Actions\Shifts\CommentOnShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\SaveShiftProposal;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

/**
 * Three shift proposals for the review screen: Sara reorganising her week
 * around university (three booked students affected), Ahmed adding Friday
 * (no conflicts) and Mona answering a counter-offer. Runs after the
 * enrolments, so the review shows whom a change would touch.
 */
final class DemoShiftProposalsSeeder extends Seeder
{
    public function run(SaveShiftProposal $save, CounterShiftProposal $counter, CommentOnShiftProposal $comment): void
    {
        $user = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $admin = $user('owner');

        // Sara: university on Tuesday afternoons; moves Tuesday to the evening and adds Wednesday.
        $saraTz = 'Africa/Cairo';
        $proposal = $save->handle(
            $user('sara'),
            self::week([[6, '17:00', '22:00'], [7, '18:00', '20:00'], [2, '21:00', '23:00'], [3, '17:00', '21:00']], $saraTz),
            CarbonImmutable::now($saraTz)->addDays(19)->startOfDay(),
            'لديّ هذا الفصل الدراسي محاضرات في الجامعة يوم الثلاثاء من 4 إلى 9، فحذفت عصر الثلاثاء وزدت في المساء، وأضفت يوم الأربعاء.',
        );
        $comment->handle($proposal, $admin, 'admin', 'حسنًا يا أستاذة. لدينا مريم وآدم يوم الثلاثاء عصرًا. هل يمكن أن تكمل مريم معكِ الثلاثاء 21:00؟');
        $comment->handle($proposal, $user('sara'), 'teacher', 'مريم يناسبها 21:00. آدم صعب بسبب فرق التوقيت، فالأفضل نقله.');
        DB::table('shift_proposals')->where('id', $proposal->id)->update(['submitted_at' => CarbonImmutable::now()->subHours(5)]);

        // Ahmed: adds Friday evening only, so it can start tomorrow.
        $save->handle(
            $user('ahmed'),
            self::week([[1, '17:00', '23:00'], [2, '17:00', '23:00'], [3, '17:00', '23:00'], [4, '17:00', '23:00'], [5, '18:00', '22:00']], 'Africa/Cairo'),
            CarbonImmutable::now('Africa/Cairo')->addDays(2)->startOfDay(),
            'يمكنني إضافة مساء الجمعة بسبب الطلب على الإنجليزية.',
        );

        // Mona asked for Saturday mornings; the admins countered with Saturday evening.
        $mona = $user('mona');
        $save->handle($mona, self::week([[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '10:00', '14:00']], 'Asia/Amman'), CarbonImmutable::now('Asia/Amman')->addDays(20)->startOfDay(), 'صباح السبت أنسب لي من المساء.');
        $counter->handle($mona, self::week([[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '10:00', '13:00'], [6, '20:00', '23:00']], 'Asia/Amman'), CarbonImmutable::now('Asia/Amman')->addDays(20)->startOfDay(), 'نُبقي صباح السبت حتى 1 ونحافظ على مساء السبت من أجل سلمى وطلاب أمريكا.', $admin);
    }

    /** @param  list<array{0: int, 1: string, 2: string}>  $blocks */
    private static function week(array $blocks, string $tz): WeeklyShifts
    {
        return WeeklyShifts::fromArray(array_map(fn ($b) => ['weekday' => $b[0], 'start' => $b[1], 'end' => $b[2]], $blocks), $tz);
    }
}
