<?php

namespace Modules\Classroom\Http\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\Recordings\GiveRecordingConsent;
use Modules\Classroom\Actions\Recordings\StartRecording;
use Modules\Classroom\Actions\Recordings\StopRecording;
use Modules\Classroom\Actions\Recordings\WatchRecording;
use Modules\Classroom\Actions\Recordings\WithdrawRecordingConsent;
use Modules\Classroom\Authorization\RecordingAccess;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Queries\RecordingViews;
use Modules\People\Contracts\FamilyView;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Lesson recordings for the apps and the portal: consent, the recordings to watch, and starting and stopping one in a lesson. */
final class RecordingsController
{
    public function __construct(
        private readonly RecordingViews $views,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * Consent to lesson recordings.
     *
     * The people whose consent the person signed in sees: themself (a teacher, or a student) and the children they
     * look after. For each, whether it stands and who gave it; whether they may change it (a teacher or an adult
     * student for themself, a guardian for a child), else who decides; and the words to agree to (send its version
     * back when agreeing).
     */
    public function consents(Request $request, ViewerTimezone $tz): JsonResponse
    {
        $viewer = (string) $request->user()->getAuthIdentifier();
        $settings = ClassroomSettings::current();
        $people = [];
        foreach ($this->views->people($viewer) as $personId) {
            $c = $this->views->consent($viewer, $personId, $tz->effective());
            $sections = [];
            foreach ($c['text']['sections'] as $part) {
                $sections[] = ['title' => (string) $part['title'], 'body' => (string) $part['body']];
            }
            $people[] = [
                'person' => ['id' => (string) $c['person']['id'], 'name' => (string) $c['person']['name']],
                'self' => (bool) $c['self'],
                /** agreed | none */
                'state' => (string) $c['state'],
                'granted_at' => $c['granted_at'] ? $this->time->present($c['granted_at']) : null,
                /** Who agreed, in words ("You agreed on 5 October 2026"); null when nobody has. */
                'by' => $c['by'],
                /** Whether the person signed in may agree or take it back. */
                'can_change' => (bool) $c['can_change'],
                /** Who decides, in words, when the person signed in cannot. */
                'decides' => $c['decides'],
                'text' => [
                    'version' => (int) $c['text']['version'],
                    'title' => (string) $c['text']['title'],
                    'intro' => (string) $c['text']['intro'],
                    /** Each part: a heading and its words. */
                    'sections' => $sections,
                    'agree' => (string) $c['text']['agree'],
                ],
            ];
        }

        return response()->json([
            /** Whether the academy records lessons now (off | manual | auto); consent is kept either way. */
            'recording' => $settings->recording,
            'people' => $people,
        ]);
    }

    /**
     * Agree to lessons being recorded.
     *
     * For yourself (a teacher, an adult student) or your child. version is the wording's version from the list (a
     * newer wording since then answers 422: read it again).
     */
    public function agree(Request $request, string $person, GiveRecordingConsent $give): JsonResponse
    {
        $data = $request->validate(['agree' => ['accepted'], 'version' => ['required', 'integer']], ['agree.accepted' => __('classroom::recordings.consent.tick')]);
        $give->handle($person, (string) $request->user()->getAuthIdentifier(), (int) $data['version'], $request->ip(), $request->userAgent());

        return response()->json(['state' => 'agreed']);
    }

    /**
     * Take back consent to lessons being recorded.
     *
     * Any recording running in their lessons stops, and nothing more is recorded; what was recorded keeps its days.
     */
    public function withdraw(Request $request, string $person, WithdrawRecordingConsent $withdraw): JsonResponse
    {
        $withdraw->handle($person, (string) $request->user()->getAuthIdentifier());

        return response()->json(['state' => 'none']);
    }

    /**
     * A learner's lesson recordings.
     *
     * The ready recordings of the learner's lessons, newest first, when the academy shares them with families
     * (shared false: none are listed). Open one with recordings/{id}/watch.
     */
    public function family(Request $request, FamilyView $family): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $learner = $family->learner((string) $request->user()->getAuthIdentifier(), $request->query('learner_id')) ?? throw new NotFoundHttpException;
        $shared = ClassroomSettings::current()->recording_family;

        return response()->json([
            'shared' => $shared,
            'data' => $shared ? $this->rows($this->views->recordings('student', $learner->id)) : [],
        ]);
    }

    /**
     * My lessons' recordings.
     *
     * The ready recordings of the teacher's own lessons, newest first, when the academy shares them with teachers
     * (shared false: none are listed). Teachers watch them; they never download them.
     */
    public function teacher(Request $request): JsonResponse
    {
        $shared = ClassroomSettings::current()->recording_teacher;

        return response()->json([
            'shared' => $shared,
            'data' => $shared ? $this->rows($this->views->recordings('teacher', (string) $request->user()->getAuthIdentifier())) : [],
        ]);
    }

    /**
     * Watch a recording.
     *
     * Whoever may (the office, the lesson's teacher, its student and guardians when the academy allows) gets an
     * address that plays it in a page or a player, signed until expires_at; the opening is logged.
     */
    public function watch(Request $request, string $recording, WatchRecording $watch): JsonResponse
    {
        $model = Recording::query()->whereKey($recording)->first() ?? throw new NotFoundHttpException;
        $link = $watch->handle($model, (string) $request->user()->getAuthIdentifier(), $request->ip(), $request->userAgent());

        return response()->json([
            'url' => $link->url,
            /** video | audio */
            'media' => $model->media,
            'expires_at' => $this->time->present($link->expiresAt),
        ]);
    }

    /**
     * Start recording a lesson.
     *
     * Its teacher (or a supervisor in it), while the classroom is open on the academy's own video, when the academy
     * records lessons and everyone the lesson records agreed (else 422, naming who has not). Already running: the
     * same one.
     */
    public function start(Request $request, string $session, StartRecording $start): JsonResponse
    {
        $recording = $start->handle($session, (string) $request->user()->getAuthIdentifier());

        return response()->json(['data' => ['status' => $recording->status, 'running' => $recording->isRunning()]]);
    }

    /** Stop recording a lesson. */
    public function stop(Request $request, string $session, StopRecording $stop, AttendanceApi $lessons, RecordingAccess $access): JsonResponse
    {
        $viewer = (string) $request->user()->getAuthIdentifier();
        $lesson = $lessons->lesson($session) ?? throw new NotFoundHttpException;
        abort_unless($access->controls($viewer, $lesson), 403);
        if ($running = Recording::query()->running()->where('session_id', $session)->first()) {
            $stop->handle($running, $viewer);
        }

        return response()->json(['data' => ['status' => 'stopped', 'running' => false]]);
    }

    /**
     * @param  list<array{id: string, at: CarbonImmutable, teacher: string, student: ?string, media: string, duration_sec: int, expires_at: ?CarbonImmutable}>  $recordings
     * @return list<array<string, mixed>>
     */
    private function rows(array $recordings): array
    {
        $rows = [];
        foreach ($recordings as $r) {
            $rows[] = [
                'id' => $r['id'],
                /** The lesson's start. */
                'at' => $this->time->present($r['at']),
                'teacher' => $r['teacher'],
                'student' => $r['student'],
                /** video | audio */
                'media' => $r['media'],
                'duration_sec' => $r['duration_sec'],
                /** When it goes (its days are up); null when held. */
                'expires_at' => $r['expires_at'] ? $this->time->present($r['expires_at']) : null,
            ];
        }

        return $rows;
    }
}
