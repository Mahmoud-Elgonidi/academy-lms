<?php

namespace Modules\Billing\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** "An invoice of this academy was issued, paid or cancelled", for the open revenue screens; no data. */
final class RevenueChanged implements ShouldBroadcastNow
{
    public const CHANNEL = 'academy.{academyId}.revenue';

    public function __construct(public readonly string $academyId) {}

    public static function channel(string $academyId): string
    {
        return str_replace('{academyId}', $academyId, self::CHANNEL);
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channel($this->academyId))];
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
