<?php

namespace Modules\Games\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Games\Actions\ChooseBuddy;
use Modules\Games\Actions\FinishPlay;
use Modules\Games\Actions\NameCreature;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Domain\Round;
use Modules\Games\Queries\PracticePool;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Games\CatalogGame;

/**
 * Rounds the demo students played in the last week, through the real
 * actions (docs/architecture/02, section 18): quests from their last lesson,
 * reviews and where they stand, and free play, each with some first tries
 * wrong, so stars, a hatched egg and the practice on their roadmap are
 * there. Homework rounds are left for the demo to play. Yousef named his
 * first creature and keeps it as his buddy; his guardian keeps him to 20
 * minutes a day. Runs after the reports and the practice items.
 */
final class DemoGamesSeeder extends Seeder
{
    /** [student, days ago, hour (UTC), quest (or null: free play), share right first time, minutes] */
    private const ROUNDS = [
        ['yousef', 6, 15, 'last_lesson', 1.0, 4],
        ['yousef', 5, 16, 'review', 0.7, 6],
        ['yousef', 4, 15, 'current', 0.9, 5],
        ['yousef', 3, 16, null, 1.0, 4],
        ['yousef', 2, 15, 'last_lesson', 0.8, 5],
        ['yousef', 1, 16, 'current', 1.0, 3],
        ['ziad', 5, 18, 'last_lesson', 0.8, 6],
        ['ziad', 3, 19, null, 0.6, 7],
        ['ziad', 1, 18, 'current', 1.0, 4],
        ['maryam', 4, 19, 'review', 0.9, 5],
        ['maryam', 2, 19, null, 0.7, 6],
    ];

    public function run(StartPlay $start, FinishPlay $finish, SaveGameLimit $limits, PracticePool $pool, PeopleApi $people, NameCreature $name, ChooseBuddy $buddy): void
    {
        $realNow = CarbonImmutable::now();
        $id = fn (string $key) => (string) DB::table('users')->where('email', "{$key}@demo.test")->value('id');

        foreach (self::ROUNDS as $n => [$key, $daysAgo, $hour, $quest, $share, $minutes]) {
            $student = $id($key);
            if ($student === '') {
                continue;
            }
            $at = $realNow->subDays($daysAgo)->setTime($hour, 7 * ($n % 5));
            CarbonImmutable::setTestNow($at);

            $games = $pool->games($people->studentProfile($student)->stage->value);
            $focus = $quest === null ? null : collect($pool->quests($student, $pool->focus($student), $games))->first(fn ($q) => $q['focus']->reason === $quest);
            $all = $pool->all($pool->focus($student));
            $game = $focus['game'] ?? collect($games)->sortByDesc(fn (CatalogGame $g) => count(Round::pick($g, $all) ?? []))->first(fn (CatalogGame $g) => Round::pick($g, $all) !== null);
            if ($game === null) {
                continue;
            }
            $round = $start->handle($student, $student, $game->id, $focus === null ? null : $focus['focus']->key());

            // The first tries: this share right, the rest wrong and then right.
            $items = array_column($round['round'], 'id');
            $right = (int) round($share * count($items));
            $answers = [];
            foreach ($items as $i => $item) {
                if ($i >= $right) {
                    $answers[] = ['item' => $item, 'correct' => false];
                }
                $answers[] = ['item' => $item, 'correct' => true];
            }
            CarbonImmutable::setTestNow($at->addMinutes($minutes));
            $finish->handle($round['play'], $student, $answers);
        }
        CarbonImmutable::setTestNow($realNow);

        // Yousef named his first creature and keeps it with him.
        $yousef = $id('yousef');
        $first = $yousef === '' ? null : DB::table('creature_finds')->where('student_id', $yousef)->orderBy('found_at')->value('creature_key');
        if ($first !== null) {
            $name->handle($yousef, $first, 'سمسم');
            $buddy->handle($yousef, $first);
        }

        // Yousef's guardian keeps him to 20 minutes a day.
        $guardian = $yousef === '' ? null : DB::table('guardian_student')->where('student_id', $yousef)->value('guardian_id');
        if ($guardian !== null) {
            $limits->handle($yousef, (string) $guardian, true, 20);
        }
        CarbonImmutable::setTestNow();
    }
}
