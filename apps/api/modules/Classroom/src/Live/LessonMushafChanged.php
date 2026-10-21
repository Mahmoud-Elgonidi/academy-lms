<?php

namespace Modules\Classroom\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * "The lesson's shared mushaf changed" (a page turned, a word marked), on the
 * lesson's own channel, which the class page listens on (its students and
 * guardians may have come by a signed link, without a channel of their own).
 * No data: the page asks again.
 */
final class LessonMushafChanged implements ShouldBroadcastNow
{
    public const CHANNEL = 'lesson.{session}';

    public function __construct(public readonly string $sessionId) {}

    public static function channelName(string $sessionId): string
    {
        return 'lesson.'.$sessionId;
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->sessionId))];
    }

    public function broadcastAs(): string
    {
        return 'mushaf.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
