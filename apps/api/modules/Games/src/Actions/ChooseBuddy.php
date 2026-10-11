<?php

namespace Modules\Games\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Games\Models\CreatureFind;

/**
 * A learner picks the creature that keeps them company (docs/architecture/02,
 * section 18): it is on their world's header and flies beside them. One at
 * a time; only one they hatched.
 */
final class ChooseBuddy
{
    public function handle(string $studentId, string $creatureKey): CreatureFind
    {
        return DB::transaction(function () use ($studentId, $creatureKey) {
            $find = CreatureFind::query()->where('student_id', $studentId)->where('creature_key', $creatureKey)->lockForUpdate()->firstOrFail();
            CreatureFind::query()->where('student_id', $studentId)->where('buddy', true)->whereKeyNot($find->id)->update(['buddy' => false]);
            $find->forceFill(['buddy' => true])->save();

            return $find;
        });
    }
}
