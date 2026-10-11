<?php

namespace Modules\Platform\Games;

/** What a published version says about itself, kept with it (game_versions.manifest). */
final class Manifest
{
    /** The bridge's protocol the page was written for (resources/games/bridge.js). */
    public const BRIDGE = 1;

    /**
     * @param  list<string>  $stages
     * @param  list<string>  $kinds
     * @return array<string, mixed>
     */
    public static function make(array $stages, array $kinds, int $minItems, int $maxItems, string $howToPlayAr, string $howToPlayEn): array
    {
        return [
            'bridge' => self::BRIDGE,
            'stages' => array_values($stages),
            'kinds' => array_values($kinds),
            'min_items' => $minItems,
            'max_items' => $maxItems,
            'how_to_play_ar' => $howToPlayAr,
            'how_to_play_en' => $howToPlayEn,
        ];
    }
}
