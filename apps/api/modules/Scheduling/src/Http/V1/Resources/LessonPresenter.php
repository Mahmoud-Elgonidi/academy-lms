<?php

namespace Modules\Scheduling\Http\V1\Resources;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * One lesson for the apps (a row of Queries\LessonList): times in the
 * viewer's zone, labels in their language, and what they may do with it.
 * A family sees the teacher; a teacher, the student.
 */
final class LessonPresenter
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /**
     * @param  'teacher'|'student'  $counterpart  whom the lesson is with, as the viewer sees it
     * @param  bool  $mayAct  the viewer may cancel or refuse (not a child in the kids' look, not a teacher)
     * @return array<string, mixed>
     */
    public function lesson(object $r, CarbonImmutable $now, string $counterpart = 'teacher', bool $mayAct = false): array
    {
        $l = 'scheduling::family.';
        $open = in_array($r->status, ['scheduled', 'in_progress'], true);
        $opensAt = $r->starts_at->subMinutes((int) config('classroom.opens_before_min', 10));
        $joinable = $open && $now->greaterThanOrEqualTo($opensAt)
            && $now->lessThanOrEqualTo($r->ends_at->addMinutes((int) config('classroom.closes_after_min', 10)));
        $ahead = $r->status === 'scheduled' && $r->starts_at->greaterThan($now);
        $substitute = $r->original_teacher ?? null;

        return [
            'id' => $r->id,
            'starts_at' => $this->time->present($r->starts_at),
            'ends_at' => $this->time->present($r->ends_at),
            'minutes' => (int) round($r->starts_at->diffInMinutes($r->ends_at)),
            'subject' => app()->getLocale() === 'ar' ? $r->subject_ar : $r->subject_en,
            /** The teacher's name (family) or the student's (teacher). */
            'with' => $r->{$counterpart},
            /** scheduled, in_progress, completed, student_absent, teacher_absent, needs_review, cancelled */
            'status' => $r->status,
            'status_label' => __($l.'statuses.'.$r->status),
            /** regular, trial, makeup, extra, assessment */
            'type' => $r->type,
            'type_label' => $r->type === 'regular' ? null : __($l.'types.'.$r->type),
            /** Booked but not yet paid for. */
            'unfunded' => $open && $r->funding_status === 'unfunded',
            'join' => [
                /** The room is open: POST /lessons/{id}/join for a ticket. */
                'open' => $joinable,
                'opens_at' => $open && ! $joinable && $r->starts_at->greaterThan($now) ? $this->time->present($opensAt) : null,
            ],
            /** A substitute has the lesson: the family's own teacher (family), or whom the teacher covers (teacher). */
            'substitute_for' => $substitute,
            'can_cancel' => $mayAct && $counterpart === 'teacher' && $ahead,
            'can_refuse_substitute' => $mayAct && $counterpart === 'teacher' && $substitute !== null && $ahead,
            /** Why the academy cancelled it (a holiday, the teacher's leave), when it did. */
            'cancelled_because' => $r->status === 'cancelled' && in_array($r->cancel_reason ?? null, ['holiday', 'teacher_leave', 'substitute_refused'], true)
                ? __('scheduling::timeoff.family.why.'.$r->cancel_reason) : null,
        ];
    }
}
