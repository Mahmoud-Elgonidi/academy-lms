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
 * enrolments and the days DemoOpenHoursSeeder adds (which Sara's and Mona's
 * weeks keep), so the review shows whom a change would touch.
 * DemoOpenHoursSeeder tells Sara's and Mona's stories again on a demo whose
 * proposals came before those days.
 */
final class DemoShiftProposalsSeeder extends Seeder
{
    public const SARA_NOTE = 'لديّ هذا الفصل الدراسي محاضرات في الجامعة يوم الثلاثاء من 4 إلى 9، فحذفت عصر الثلاثاء وزدت في المساء، وأضفت يوم الأربعاء.';

    public const MONA_NOTE = 'صباح السبت أنسب لي من المساء.';

    public function __construct(
        private readonly SaveShiftProposal $save,
        private readonly CounterShiftProposal $counter,
        private readonly CommentOnShiftProposal $comment,
    ) {}

    public function run(): void
    {
        $this->sara();

        // Ahmed: adds Friday evening only, so it can start tomorrow.
        $this->save->handle(
            self::user('ahmed'),
            self::week([[1, '17:00', '23:00'], [2, '17:00', '23:00'], [3, '17:00', '23:00'], [4, '17:00', '23:00'], [5, '18:00', '22:00']], 'Africa/Cairo'),
            CarbonImmutable::now('Africa/Cairo')->addDays(2)->startOfDay(),
            'يمكنني إضافة مساء الجمعة بسبب الطلب على الإنجليزية.',
        );

        $this->mona();
    }

    /** Sara: university on Tuesday afternoons; moves Tuesday to the evening and adds Wednesday. */
    public function sara(): void
    {
        $saraTz = 'Africa/Cairo';
        $proposal = $this->save->handle(
            self::user('sara'),
            self::week([[6, '17:00', '22:00'], [7, '18:00', '20:00'], [2, '21:00', '23:00'], [3, '17:00', '21:00'], ...DemoOpenHoursSeeder::DAYS['sara']], $saraTz),
            CarbonImmutable::now($saraTz)->addDays(19)->startOfDay(),
            self::SARA_NOTE,
        );
        $this->comment->handle($proposal, self::user('owner'), 'admin', 'حسنًا يا أستاذة. لدينا مريم وآدم يوم الثلاثاء عصرًا. هل يمكن أن تكمل مريم معكِ الثلاثاء 21:00؟');
        $this->comment->handle($proposal, self::user('sara'), 'teacher', 'مريم يناسبها 21:00. آدم صعب بسبب فرق التوقيت، فالأفضل نقله.');
        DB::table('shift_proposals')->where('id', $proposal->id)->update(['submitted_at' => CarbonImmutable::now()->subHours(5)]);
    }

    /** Mona asked for Saturday mornings; the admins countered with Saturday evening. */
    public function mona(): void
    {
        $mona = self::user('mona');
        $days = DemoOpenHoursSeeder::DAYS['mona'];
        $this->save->handle($mona, self::week([[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '10:00', '14:00'], ...$days], 'Asia/Amman'), CarbonImmutable::now('Asia/Amman')->addDays(20)->startOfDay(), self::MONA_NOTE);
        $this->counter->handle($mona, self::week([[7, '17:00', '22:00'], [4, '17:00', '22:00'], [6, '10:00', '13:00'], [6, '20:00', '23:00'], ...$days], 'Asia/Amman'), CarbonImmutable::now('Asia/Amman')->addDays(20)->startOfDay(), 'نُبقي صباح السبت حتى 1 ونحافظ على مساء السبت من أجل سلمى وطلاب أمريكا.', self::user('owner'));
    }

    private static function user(string $key): string
    {
        return (string) DB::table('users')->where('email', "{$key}@demo.test")->value('id');
    }

    /** @param  list<array{0: int, 1: string, 2: string}>  $blocks */
    private static function week(array $blocks, string $tz): WeeklyShifts
    {
        return WeeklyShifts::fromArray(array_map(fn ($b) => ['weekday' => $b[0], 'start' => $b[1], 'end' => $b[2]], $blocks), $tz);
    }
}
