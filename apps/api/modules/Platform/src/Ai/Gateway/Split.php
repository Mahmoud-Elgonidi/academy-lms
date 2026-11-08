<?php

namespace Modules\Platform\Ai\Gateway;

/**
 * Which of a route's two chains a request takes when a share goes to the
 * second one for comparison: the same request (the same id, and the same
 * capability) always takes the same one, so a helper that hears and then
 * writes stays on one side, and a percentage is held over many requests.
 * Pure: an id, a key and a percentage in, a side out.
 */
final class Split
{
    public static function takesSecond(string $requestId, string $capability, int $percent): bool
    {
        if ($percent <= 0) {
            return false;
        }
        if ($percent >= 100) {
            return true;
        }

        return hexdec(substr(hash('sha256', $requestId."\n".$capability), 0, 8)) % 100 < $percent;
    }
}
