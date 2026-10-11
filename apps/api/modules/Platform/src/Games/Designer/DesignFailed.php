<?php

namespace Modules\Platform\Games\Designer;

use RuntimeException;

/**
 * The designer could not make the game this round: not set up (no key),
 * declined the story, ran out of room, answered in a shape we cannot
 * read, or the service did not answer. The reason's wording is in
 * platform::games.failed.{reason}; the detail is for the platform team.
 */
final class DesignFailed extends RuntimeException
{
    public const REASONS = ['not_configured', 'refused', 'too_long', 'unreadable', 'unavailable'];

    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail !== '' ? $detail : $reason);
    }
}
