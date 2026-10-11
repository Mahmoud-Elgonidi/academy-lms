<?php

namespace Modules\Platform\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** "A game draft changed" (the designer finished, a change was asked, a game was published), for the studio's open screens; no data. */
final class GameStudioChanged implements ShouldBroadcastNow
{
    public const CHANNEL = 'console.games';

    public function __construct(public readonly string $draftId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::CHANNEL)];
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
