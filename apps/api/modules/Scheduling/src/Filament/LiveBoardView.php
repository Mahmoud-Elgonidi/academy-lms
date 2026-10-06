<?php

namespace Modules\Scheduling\Filament;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\LiveBoard\BoardEvent;
use Modules\Scheduling\Domain\LiveBoard\BoardGroup;
use Modules\Scheduling\Domain\LiveBoard\BoardIssue;
use Modules\Scheduling\Domain\LiveBoard\BoardItem;
use Modules\Scheduling\Domain\LiveBoard\BoardPerson;
use Modules\SharedKernel\Time\Timezones;

/**
 * Turns board items into plain arrays for the two layouts: every time is in
 * the viewer's chosen timezone, every string is translated, and each item
 * carries the actions that fit its problem (recommended one first).
 */
final class LiveBoardView
{
    public function __construct(
        private readonly CarbonImmutable $now,
        private readonly string $viewTz,
    ) {}

    /** @return array<string, mixed> */
    public function item(BoardItem $item): array
    {
        $s = $item->session;
        $minutesLeft = $item->minutesLeft($this->now);

        return [
            'id' => $s->id,
            'group' => $item->group->value,
            'issue' => $item->issue?->value,
            'severity' => $item->issue?->severity() ?? 0,
            'time' => $this->hm($s->startsAt).' – '.$this->hm($s->endsAt),
            'start' => $this->hm($s->startsAt),
            'timing' => $this->timing($item),
            'subject' => $s->subject(),
            'type' => $s->type->value,
            'typeLabel' => $s->type === SessionType::Regular ? null : __('scheduling::board.types.'.$s->type->value),
            'teacher' => $s->teacher->name,
            'teacherPhone' => $s->teacher->phone,
            'studentPhone' => $s->student?->phone,
            'teacherLine' => $this->personLine($s->teacher, $s->startsAt, true, $item),
            'student' => $s->student?->name ?? '—',
            'studentLine' => $s->student ? $this->personLine($s->student, $s->startsAt, false, $item) : '',
            'teacherDot' => $this->dot($s->teacher, $item),
            'studentDot' => $s->student ? $this->dot($s->student, $item) : 'none',
            'status' => $this->status($item),
            'problem' => $item->issue ? __('scheduling::board.issues.'.$item->issue->value.'.title', ['teacher' => $s->teacher->name, 'student' => $s->student?->name]) : null,
            'context' => $item->issue ? $this->context($item) : null,
            'rule' => $item->issue ? $this->rule($item) : null,
            'minutesLeft' => $minutesLeft,
            'handled' => $item->handledBy ? $this->eventText($item->handledBy).' · '.$this->hm($item->handledBy->at) : null,
            'progress' => $item->progressPercent(),
            'progressText' => $item->group === BoardGroup::Upcoming ? $this->timing($item) : __('scheduling::board.progress', ['done' => $item->minutesDone, 'total' => $s->durationMin()]),
            'checks' => array_map(fn (array $c) => ['label' => __('scheduling::board.checks.'.$c['key']), 'ok' => $c['ok']], $item->checks),
            'result' => $item->group === BoardGroup::Done ? $this->result($item) : null,
            'funding' => __('scheduling::board.funding.'.$s->funding->value),
            // "Booked" says nothing on the day; the log starts with who came in.
            'log' => array_values(array_map(
                fn (BoardEvent $e) => ['at' => $this->hm($e->at), 'text' => $this->eventText($e)],
                array_filter($s->events, fn (BoardEvent $e) => $e->kind !== SessionEventKind::Scheduled),
            )),
            'guardians' => array_map(fn (BoardPerson $g) => ['name' => $g->name, 'phone' => $g->phone, 'relationship' => __('scheduling::board.relationships.'.($g->relationship ?? 'parent'))], $s->guardians),
            'clocks' => array_values(array_filter([
                ['who' => __('scheduling::board.clocks.you'), 'time' => $this->hm($s->startsAt), 'city' => self::city($this->viewTz)],
                $s->student ? ['who' => __('scheduling::board.clocks.student'), 'time' => $this->hm($s->startsAt, $s->student->timezone), 'city' => self::city($s->student->timezone)] : null,
                ['who' => __('scheduling::board.clocks.teacher'), 'time' => $this->hm($s->startsAt, $s->teacher->timezone), 'city' => self::city($s->teacher->timezone)],
            ])),
            'actions' => $this->actions($item),
        ];
    }

