<?php

namespace Modules\Games\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * A learner's games: the rounds they played (which game, how it went), the
 * creatures they hatched, and the limits set for them. Erasing removes them
 * all; what the rounds meant for learning stays in Learning's practice.
 */
final class GamesData implements PersonalDataSource
{
    public function key(): string
    {
        return 'games';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([
            'rounds' => DB::table('game_plays')->where('student_id', $userId)->orderBy('started_at')
                ->get(['game_key as game', 'focus', 'correct', 'total', 'stars', 'started_at', 'finished_at'])->map(fn (object $r) => (array) $r)->all(),
            'creatures' => DB::table('creature_finds')->where('student_id', $userId)->orderBy('found_at')
                ->get(['creature_key as creature', 'source', 'found_at'])->map(fn (object $c) => (array) $c)->all(),
            'limits' => DB::table('game_limits')->where('student_id', $userId)->get(['enabled', 'daily_minutes', 'updated_at'])->map(fn (object $l) => (array) $l)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return [
            'game_rounds' => DB::table('game_plays')->where('student_id', $userId)->delete(),
            'creatures' => DB::table('creature_finds')->where('student_id', $userId)->delete(),
            'game_limits' => DB::table('game_limits')->where('student_id', $userId)->delete(),
        ];
    }
}
