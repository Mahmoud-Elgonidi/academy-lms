<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Holidays\DeclareHoliday;
use Modules\Scheduling\Actions\SubstituteTeacher;
use Modules\Scheduling\Actions\TimeOff\DecideTimeOff;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\SubstituteCandidates;

/**
 * Time off in the demo: Nour (Yousef's teacher) is off for her next two
 * teaching days, approved, one lesson already with a substitute and the
 * rest waiting for cover; Mona asked for two days next week and waits for
 * an answer; and a mid-term break closes the academy for a day two to three
 * weeks ahead, its lessons cancelled with their minutes back.
 */
final class DemoTimeOffSeeder extends Seeder
{
    public function run(RequestTimeOff $request, DecideTimeOff $decide, SubstituteCandidates $candidates, SubstituteTeacher $substitute, DeclareHoliday $holiday): void
    {
        $id = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        [$owner, $nour, $mona] = [$id('owner@demo.test'), $id('nour@demo.test'), $id('mona@demo.test')];
        if (! $owner || ! $nour) {
            return;
        }

        // Nour: from her next lesson after tomorrow, over her next two teaching days.
        $tz = $request->timezone($nour);
        $lessons = Session::query()->where('teacher_id', $nour)->where('status', 'scheduled')
            ->where('starts_at', '>', CarbonImmutable::now()->addDay()->endOfDay())->orderBy('starts_at')->limit(12)->get();
        $days = $lessons->map(fn (Session $s) => $s->starts_at->setTimezone($tz)->toDateString())->unique()->values();
        $first = $days[0] ?? CarbonImmutable::now($tz)->addDays(2)->toDateString();
        $last = $days[1] ?? $first;
        if (CarbonImmutable::parse($first)->diffInDays(CarbonImmutable::parse($last)) > 6) {
            $last = $first;
        }
        $leave = $request->handle($nour, $first, $last, null, null, 'family', 'زواج أختي', $nour);
        $decide->handle($leave, true, 'نتمنى لكم الخير. سندبّر بديلًا للحصص.', $owner);

        // One lesson given to the best substitute; the others are left for the cover screen.
        $open = $lessons->filter(fn (Session $s) => $leave->covers($s->starts_at, $s->ends_at))->values();
        foreach ($open->take(1) as $lesson) {
            foreach ($candidates->forSessions([$lesson])[$lesson->id] ?? [] as $candidate) {
                try {
                    $substitute->handle($lesson, $candidate['id'], $owner);
                    break;
                } catch (ValidationException) {
                    continue;
                }
            }
        }

        // Mona asks for two days next week.
        if ($mona) {
            $monaTz = $request->timezone($mona);
            $from = CarbonImmutable::now($monaTz)->addDays(8);
            $request->handle($mona, $from->toDateString(), $from->addDay()->toDateString(), null, null, 'vacation', 'سفر مع العائلة', $mona);
        }

        // A mid-term break: the busiest day two to three weeks ahead on the academy's clock.
        $day = collect(range(14, 21))
            ->map(fn (int $n) => CarbonImmutable::now($holiday->timezone())->addDays($n)->toDateString())
            ->sortByDesc(fn (string $d) => $holiday->affected($d, $d)->count())
            ->first();
        $holiday->handle('إجازة منتصف الترم', $day, $day, $owner);
    }
}