    public function hm(CarbonImmutable $instant, ?string $tz = null): string
    {
        return $instant->setTimezone($tz ?? $this->viewTz)->format('H:i');
    }

    public static function city(string $tz): string
    {
        return Timezones::city($tz);
    }

    private function timing(BoardItem $item): string
    {
        $s = $item->session;

        if ($item->group === BoardGroup::Done || $s->status === SessionStatus::NeedsReview) {
            return __('scheduling::board.timing.ended', ['time' => $this->hm($s->endsAt)]);
        }
        if ($s->startsAt->greaterThan($this->now)) {
            return __('scheduling::board.timing.starts_in', ['min' => (int) ceil($this->now->diffInSeconds($s->startsAt) / 60)]);
        }

        return __('scheduling::board.timing.started_ago', ['min' => (int) floor($s->startsAt->diffInSeconds($this->now) / 60)]);
    }

    private function personLine(BoardPerson $p, CarbonImmutable $start, bool $isTeacher, BoardItem $item): string
    {
        $local = self::city($p->timezone).' '.$this->hm($start, $p->timezone);

        $state = match (true) {
            $p->joinedAt !== null => __('scheduling::board.person.joined', ['time' => $this->hm($p->joinedAt)]),
            $item->group === BoardGroup::Upcoming, $item->group === BoardGroup::Done => null,
            $item->session->startsAt->greaterThan($this->now) => null,
            default => __('scheduling::board.person.not_joined'),
        };

        return $state ? $local.' · '.$state : $local;
    }

    private function dot(BoardPerson $p, BoardItem $item): string
    {
        if ($item->group === BoardGroup::Upcoming || $item->group === BoardGroup::Done) {
            return 'none';
        }
        if ($p->joinedAt !== null) {
            return $item->issue === BoardIssue::WeakConnection ? 'warn' : 'ok';
        }

        return $item->session->startsAt->lessThanOrEqualTo($this->now) ? 'bad' : 'none';
    }

    /** @return array{key: string, label: string} */
    private function status(BoardItem $item): array
    {
        $s = $item->session;

        $key = match (true) {
            $s->status === SessionStatus::NeedsReview => 'review',
            $s->status === SessionStatus::Cancelled => 'cancelled',
            in_array($s->status, [SessionStatus::StudentAbsent, SessionStatus::TeacherAbsent], true) => 'absent',
            $s->status === SessionStatus::Completed => 'done',
            $item->issue === BoardIssue::TeacherNotJoined, $item->issue === BoardIssue::NobodyJoined => 'late',
            $item->issue === BoardIssue::StudentNotJoined => 'waiting',
            $item->issue === BoardIssue::Unfunded => 'unpaid',
            $item->issue === BoardIssue::WeakConnection => 'weak',
            $item->group === BoardGroup::Upcoming => 'upcoming',
            default => 'live',
        };

        return ['key' => $key, 'label' => __('scheduling::board.status.'.$key)];
    }

