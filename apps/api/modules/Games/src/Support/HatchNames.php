<?php

namespace Modules\Games\Support;

use Modules\SharedKernel\Games\CatalogCreature;

/**
 * The three names a child chooses from when a creature hatches (the approved
 * design, Hatch): its own and two more from the reader's language, the same
 * two every time for that creature, on every screen.
 */
final class HatchNames
{
    /** @return list<string> */
    public static function for(CatalogCreature $creature): array
    {
        return self::of($creature->key, $creature->name());
    }

    /**
     * @param  string  $key  the creature's key
     * @param  string  $name  its own name in the reader's language
     * @return list<string>
     */
    public static function of(string $key, string $name): array
    {
        $names = array_values((array) __('games::games.hatch.names'));
        $count = max(1, count($names));
        $at = crc32($key) % $count;

        return array_values(array_filter(array_unique([$name, $names[$at] ?? '', $names[($at + 5) % $count] ?? '']), fn ($n) => $n !== ''));
    }
}
