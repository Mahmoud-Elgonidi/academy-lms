<?php

namespace Modules\Evaluation\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\Actions\ExcludeLesson;
use Modules\Evaluation\Actions\RateLesson;
use Modules\People\Contracts\PeopleApi;

/**
 * Ratings in the demo, given the way families give them: on most of the
 * lessons that took place, a couple of hours after each, mostly 4 and 5
 * stars with a few words, one low rating with a comment about the
 * connection, and one teacher absence the academy took out of the numbers
 * (the video provider was down).
 */
final class DemoEvaluationSeeder extends Seeder
{
    public function run(RateLesson $rate, ExcludeLesson $exclude, PeopleApi $people): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');
        $lessons = DB::table('sessions as s')
            ->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', 'student'))
            ->where('s.status', 'completed')->where('s.starts_at', '>', CarbonImmutable::now()->subDays(60))
            ->orderBy('s.starts_at')->get(['s.id', 's.ends_at', 's.teacher_id', 'p.user_id as student_id']);

        $script = [[5, ['clear', 'patient'], null], [5, ['engaging'], 'أصبح ابني ينتظر الحصة من أول اليوم.'], [4, ['prepared'], null], null,
            [5, ['clear'], null], [4, [], null], [3, ['too_fast'], 'نحتاج إلى إبطاء الشرح قليلًا.'], [5, ['patient', 'engaging'], null], null, [4, ['clear'], null]];
        $low = false;
        foreach ($lessons as $i => $lesson) {
            $pick = $script[$i % count($script)];
            if ($pick === null) {
                continue;
            }
            [$stars, $tags, $comment] = $pick;
            if (! $low && $i >= 4) {
                [$stars, $tags, $comment, $low] = [2, ['connection'], 'كان الإنترنت ينقطع كثيرًا والصوت غير واضح.', true];
            }
            $rater = $people->guardiansOf($lesson->student_id)[0] ?? $lesson->student_id;
            CarbonImmutable::setTestNow(CarbonImmutable::parse($lesson->ends_at)->addHours(2));
            try {
                $rate->handle($lesson->id, $rater, $stars, $tags, $comment, $i % 3 === 0 ? 'link' : 'app');
            } catch (ValidationException) {
                continue;
            } finally {
                CarbonImmutable::setTestNow();
            }
        }

        $absence = DB::table('sessions')->where('status', 'teacher_absent')->orderByDesc('starts_at')->value('id');
        if ($absence && $owner) {
            $exclude->handle($absence, 'provider', 'كان مزوّد الفيديو متوقفًا في تلك الساعة، وحاول المعلّم الدخول.', $owner);
        }
    }
}
