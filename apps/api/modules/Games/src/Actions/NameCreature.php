<?php

namespace Modules\Games\Actions;

use Illuminate\Support\Facades\Validator;
use Modules\Games\Models\CreatureFind;

/**
 * A learner names a creature they hatched (docs/architecture/02, section
 * 18): up to 24 letters, words only (no links or numbers to pass along);
 * empty takes the name back to the creature's own.
 */
final class NameCreature
{
    public const MAX = 24;

    public function handle(string $studentId, string $creatureKey, ?string $name): CreatureFind
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
        Validator::make(['name' => $name], ['name' => ['nullable', 'string', 'max:'.self::MAX, 'regex:/^[\p{L}\p{M} ]*$/u']], [],
            ['name' => __('games::games.creature.name')])->validate();

        $find = CreatureFind::query()->where('student_id', $studentId)->where('creature_key', $creatureKey)->firstOrFail();
        $find->forceFill(['nickname' => $name !== '' ? $name : null])->save();

        return $find;
    }
}