    private function context(BoardItem $item): string
    {
        $s = $item->session;
        $base = $s->subject().' · '.$s->teacher->name.' '.__('scheduling::shifts.arrow').' '.($s->student?->name ?? '—');

        return match ($item->issue) {
            BoardIssue::TeacherNotJoined => __('scheduling::board.issues.teacher_not_joined.context', ['base' => $base, 'time' => $this->hm($s->student?->joinedAt ?? $s->startsAt)]),
            BoardIssue::StudentNotJoined => __('scheduling::board.issues.student_not_joined.context', ['base' => $base, 'time' => $this->hm($s->teacher->joinedAt ?? $s->startsAt)]),
            BoardIssue::WeakConnection => __('scheduling::board.issues.weak_connection.context', ['base' => $base, 'count' => count($s->eventsOf(SessionEventKind::ConnectionDrop))]),
            BoardIssue::Unfunded => __('scheduling::board.issues.unfunded.context', ['base' => $base, 'time' => $this->hm($s->startsAt)]),
            BoardIssue::NeedsReview => __('scheduling::board.issues.needs_review.context', ['base' => $base, 'reason' => __('scheduling::board.review_reasons.'.($s->reviewReason ?? 'other'))]),
            default => $base,
        };
    }

    private function rule(BoardItem $item): string
    {
        $text = __('scheduling::board.issues.'.$item->issue->value.'.rule', ['student' => $item->session->student?->name]);

        return $item->deadline ? __('scheduling::board.auto_at', ['time' => $this->hm($item->deadline), 'what' => $text]) : $text;
    }

    private function result(BoardItem $item): string
    {
        $s = $item->session;

        return match ($s->status) {
            SessionStatus::Completed => __('scheduling::board.results.completed', ['min' => $item->minutesDone]),
            SessionStatus::StudentAbsent => __('scheduling::board.results.student_absent'),
            SessionStatus::TeacherAbsent => __('scheduling::board.results.teacher_absent'),
            SessionStatus::Cancelled => __('scheduling::board.results.cancelled'),
            default => __('scheduling::board.status.'.$s->status->value),
        };
    }

    public function eventText(BoardEvent $e): string
    {
        $d = $e->data;
        $actor = $e->actorName ?? __('scheduling::board.log.system');

        return match ($e->kind) {
            SessionEventKind::Scheduled => __('scheduling::board.log.scheduled'),
            SessionEventKind::Joined => __('scheduling::board.log.joined_'.($d['role'] ?? 'student')),
            SessionEventKind::Left => __('scheduling::board.log.left'),
            SessionEventKind::ConnectionDrop => __('scheduling::board.log.drop', ['seconds' => $d['seconds'] ?? 0]),
            SessionEventKind::ContactLogged => __('scheduling::board.log.contact', [
                'actor' => $actor,
                'target' => __('scheduling::board.targets.'.($d['target'] ?? 'teacher')),
                'outcome' => __('scheduling::board.outcomes.'.($d['outcome'] ?? 'no_answer')),
            ]),
            SessionEventKind::DecisionLogged => __('scheduling::board.log.decision_'.($d['decision'] ?? 'other'), ['actor' => $actor]),
            SessionEventKind::Extended => __('scheduling::board.log.extended', ['min' => $d['minutes'] ?? 0, 'actor' => $actor]),
            SessionEventKind::StatusChanged => __('scheduling::board.log.status', [
                'status' => __('scheduling::board.status_names.'.($d['to'] ?? 'scheduled')),
                'actor' => ($d['by'] ?? null) === 'system' ? __('scheduling::board.log.system') : $actor,
            ]),
            SessionEventKind::PolicyApplied => __('scheduling::board.log.policy', [
                'rule' => $d['decision']['rule'] ?? '?',
                'credit' => __('scheduling::policy.credit.'.($d['decision']['credit'] ?? 'none')),
            ]),
            SessionEventKind::Rescheduled => __('scheduling::board.log.rescheduled', [
                'actor' => $actor,
                'from' => isset($d['from']) ? $this->hm(CarbonImmutable::parse($d['from'])) : '—',
            ]),
            SessionEventKind::TeacherChanged => __('scheduling::board.log.teacher_changed', ['actor' => $actor]),
        };
    }

