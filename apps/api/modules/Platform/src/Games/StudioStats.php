<?php

namespace Modules\Platform\Games;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The studio's numbers (the approved design, Studio and Studio-Game), across
 * every academy on the platform's connection: who played this week, how many
 * rounds, how many were finished, the eggs hatched; and for one game, its
 * rounds, how they end, and how often each question in a round is right first
 * time. Counts only: no learner, academy or answer leaves here.
 */
final class StudioStats
{
    /** How many of a game's latest rounds are read for its question-by-question numbers. */
    public const SAMPLE = 500;

    /** @return array{players: int, academies: int, rounds: int, per_player: float, finished: int, eggs: int, mastery_eggs: int} */
    public function overview(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $db = DB::connection('pgsql_platform');
        $week = $db->table('game_plays')->where('started_at', '>=', $now->subDays(7));
        $started = $db->table('game_plays')->count();
        $rounds = $db->table('game_plays')->whereNotNull('finished_at')->count();
        $players = $db->table('game_plays')->distinct()->count('student_id');

        return [
            'players' => (clone $week)->distinct()->count('student_id'),
            'academies' => (clone $week)->distinct()->count('academy_id'),
            'rounds' => $rounds,
            'per_player' => round($rounds / max(1, $players), 1),
            'finished' => (int) round(100 * $rounds / max(1, $started)),
            'eggs' => $db->table('creature_finds')->count(),
            'mastery_eggs' => $db->table('creature_finds')->where('source', 'mastery')->count(),
        ];
    }

    /** @return array<string, int> rounds finished, by game id */
    public function roundsByGame(): array
    {
        return DB::connection('pgsql_platform')->table('game_plays')->whereNotNull('finished_at')
            ->selectRaw('game_id, count(*) as n')->groupBy('game_id')->pluck('n', 'game_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * One game: its rounds, the share finished, average stars and time, and for each position in a round the share
     * right first time (from its latest SAMPLE finished rounds).
     *
     * @return array{rounds: int, finished: int, stars: float, seconds: int, positions: list<int>, academies: int}
     */
    public function game(string $gameId): array
    {
        $db = DB::connection('pgsql_platform');
        $all = $db->table('game_plays')->where('game_id', $gameId);
        $done = (clone $all)->whereNotNull('finished_at');
        $rounds = (clone $done)->count();
        $positions = [];
        foreach ((clone $done)->latest('finished_at')->limit(self::SAMPLE)->get(['items', 'answers']) as $play) {
            $ids = array_column((array) json_decode((string) $play->items, true), 'id');
            $first = [];
            foreach ((array) json_decode((string) $play->answers, true) as $answer) {
                $first[$answer['item'] ?? ''] ??= ($answer['correct'] ?? false) === true;
            }
            foreach ($ids as $i => $id) {
                if (array_key_exists($id, $first)) {
                    $positions[$i] ??= [0, 0];
                    $positions[$i][0] += $first[$id] ? 1 : 0;
                    $positions[$i][1]++;
                }
            }
        }
        ksort($positions);

        return [
            'rounds' => $rounds,
            'finished' => (int) round(100 * $rounds / max(1, (clone $all)->count())),
            'stars' => round((float) (clone $done)->avg('stars'), 1),
            'seconds' => (int) round((float) (clone $done)->avg('seconds')),
            'positions' => array_values(array_map(fn ($p) => (int) round(100 * $p[0] / max(1, $p[1])), $positions)),
            'academies' => (clone $all)->distinct()->count('academy_id'),
        ];
    }
}
