<?php

namespace Modules\Classroom\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Authorization\ClassroomAccess;
use Modules\Learning\Contracts\LearningApi;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Tenancy\TenantContext;

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

    public function show(Request $request, string $session, LearningApi $learning, BrandingApi $branding, TenantContext $tenant): View
    {
        $viewer = $this->viewer($request, $session);
        $lesson = $this->lessons->lesson($session);
        $seat = $viewer !== null && $lesson !== null ? $this->access->seatFor($viewer, $lesson) : null;
        if ($seat === null) {
            abort(response()->view('classroom::refused', ['brand' => $branding->current()], 403));
        }

        $user = DB::table('users')->where('id', $viewer)->first(['locale', 'timezone']);
        app()->setLocale(in_array($user->locale ?? null, ['ar', 'en'], true) ? $user->locale : $tenant->require()->defaultLocale);
        $tz = $user->timezone ?: $tenant->require()->defaultTimezone;

        return view('classroom::class', [
            'brand' => $branding->current(),
            'lesson' => $lesson,
            'seat' => $seat,
            'when' => $lesson->startsAt->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i'),
            'plan' => $seat->role === 'student' ? [] : $learning->planFor($lesson->enrollmentId, $lesson->startsAt),
            'ticketUrl' => route('classroom.ticket', ['session' => $session]),
            'goUrl' => route('classroom.go', ['session' => $session]),
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
        $ticket = $join->handle($session, $viewer);
        if ($ticket->seat->role !== 'observer') {
            $this->lessons->participantJoined($session, $ticket->seat->identity, now()->toImmutable(), 'external_link');
        }

        return redirect()->away($ticket->url);
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
