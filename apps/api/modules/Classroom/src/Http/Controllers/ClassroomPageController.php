<?php

namespace Modules\Classroom\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
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
use Modules\Classroom\Live\LessonMushafChanged;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Recordings\RecordingState;
use Modules\Learning\Contracts\LearningApi;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Scheduling\Contracts\AttendanceApi;
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
    ) {}

    public function show(Request $request, string $session, LearningApi $learning, BrandingApi $branding, TenantContext $tenant, RecordingState $recording): View
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

        // A Quran lesson's shared mushaf (Learning's): its teacher turns the pages and marks words, the student follows.
        $mushaf = $seat->role === 'observer' ? null : $learning->lessonMushaf($lesson->id, $seat->userId);

        return view('classroom::class', [
            'brand' => $branding->current(),
            'mushaf' => $mushaf === null ? null : [
                'first' => $mushaf,
                'urls' => [
                    'show' => route('classroom.mushaf', ['session' => $session]),
                    'change' => route('classroom.mushaf.change', ['session' => $session]),
                    'auth' => route('classroom.live.auth', ['session' => $session]),
                ],
                'echo' => BrowserEcho::config($request),
                'channel' => LessonMushafChanged::channelName($lesson->id),
            ],
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

        return response()->json(['data' => $join->handle($session, $viewer)->toArray()]);
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

    /** A page of the lesson's shared mushaf (the one asked for, else the teacher's, else where the lesson starts). */
    public function mushaf(Request $request, string $session, LearningApi $learning): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'between:1,604']]);
        $viewer = $this->viewer($request, $session) ?? abort(403);
        $page = $learning->lessonMushaf($session, $viewer, $request->integer('page') ?: null) ?? abort(404);

        return response()->json(['data' => $page]);
    }

    /** The teacher turns the lesson's mushaf, marks a word or takes a mark off (change: turn, mark, unmark). */
    public function changeMushaf(Request $request, string $session, LearningApi $learning): JsonResponse
    {
        $request->validate(['change' => ['required', 'in:turn,mark,unmark']]);
        $viewer = $this->viewer($request, $session) ?? abort(403);
        try {
            $page = $learning->changeMushaf($session, $viewer, (string) $request->input('change'), $request->except('change'));
        } catch (AuthorizationException) {
            abort(403);
        }

        return response()->json(['data' => $page ?? abort(404)]);
    }

    /**
     * Permission to hear the lesson's own channel (its mushaf), for whoever has a seat in it: a student or guardian
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
        abort_unless($request->input('channel_name') === 'private-'.LessonMushafChanged::channelName($lesson->id) && $this->access->seatFor($viewer, $lesson) !== null, 403);
        $key = (string) config('broadcasting.connections.reverb.key');
        abort_if(BrowserEcho::config($request) === null || $key === '', 404);
        $signed = hash_hmac('sha256', $request->input('socket_id').':'.$request->input('channel_name'), (string) config('broadcasting.connections.reverb.secret'));

        return response()->json(['auth' => $key.':'.$signed]);
    }

    private function viewer(Request $request, string $session): ?string
    {
        $key = "classroom.pass.{$session}";
        if ($request->query('u') && $request->hasValidRelativeSignature()) {
            $request->session()->put($key, (string) $request->query('u'));
        }

        return $request->user()?->getAuthIdentifier() ?? $request->session()->get($key);
    }
}
