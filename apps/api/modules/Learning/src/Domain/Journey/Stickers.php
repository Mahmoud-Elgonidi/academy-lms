<?php

namespace Modules\Learning\Domain\Journey;

/**
 * The kids' sticker album (docs/architecture/02, section 18): each week's
 * chest a child opens brings the next sticker, in the album's order, then
 * round again.
 */
final class Stickers
{
    public const ALBUM = [
        'lion', 'rocket', 'sun', 'whale', 'cat', 'tree', 'kite', 'castle', 'camel', 'owl', 'turtle', 'balloon',
        'moon', 'fish', 'bee', 'rainbow', 'plane', 'crown', 'cupcake', 'dino', 'flower', 'robot', 'boat', 'trophy',
    ];

    /** The sticker the n-th chest (0-based) brings. */
    public static function nth(int $n): string
    {
        return self::ALBUM[$n % count(self::ALBUM)];
    }
}
