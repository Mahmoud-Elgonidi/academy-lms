<?php

namespace Modules\Scheduling\Http\V1\Resources;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Actions\Groups\GroupOutcomes;
use Modules\Scheduling\Domain\Disputes\DisputeRules;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * One lesson for the apps (a row of Queries\LessonList): times in the
 * viewer's zone, labels in their language, and what they may do with it.
 * A family sees the teacher; a teacher, the student. A group lesson says
 * so (format, group): the family reads its own seat and its classmates'
 * first names only (the kids' look, how many), the teacher everyone seated.
 */
final class LessonPresenter
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /**
     * @param  'teacher'|'student'  $counterpart  whom the lesson is with, as the viewer sees it
     * @param  bool  $mayAct  the viewer may cancel or refuse (not a child in the kids' look, not a teacher); a teacher may still dispute
     * @param  bool  $kids  the viewer is in the kids' look: classmates as a number, never names
     * @return array<string, mixed>
     */
    public function lesson(object $r, CarbonImmutable $now, string $counterpart = 'teacher', bool $mayAct = false, bool $kids = false): array
    {
        $group = ($r->group_id ?? null) !== null;
        // A group lesson, for a student: what their own seat came to (attended, absent, excused, a makeup owed).
        $seat = $group && $counterpart === 'teacher' ? ($r->seat_outcome ?? null) : null;
        $seatOpen = ! $group || $counterpart !== 'teacher' || (($r->seat_active ?? true) && $seat === null);
        $l = 'scheduling::family.';
        $open = in_array($r->status, ['scheduled', 'in_progress'], true);
        $opensAt = $r->starts_at->subMinutes((int) config('classroom.opens_before_min', 10));
        $joinable = $open && $now->greaterThanOrEqualTo($opensAt)
            && $now->lessThanOrEqualTo($r->ends_at->addMinutes((int) config('classroom.closes_after_min', 10)));
        $ahead = $r->status === 'scheduled' && $r->starts_at->greaterThan($now);
        $substitute = $r->original_teacher ?? null;
        // A dispute is shown to the side that raised it; the other side sees only the outcome it led to.
        $side = $counterpart === 'teacher' ? 'family' : 'teacher';
        $dispute = ($mayAct || $side === 'teacher') && ($r->dispute_side ?? null) === $side ? $r : null;
        $disputable = $seat !== null ? (GroupOutcomes::AS_STATUS[$seat] ?? null) : $r->status;
        $canDispute = ($mayAct || $side === 'teacher') && ($r->dispute_status ?? null) === null && $disputable !== null
            && (DisputeRules::open($r->status, $r->ends_at, $now) || ($seat !== null && $r->status === 'needs_review' && DisputeRules::open($disputable, $r->ends_at, $now)));

        return [
            'id' => $r->id,
            'starts_at' => $this->time->present($r->starts_at),
            'ends_at' => $this->time->present($r->ends_at),
            'minutes' => (int) round($r->starts_at->diffInMinutes($r->ends_at)),
            'subject' => app()->getLocale() === 'ar' ? $r->subject_ar : $r->subject_en,
            /** The teacher's name (family) or the student's (teacher; a group lesson reads "group · n"). */
            'with' => $group && $counterpart === 'student' ? __('scheduling::group_outcomes.label', ['count' => (int) $r->students]) : $r->{$counterpart},
            /** private (one student) or group */
            'format' => $group ? 'group' : 'private',
            'format_label' => (string) __('scheduling::group_outcomes.format.'.($group ? 'group' : 'private')),
            /** A group lesson: its seats, and who else is in it as the viewer may see them; null for a private lesson. */
            'group' => $group ? $this->group($r, $counterpart, $kids, $seat) : null,
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
            /** A group lesson's "I won't attend": the learner's own seat is excused, never the lesson cancelled. */
            'can_cancel' => $mayAct && $counterpart === 'teacher' && $ahead && $seatOpen,
            'can_refuse_substitute' => $mayAct && $counterpart === 'teacher' && $substitute !== null && $ahead,
            /** Why the academy cancelled it (a holiday, the teacher's leave, the course paused or ended), when it did. */
            'cancelled_because' => $r->status === 'cancelled' && in_array($r->cancel_reason ?? null, ['holiday', 'teacher_leave', 'substitute_refused', 'enrollment_paused', 'enrollment_ended', 'group_ended'], true)
                ? __('scheduling::timeoff.family.why.'.$r->cancel_reason) : null,
            /** The viewer's own dispute of this lesson's outcome, and the academy's answer once decided. */
            'dispute' => $dispute === null ? null : [
                /** open, upheld, rejected */
                'status' => $dispute->dispute_status,
                'status_label' => __('scheduling::disputes.statuses.'.$dispute->dispute_status),
                'reason' => __("scheduling::disputes.reasons.{$side}.{$dispute->dispute_reason}"),
                'answer' => $dispute->dispute_status === 'open' ? null : $dispute->dispute_answer,
            ],
            /** Decided and within 48 hours of its end: POST …/lessons/{id}/dispute with one of dispute_reasons and a note. */
            'can_dispute' => $canDispute,
            'dispute_until' => $canDispute ? $this->time->present(DisputeRules::closesAt($r->ends_at)) : null,
            /** The reasons that fit what the lesson was, each with its label. */
            'dispute_reasons' => $canDispute ? array_map(fn (string $k) => ['key' => $k, 'label' => __("scheduling::disputes.reasons.{$side}.{$k}")], DisputeRules::reasons($side, (string) $disputable)) : [],
        ];
    }

    /**
     * @return array{capacity: int, seats_taken: int, seat_number: int|null, classmates: list<string>|null, classmates_count: int|null, classmates_label: string|null, seat: string|null, seat_label: string|null, students: list<array{id: string, name: string}>|null}
     */
    private function group(object $r, string $counterpart, bool $kids, ?string $seat): array
    {
        $family = $counterpart === 'teacher';
        $classmates = $r->classmates ?? [];
        $students = [];
        foreach ($family ? [] : (array) ($r->roster ?? []) as $s) {
            $students[] = ['id' => (string) $s['id'], 'name' => (string) $s['name']];
        }

        return [
            /** Seats in the group. */
            'capacity' => (int) ($r->capacity ?? $r->students),
            /** Students seated in this lesson. */
            'seats_taken' => (int) $r->students,
            /** The family: the learner's seat in the group by when they joined ("seat 4 of 5": 4); null for the teacher. */
            'seat_number' => $family && ($r->seat_number ?? null) !== null ? (int) $r->seat_number : null,
            /** The family: classmates' first names (null in the kids' look, which reads only how many). */
            'classmates' => $family && ! $kids ? $classmates : null,
            'classmates_count' => $family ? count($classmates) : null,
            'classmates_label' => $family ? (string) trans_choice('scheduling::group_outcomes.with_classmates', count($classmates), ['count' => count($classmates)]) : null,
            /** The family: what the learner's own seat came to (attended, absent, excused, makeup), once decided. */
            'seat' => $family ? $seat : null,
            'seat_label' => $family && $seat !== null ? (string) __('scheduling::group_outcomes.outcomes.'.$seat) : null,
            /** The teacher: every student seated (id, name). */
            'students' => $family ? null : $students,
        ];
    }
}
