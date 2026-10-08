<?php

namespace Modules\Classroom\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Recordings\RecordingState;
use Modules\Scheduling\Contracts\AttendanceApi;

/** The apps: a ticket into a lesson's room (LiveKit server and token, or an outside link). */
final class LessonJoinController
{
    /**
     * Join a lesson.
     *
     * A ticket into the lesson's room, with what the screen shows about recording: the academy's mode (off on an
     * outside link), whether this person may start and stop it, whether it runs now, and who has not agreed yet.
     */
    public function __invoke(Request $request, string $session, JoinLesson $join, AttendanceApi $lessons, RecordingState $recording): JsonResponse
    {
        $viewer = (string) $request->user()->getAuthIdentifier();
        $ticket = $join->handle($session, $viewer);
        $lesson = $lessons->lesson($session);
        $state = $lesson !== null ? $recording->for($viewer, $lesson) : ['mode' => 'off', 'controls' => false, 'running' => false, 'missing' => []];

        return response()->json([
            'data' => $ticket->toArray(),
            'recording' => [
                /** off | manual | auto */
                'mode' => (string) $state['mode'],
                'controls' => (bool) $state['controls'],
                'running' => (bool) $state['running'],
                /** Who has not agreed to recording yet (names). */
                'missing' => array_values(array_map('strval', $state['missing'])),
            ],
        ]);
    }
}
