<?php

namespace Modules\Classroom\Contracts;

use Illuminate\Http\Request;

/**
 * The lesson's class page, for a module that adds something beside the room
 * (SharedKernel's LessonQuran): who is looking, and the lesson's own live
 * channel, which every seat in the lesson may hear (the page signs it for a
 * student or guardian who came by their signed link, at classroom.live.auth).
 */
interface ClassPage
{
    /** Who looks at the class page: signed in to the panels, or came by their signed link; null for nobody. */
    public function viewer(Request $request, string $sessionId): ?string;

    /** The lesson's own private channel's name (without "private-"). */
    public function channel(string $sessionId): string;
}
