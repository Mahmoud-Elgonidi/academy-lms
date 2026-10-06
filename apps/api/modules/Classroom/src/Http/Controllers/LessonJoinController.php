<?php

namespace Modules\Classroom\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\JoinLesson;

/** The apps: a ticket into a lesson's room (LiveKit server and token, or an outside link). */
final class LessonJoinController
{
    public function __invoke(Request $request, string $session, JoinLesson $join): JsonResponse
    {
        return response()->json(['data' => $join->handle($session, (string) $request->user()->getAuthIdentifier())->toArray()]);
    }
}
