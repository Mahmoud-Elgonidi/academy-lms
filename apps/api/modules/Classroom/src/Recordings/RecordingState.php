<?php

namespace Modules\Classroom\Recordings;

use Modules\Classroom\Authorization\RecordingAccess;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\Room;
use Modules\Scheduling\Contracts\DTOs\LessonCard;

/**
 * What a lesson's screen shows about recording, the same on the class page,
 * in the portal and in the apps: the academy's mode (off when the lesson is
 * on an outside link), whether this person may start and stop it, whether
 * it runs now, and who has not agreed yet.
 */
final class RecordingState
{
    public function __construct(
        private readonly RecordingRules $rules,
        private readonly RecordingAccess $access,
    ) {}

    /** @return array{mode: string, controls: bool, running: bool, missing: list<string>} */
    public function for(string $viewerId, LessonCard $lesson): array
    {
        $settings = ClassroomSettings::current();
        $provider = Room::query()->where('session_id', $lesson->id)->value('provider') ?? $settings->provider;
        $on = $settings->records() && $provider === 'livekit';

        return [
            'mode' => $on ? $settings->recording : 'off',
            'controls' => $on && $this->access->controls($viewerId, $lesson),
            'running' => Recording::query()->running()->where('session_id', $lesson->id)->exists(),
            'missing' => $on ? $this->rules->missing($lesson) : [],
        ];
    }
}
