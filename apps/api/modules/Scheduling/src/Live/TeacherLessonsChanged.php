<?php

namespace Modules\Scheduling\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Identity\Contracts\PersonalChannel;

/** "One of your lessons changed" (its time, status or teacher), on the teacher's own channel; no data. */
final class TeacherLessonsChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $teacherId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(PersonalChannel::name($this->teacherId))];
    }

    public function broadcastAs(): string
    {
        return 'lessons.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