    /**
     * The actions that fit this item, recommended first. Each is a page action
     * name plus its arguments; "soon" ones show disabled until their module lands.
     *
     * @return list<array{name: string, args: array<string, mixed>, label: string, hint: string, danger?: bool, soon?: bool}>
     */
    private function actions(BoardItem $item): array
    {
        $s = $item->session;
        $id = $s->id;
        $a = fn (string $name, array $args, string $label, string $hint, bool $danger = false, bool $soon = false) => [
            'name' => $name, 'args' => ['session' => $id] + $args, 'label' => __('scheduling::board.actions.'.$label),
            'hint' => __('scheduling::board.hints.'.$hint, ['student' => $s->student?->name, 'teacher' => $s->teacher->name]),
            'danger' => $danger, 'soon' => $soon,
        ];

        return match (true) {
            $item->issue === BoardIssue::TeacherNotJoined => [
                $a('contact', ['target' => 'teacher'], 'call_teacher', 'call_teacher'),
                $a('cancel', ['by' => 'teacher', 'reason' => 'emergency'], 'cancel_makeup', 'cancel_makeup', true),
                $a('contact', ['target' => 'guardian'], 'reassure', 'reassure'),
                $a('soon', ['what' => 'substitute'], 'substitute', 'soon_substitute', soon: true),
            ],
            $item->issue === BoardIssue::NobodyJoined => [
                $a('contact', ['target' => 'teacher'], 'call_teacher', 'call_teacher'),
                $a('contact', ['target' => 'guardian'], 'call_guardian', 'call_guardian'),
                $a('cancel', ['by' => 'academy', 'reason' => 'other'], 'cancel_makeup', 'cancel_makeup', true),
            ],
            $item->issue === BoardIssue::StudentNotJoined => [
                $a('contact', ['target' => 'guardian'], 'call_guardian', 'call_guardian'),
                $a('markStudentAbsent', [], 'mark_student_absent', 'mark_student_absent', true),
                $a('soon', ['what' => 'resend_link'], 'resend_link', 'soon_messages', soon: true),
            ],
            $item->issue === BoardIssue::WeakConnection => [
                $a('contact', ['target' => 'teacher'], 'call_teacher', 'call_teacher_tech'),
                $a('flagTechnical', [], 'flag_technical', 'flag_technical'),
                $a('extend', [], 'extend', 'extend'),
            ],
            $item->issue === BoardIssue::Unfunded => [
                $a('allowUnfunded', [], 'allow_unfunded', 'allow_unfunded'),
                $a('cancel', ['by' => 'academy', 'reason' => 'unpaid'], 'cancel', 'cancel_unpaid', true),
                $a('soon', ['what' => 'payment_link'], 'payment_link', 'soon_billing', soon: true),
            ],
            $item->issue === BoardIssue::NeedsReview => [
                $a('decide', [], 'resolve', 'resolve'),
            ],
            $item->group === BoardGroup::Fine => [
                $a('contact', ['target' => 'teacher'], 'call_teacher', 'call_teacher_any'),
                $a('extend', [], 'extend', 'extend'),
                $a('flagTechnical', [], 'flag_technical', 'flag_technical'),
                $a('soon', ['what' => 'observe'], 'observe', 'soon_classroom', soon: true),
            ],
            $item->group === BoardGroup::Upcoming => array_values(array_filter([
                $a('contact', ['target' => 'teacher'], 'call_teacher', 'call_teacher_any'),
                $a('contact', ['target' => 'guardian'], 'call_guardian', 'call_guardian_any'),
                $s->funding === FundingStatus::Unfunded ? $a('soon', ['what' => 'payment_link'], 'payment_link', 'soon_billing', soon: true) : null,
                $a('cancel', ['by' => 'student', 'reason' => 'other'], 'cancel', 'cancel_any', true),
            ])),
            in_array($s->status, [SessionStatus::Completed, SessionStatus::StudentAbsent, SessionStatus::TeacherAbsent], true) => [
                $a('dispute', [], 'dispute', 'dispute'),
            ],
            default => [],
        };
    }
}
