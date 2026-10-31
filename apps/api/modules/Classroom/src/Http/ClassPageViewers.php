<?php

namespace Modules\Classroom\Http;

use Illuminate\Http\Request;
use Modules\Classroom\Contracts\ClassPage;

/**
 * Who looks at a lesson's class page: someone signed in to the panels, or a
 * student or guardian who came by the signed link in their message (kept in
 * the session for this lesson only).
 */
final class ClassPageViewers implements ClassPage
{
    /** The lesson's own channel, for every seat in it (Broadcast::channel in ClassroomServiceProvider). */
    public const CHANNEL = 'lesson.{session}';

    public function viewer(Request $request, string $sessionId): ?string
    {
        $key = "classroom.pass.{$sessionId}";
        if ($request->query('u') && $request->hasValidRelativeSignature()) {
            $request->session()->put($key, (string) $request->query('u'));
        }

        return $request->user()?->getAuthIdentifier() ?? $request->session()->get($key);
    }

    public function channel(string $sessionId): string
    {
        return 'lesson.'.$sessionId;
    }
}
