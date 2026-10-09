<?php

namespace Modules\Safeguarding\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\Safeguarding\Actions\ReportConcern;
use Modules\Safeguarding\ConcernRules;
use Modules\Safeguarding\Queries\MyConcerns;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * Raising a concern about a child's safety from the apps (the same as "Tell
 * us" in /my and "Raise a concern" in /teach): what one can be about, in the
 * reporter's own words, sending one, and where the ones they raised stand.
 * A family names the learner (learner_id); a teacher may name a student they
 * teach. Only the academy's safeguarding leads read them.
 */
final class ConcernsController
{
    public function __construct(
        private readonly ConcernRules $rules,
        private readonly FamilyView $family,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * What a concern can be about.
     *
     * The look the screen speaks in (kids: a child's words, the text optional, no lesson and no "danger now";
     * student; classic; teacher), the choices in those words, the lessons it may be about (the learner's or the
     * teacher's from the last month, newest first) and, for a teacher, the students they taught lately. A family
     * names the learner with `learner_id` (else the first).
     */
    public function options(Request $request): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        [$side, $learner] = $this->who($request, $me);
        $look = $side === 'teacher' ? 'teacher' : $this->rules->look($me);

        $categories = [];
        foreach ($this->rules->categoriesFor($side, $look === 'teacher' ? 'classic' : $look) as $c) {
            $categories[] = ['key' => $c->value, 'label' => $c->label($look)];
        }
        $lessons = [];
        foreach ($look === 'kids' ? [] : $this->rules->lessonChoices($side, $learner?->id, $me) as $l) {
            $lessons[] = $this->lesson($l, $side);
        }
        $students = [];
        foreach ($side === 'teacher' ? $this->rules->studentsOfTeacher($me) : [] as $id => $name) {
            $students[] = ['id' => (string) $id, 'name' => (string) $name];
        }

        return response()->json(['data' => [
            /** kids | student | classic | teacher */
            'look' => $look,
            /** The learner it would be about; null for a teacher. */
            'learner' => $learner ? ['id' => $learner->id, 'name' => $learner->name] : null,
            'categories' => $categories,
            /** Whether the person must say what happened (a child may just press the button). */
            'text_required' => $look !== 'kids',
            /** Whether to offer "someone may be in danger right now" (not to a child). */
            'urgent_allowed' => $look !== 'kids',
            'lessons' => $lessons,
            /** A teacher's students of the last months; empty for a family. */
            'students' => $students,
            /** What to do first when someone is in danger now, in the screen's words. */
            'danger' => $look === 'teacher' ? __('safeguarding::screens.teacher.danger') : __('safeguarding::screens.family.danger.'.$look),
        ]]);
    }

    /**
     * My concerns.
     *
     * The concerns this person raised, newest first: when, what about, where each stands (received, being looked
     * into, closed) and the academy's reply once closed. Never the leads' notes or what was done.
     */
    public function index(Request $request, MyConcerns $mine): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        [$side] = $this->who($request, $me);

        return response()->json(['data' => $mine->of($me, $side === 'teacher' ? 'teacher' : $this->rules->look($me))]);
    }

    /**
     * Raise a concern.
     *
     * `category` is one of what "new" offered; `text` what happened (a child may leave it out); `urgent` when someone
     * may be in danger now; `session_id` optionally one of the lessons offered; a teacher may name `student_id`.
     * Answers `201` with the concern as its reporter sees it. Send an `Idempotency-Key`.
     */
    public function store(Request $request, ReportConcern $report, MyConcerns $mine): JsonResponse
    {
        $request->validate([
            /** A family: the learner it is about (one of theirs). */
            'learner_id' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:adult_behaviour,outside_contact,content,bullying,wellbeing,other'],
            'text' => ['nullable', 'string', 'max:5000'],
            'urgent' => ['nullable', 'boolean'],
            /** One of the lessons "new" offered. */
            'session_id' => ['nullable', 'string'],
            /** A teacher: one of the students "new" offered. */
            'student_id' => ['nullable', 'string'],
        ]);
        $me = (string) $request->user()?->getAuthIdentifier();
        [$side, $learner] = $this->who($request, $me);
        $look = $side === 'teacher' ? 'teacher' : $this->rules->look($me);
        $concern = $report->handle(
            reporterId: $me,
            side: $side,
            studentId: $side === 'teacher' ? ($request->input('student_id') ?: null) : $learner?->id,
            category: (string) $request->input('category'),
            body: $request->input('text'),
            urgent: $look !== 'kids' && $request->boolean('urgent'),
            sessionId: $request->input('session_id') ?: null,
        );
        $shown = collect($mine->of($me, $look))->firstWhere('id', $concern->id);

        return response()->json(['data' => $shown, 'message' => $look === 'teacher' ? __('safeguarding::screens.teacher.sent') : __('safeguarding::screens.family.sent.'.$look)], 201);
    }

    /** @return array{0: string, 1: ?Learner} */
    private function who(Request $request, string $me): array
    {
        if ($this->rules->isTeacher($me)) {
            return ['teacher', null];
        }
        $learner = $this->family->learner($me, $request->input('learner_id') ?: $request->query('learner_id'));
        abort_if($learner === null, 403);

        return ['family', $learner];
    }

    /** @return array<string, mixed> */
    private function lesson(LessonCard $l, string $side): array
    {
        return [
            'id' => $l->id,
            'subject' => $l->subject(),
            'starts_at' => $this->time->present($l->startsAt),
            /** The other side of the lesson: its student for a teacher, its teacher for a family. */
            'with' => $side === 'teacher' ? $l->studentName : $l->teacherName,
        ];
    }
}
