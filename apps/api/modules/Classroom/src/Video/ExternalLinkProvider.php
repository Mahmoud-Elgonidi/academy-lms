<?php

namespace Modules\Classroom\Video;

use Illuminate\Validation\ValidationException;
use Modules\Classroom\Models\MeetingLink;
use Modules\Classroom\Models\Room;
use Modules\Scheduling\Contracts\DTOs\LessonCard;

/**
 * The fallback: each teacher's own Zoom or Meet link. Entering goes through
 * our page, which records the join (the provider tells us nothing more, so
 * attendance otherwise is by hand).
 */
final class ExternalLinkProvider implements VideoProvider
{
    public function name(): string
    {
        return 'external';
    }

    public function ticket(Room $room, Seat $seat, LessonCard $lesson): JoinTicket
    {
        $url = MeetingLink::query()->where('teacher_id', $lesson->teacherId)->value('url')
            ?? throw ValidationException::withMessages(['lesson' => __('classroom::classroom.errors.no_link')]);

        return new JoinTicket('external', $url, null, null, $seat, $lesson->endsAt->addMinutes(30));
    }

    public function participants(Room $room): ?array
    {
        return null;
    }
}
