<?php

namespace Modules\Classroom\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Actions\Recordings\StartRecording;
use Modules\Classroom\Actions\Recordings\StopRecording;
use Modules\Classroom\Authorization\ClassroomAccess;
use Modules\Classroom\Authorization\RecordingAccess;
use Modules\Classroom\Contracts\ClassPage;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Recordings\RecordingState;
use Modules\Learning\Contracts\LearningApi;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Quran\LessonQuran;
use Modules\SharedKernel\Realtime\BrowserEcho;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerClock;

/**
 * The class page on the web. Who is looking: someone signed in to the
 * panels (teacher, supervisor), or a student or guardian who came by the
 * signed link in their message; that link lets them into this lesson only,
 * until half an hour after it ends.
 */
final class ClassroomPageController
{
    public function __construct(
        private readonly AttendanceApi $lessons,
        private readonly ClassroomAccess $access,
        private readonly ClassPage $page,
    ) {}

    public function show(Request $request, string $session, LearningApi $learning, BrandingApi $branding, TenantContext $tenant, RecordingState $recording, LessonQuran $quran): View
    {
        $viewer = $this->viewer($request, $session);
        $lesson = $this->lessons->lesson($session);
        $seat = $viewer !== null && $lesson !== null ? $this->access->seatFor($viewer, $lesson) : null;
        if ($seat === null) {
            $consent = $viewer !== null && $lesson !== null && $this->access->waitsForConsent($viewer, $lesson);
            abort(response()->view('classroom::refused', ['brand' => $branding->current(), 'consent' => $consent], 403));
        }

        $user = DB::table('users')->where('id', $viewer)->first(['locale', 'timezone', 'time_format']);
        app()->setLocale(in_array($user->locale ?? null, ['ar', 'en'], true) ? $user->locale : $tenant->require()->defaultLocale);
        $tz = $user->timezone ?: $tenant->require()->defaultTimezone;
        app(ViewerClock::class)->choose($user->time_format);

        // What another module puts beside the room for this seat (LessonQuran), none for a hidden observer.
        $side = $seat->role === 'observer' ? null : $quran->classPanel($lesson->id, $seat->userId, [
            'channel' => $this->page->channel($lesson->id),
            'auth' => route('classroom.live.auth', ['session' => $session]),
            'echo' => BrowserEcho::config($request),
        ]);

        return view('classroom::class', [
            'brand' => $branding->current(),
            'side' => $side,
            'lesson' => $lesson,
            'seat' => $seat,
            'when' => $lesson->startsAt->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('l j F · H:i')),
            // The teacher and staff see today's plan with every material; the student, the materials meant for everyone.
            'plan' => $learning->planFor($lesson->enrollmentId, $lesson->startsAt, $seat->role !== 'student'),
            'homework' => $seat->role === 'student' || $lesson->studentId === null || $lesson->isGroup() ? null : $learning->lastHomework($lesson->studentId, $lesson->enrollmentId, $lesson->startsAt),
            'tz' => $tz,
            'ticketUrl' => route('classroom.ticket', ['session' => $session]),
            'goUrl' => route('classroom.go', ['session' => $session]),
            'recording' => $recording->for($viewer, $lesson) + [
                'startUrl' => route('classroom.recording.start', ['session' => $session]),
                'stopUrl' => route('classroom.recording.stop', ['session' => $session]),
            ],
        ]);
    }

    public function ticket(Request $request, string $session, JoinLesson $join): JsonResponse
    {
        $viewer = $this->viewer($request, $session) ?? abort(403);

        $ticket = $join->handle($session, $viewer);

        return response()->json(['data' => $ticket->toArray() + ['notice' => $ticket->notice]]);
    }

    /** An outside link: the join is recorded here, then the browser goes on to Zoom or Meet. */
    public function go(Request $request, string $session, JoinLesson $join): RedirectResponse
    {
        $viewer = $this->viewer($request, $session) ?? abort(403);

        return redirect()->away($join->enter($session, $viewer)->url);
    }

    /** The teacher (or a supervisor) starts recording the lesson, when the academy records lessons and everyone agreed. */
    public function startRecording(Request $request, string $session, StartRecording $start): JsonResponse
    {
        $viewer = $this->viewer($request, $session) ?? abort(403);
        $recording = $start->handle($session, $viewer);

        return response()->json(['data' => ['status' => $recording->status, 'running' => $recording->isRunning()]]);
    }

    /** And stops it. */
    public function stopRecording(Request $request, string $session, StopRecording $stop, RecordingAccess $access): JsonResponse
    {
        $viewer = $this->viewer($request, $session) ?? abort(403);
        $lesson = $this->lessons->lesson($session) ?? abort(404);
        abort_unless($access->controls($viewer, $lesson), 403);
        if ($running = Recording::query()->running()->where('session_id', $session)->first()) {
            $stop->handle($running, $viewer);
        }

        return response()->json(['data' => ['status' => 'stopped', 'running' => false]]);
    }

    /**
     * Permission to hear the lesson's own channel (what a tool beside the room says), for whoever has a seat in it: a student or guardian
     * who came by the signed link has no session to sign in with, so the page signs the socket's subscription here.
     */
    public function liveAuth(Request $request, string $session): JsonResponse
    {
        $request->validate([
            'socket_id' => ['required', 'string', 'max:64', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:200'],
        ]);
        $viewer = $this->viewer($request, $session) ?? abort(403);
        $lesson = $this->lessons->lesson($session) ?? abort(404);
        abort_unless($request->input('channel_name') === 'private-'.$this->page->channel($lesson->id) && $this->access->seatFor($viewer, $lesson) !== null, 403);
        $key = (string) config('broadcasting.connections.reverb.key');
        abort_if(BrowserEcho::config($request) === null || $key === '', 404);
        $signed = hash_hmac('sha256', $request->input('socket_id').':'.$request->input('channel_name'), (string) config('broadcasting.connections.reverb.secret'));

        return response()->json(['auth' => $key.':'.$signed]);
    }

    private function viewer(Request $request, string $session): ?string
    {
        return $this->page->viewer($request, $session);
    }
}
