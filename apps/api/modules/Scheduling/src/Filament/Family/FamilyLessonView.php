<?php

namespace Modules\Scheduling\Filament\Family;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Domain\Disputes\DisputeRules;

/**
 * One lesson as a person in it reads it: on their clock, in their words,
 * with the way in once the classroom opens. A family sees the teacher's
 * name; a teacher, the student's. Disputes: whether the viewer may still
 * dispute it, and their own side's dispute with the academy's answer.
 */
final class FamilyLessonView
{
    public function __construct(
        private readonly CarbonImmutable $now,
        private readonly string $tz,
        private readonly string $counterpart = 'teacher',
        private readonly bool $disputes = true,
    ) {}

    /** @return array<string, mixed> */
    public function lesson(object $r): array
    {
        $l = 'scheduling::family.';
        $start = $r->starts_at->setTimezone($this->tz);
        $open = in_array($r->status, ['scheduled', 'in_progress'], true);
        $joinable = $open
            && $this->now->greaterThanOrEqualTo($r->starts_at->subMinutes((int) config('classroom.opens_before_min', 10)))
            && $this->now->lessThanOrEqualTo($r->ends_at->addMinutes((int) config('classroom.closes_after_min', 10)));

        $side = $this->counterpart === 'teacher' ? 'family' : 'teacher';
        $dispute = $this->disputes && ($r->dispute_side ?? null) === $side ? $r : null;

        return [
            'id' => $r->id,
            'day' => $this->day($start),
            'time' => $start->format('H:i').' – '.$r->ends_at->setTimezone($this->tz)->format('H:i'),
            'minutes' => (int) round($r->starts_at->diffInMinutes($r->ends_at)),
            'subject' => app()->getLocale() === 'ar' ? $r->subject_ar : $r->subject_en,
            'with' => $r->{$this->counterpart},
            'status' => $r->status,
            'statusLabel' => __($l.'statuses.'.$r->status),
            'type' => $r->type === 'regular' ? null : __($l.'types.'.$r->type),
            'unfunded' => $open && $r->funding_status === 'unfunded',
            'joinUrl' => $joinable && Route::has('classroom.page') ? route('classroom.page', ['session' => $r->id]) : null,
            'opensAt' => $open && ! $joinable && $r->starts_at->greaterThan($this->now)
                ? $r->starts_at->subMinutes((int) config('classroom.opens_before_min', 10))->setTimezone($this->tz)->format('H:i') : null,
            'cancellable' => $this->counterpart === 'teacher' && $r->status === 'scheduled' && $r->starts_at->greaterThan($this->now),
            // A substitute has the lesson: the family sees whose place they take, the substitute whom they cover.
            'substitute' => ($r->original_teacher ?? null) ? __($this->counterpart === 'teacher' ? 'scheduling::timeoff.family.substitute' : 'scheduling::timeoff.teacher.covering', ['name' => $r->original_teacher]) : null,
            'refusable' => $this->counterpart === 'teacher' && ($r->original_teacher ?? null) !== null && $r->status === 'scheduled' && $r->starts_at->greaterThan($this->now),
            'disputable' => $this->disputes && ($r->dispute_status ?? null) === null && DisputeRules::open($r->status, $r->ends_at, $this->now),
            'dispute' => $dispute === null ? null : [
                'status' => $dispute->dispute_status,
                'label' => __('scheduling::disputes.form.state', ['status' => __('scheduling::disputes.statuses.'.$dispute->dispute_status)]),
                'answer' => $dispute->dispute_status === 'open' ? null : $dispute->dispute_answer,
            ],
            'why' => $r->status === 'cancelled' && in_array($r->cancel_reason ?? null, ['holiday', 'teacher_leave', 'substitute_refused'], true)
                ? __('scheduling::timeoff.family.why.'.$r->cancel_reason) : null,
        ];
    }

    private function day(CarbonImmutable $start): string
    {
        $today = $this->now->setTimezone($this->tz)->startOfDay();
        $l = 'scheduling::family.';

        return match (true) {
            $start->isSameDay($today) => __($l.'today'),
            $start->isSameDay($today->addDay()) => __($l.'tomorrow'),
            $start->isSameDay($today->subDay()) => __($l.'yesterday'),
            default => $start->locale(app()->getLocale())->translatedFormat('l j F'),
        };
    }
}
