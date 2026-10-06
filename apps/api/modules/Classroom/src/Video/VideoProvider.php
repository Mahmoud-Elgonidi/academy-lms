<?php

namespace Modules\Classroom\Video;

use Modules\Classroom\Models\Room;
use Modules\Scheduling\Contracts\DTOs\LessonCard;

/** A video provider (docs/architecture/04, "Adapter + Manager": VideoProvider). */
interface VideoProvider
{
    public function name(): string;

    /** A ticket for this person into this lesson's room; throws when the provider cannot give one. */
    public function ticket(Room $room, Seat $seat, LessonCard $lesson): JoinTicket;

    /** @return list<string>|null identities in the room now; null when the provider cannot tell (an outside link) */
    public function participants(Room $room): ?array;
}
