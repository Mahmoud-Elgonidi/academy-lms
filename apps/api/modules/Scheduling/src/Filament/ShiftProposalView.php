<?php

namespace Modules\Scheduling\Filament;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Actions\Shifts\ShiftProposals;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftProposalComment;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\ProposalRules;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Queries\ShiftImpactQuery;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * What the shift proposals screen draws (docs/architecture/02, section 2,
 * "شاشة المراجعة"): the list grouped by status, one proposal (two weeks
 * side by side, conflicts, the conversation) and a teacher's schedules.
 * Reads only; every change goes through the Shifts Actions.
 */
final class ShiftProposalView
{
    /** The list's groups, in the order they show: what waits on the admins first. */
    public const GROUPS = ['review', 'teacher', 'approved', 'closed'];

    public function __construct(
        private readonly ShiftProposals $proposals,
        private readonly ShiftImpactQuery $impact,
    ) {}

    public static function group(ShiftProposalStatus $status): string
    {
        return match ($status) {
            ShiftProposalStatus::Submitted => 'review',
            ShiftProposalStatus::ChangesRequested, ShiftProposalStatus::Countered => 'teacher',
            ShiftProposalStatus::Approved => 'approved',
            default => 'closed',
        };
    }

    /**
     * Open proposals and those decided in the last 30 days, grouped by status.
     * Waiting on the admins: the oldest first (and late ones on top); the rest newest first.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function groups(): array
    {
        $replyHours = $this->proposals->settings()->proposal_reply_hours;
        $proposals = ShiftProposal::query()
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'shift_proposals.academy_id')->on('u.id', '=', 'shift_proposals.teacher_id'))
            ->where(fn ($q) => $q->whereIn('shift_proposals.status', ['submitted', 'changes_requested', 'countered'])
                ->orWhere('shift_proposals.updated_at', '>=', CarbonImmutable::now()->subDays(30)))
            ->where('shift_proposals.status', '!=', 'draft')
            ->orderByDesc('shift_proposals.updated_at')
            ->select('shift_proposals.*', 'u.name as teacher_name')
            ->with(['rules', 'baseSchedule.rules'])
            ->get();

        $rows = array_fill_keys(self::GROUPS, []);
        foreach ($proposals as $p) {
            $proposed = $p->shifts();
            $before = $this->before($p);
            $open = $p->status->isOpen();
            $conflicts = $open ? $this->impact->conflicts($p->teacher_id, $proposed, $p->effective_from) : [];
            $sent = $p->submitted_at ?? $p->created_at;
            $name = (string) $p->getAttribute('teacher_name');

            $rows[self::group($p->status)][] = [
                'id' => $p->id,
                'teacher_id' => $p->teacher_id,
                'teacher' => $name,
                'initial' => self::initial($name),
                // The proposal's hours are on this zone (the teacher's own).
                'zone' => LiveBoardView::city($p->timezone),
                'status' => $p->status->value,
                'status_label' => __('scheduling::shifts.statuses.'.$p->status->value),
                'sent' => $sent->locale(app()->getLocale())->diffForHumans(),
                'sent_at' => $sent->getTimestamp(),
                'overdue' => $p->status === ShiftProposalStatus::Submitted && (bool) $p->submitted_at?->addHours($replyHours)->isPast(),
                'from' => $this->day($p->effective_from),
                'summary' => $this->summary($before, $proposed),
                'hours_before' => ProposalRules::hours($before->weeklyMinutes()),
                'hours_after' => ProposalRules::hours($proposed->weeklyMinutes()),
                'delta' => $proposed->weeklyMinutes() - $before->weeklyMinutes(),
                'delta_hours' => ProposalRules::hours(abs($proposed->weeklyMinutes() - $before->weeklyMinutes())),
                'days7' => $this->days7($before, $proposed),
                'conflicts' => count($conflicts),
                'unresolved' => count(array_filter($conflicts, fn ($c) => ! isset($p->resolutions[$c['key']]))),
            ];
        }

        usort($rows['review'], fn (array $a, array $b) => [$b['overdue'], $a['sent_at']] <=> [$a['overdue'], $b['sent_at']]);

        return $rows;
    }

    /** One proposal, as the slide-over draws it. @return array<string, mixed>|null */
    public function proposal(string $id): ?array
    {
        $p = ShiftProposal::query()->with(['rules', 'comments', 'baseSchedule.rules'])->find($id);
        if ($p === null) {
            return null;
        }
        $teacher = DB::table('users')->where('id', $p->teacher_id)->first(['name']);
        $proposed = $p->shifts();
        $before = $this->before($p);
        $today = CarbonImmutable::now($p->timezone)->startOfDay();
        $earliest = $this->proposals->rules()->earliestStart($proposed, $before, $today);
        [, $removed] = $proposed->addedAndRemovedMinutes($before);

        $conflicts = array_map(fn (array $c) => $c + [
            'resolution' => $p->resolutions[$c['key']]['kind'] ?? null,
            'subject' => app()->getLocale() === 'ar' ? $c['subject_ar'] : $c['subject_en'],
            'teacher_label' => __('scheduling::shifts.days.'.$c['weekday']).' '.Clock::wall($c['start']).' '.LiveBoardView::city($p->timezone),
            'student_label' => $c['first']->setTimezone($c['student_tz'])->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i')).' '.LiveBoardView::city($c['student_tz']),
            'range' => $c['first']->locale(app()->getLocale())->translatedFormat('j M').' → '.$c['last']->locale(app()->getLocale())->translatedFormat('j M'),
            // Moving the student to another teacher happens on their account; the conflict then goes away.
            'move_url' => $c['enrollment_id'] ? route('filament.academy.resources.enrollments.view', ['record' => $c['enrollment_id'], 'reassign' => $p->effective_from->toDateString()]) : null,
        ], $p->status->isOpen() ? $this->impact->conflicts($p->teacher_id, $proposed, $p->effective_from) : []);
        $unresolved = count(array_filter($conflicts, fn ($c) => $c['resolution'] === null));
        $timeline = $this->timeline($p, $teacher->name ?? '');

        return [
            'id' => $p->id,
            'teacher_id' => $p->teacher_id,
            'teacher' => $teacher->name ?? '—',
            'city' => LiveBoardView::city($p->timezone),
            'tz' => $p->timezone,
            'status' => $p->status,
            'revision' => $p->revision,
            'author' => $p->rules->where('revision', $p->revision)->first()?->author_role ?? $p->author_role,
            'from' => $this->day($p->effective_from),
            'hours_before' => ProposalRules::hours($before->weeklyMinutes()),
            'hours_after' => ProposalRules::hours($proposed->weeklyMinutes()),
            'minutes_before' => $before->weeklyMinutes(),
            'minutes_after' => $proposed->weeklyMinutes(),
            'delta' => $proposed->weeklyMinutes() - $before->weeklyMinutes(),
            'delta_hours' => ProposalRules::hours(abs($proposed->weeklyMinutes() - $before->weeklyMinutes())),
            'reduces' => $removed > 0,
            'notice_days' => $this->proposals->settings()->reduction_notice_days,
            'notice_ok' => ! $p->effective_from->lessThan($earliest),
            'week' => $this->compare($before, $proposed),
            'conflicts' => $conflicts,
            'unresolved' => $unresolved,
            'timeline' => $timeline,
            'messages' => count(array_filter($timeline, fn (array $m) => $m['type'] === 'message')),
            // The slide-over opens on the conflicts while one still needs a decision.
            'pane' => $unresolved > 0 ? 'conflicts' : 'compare',
        ];
    }

    /**
     * A teacher's schedules: the one in force today and an approved one that starts later.
     *
     * @return array<string, mixed>
     */
    public function teacher(string $teacherId): array
    {
        $tz = $this->proposals->teacherTimezone($teacherId);
        $today = CarbonImmutable::now($tz)->startOfDay();
        $current = $this->proposals->scheduleOn($teacherId, $today, $tz);
        $upcoming = $this->proposals->upcoming($teacherId, $today);
        $open = $this->proposals->openOrDraft($teacherId);

        return [
            'id' => $teacherId,
            'name' => DB::table('users')->where('id', $teacherId)->value('name') ?? '—',
            'city' => LiveBoardView::city($tz),
            'tz' => $tz,
            'current_hours' => ProposalRules::hours($current->weeklyMinutes()),
            'upcoming' => $upcoming ? [
                'from' => $this->day($upcoming->effectiveFrom()),
                'hours' => ProposalRules::hours($upcoming->shifts()->weeklyMinutes()),
            ] : null,
            'week' => $this->compare($current, $upcoming?->shifts() ?? $current),
            'open' => $open !== null && $open->status->isOpen() ? [
                'id' => $open->id,
                'status' => $open->status->value,
                'status_label' => __('scheduling::shifts.statuses.'.$open->status->value),
            ] : null,
        ];
    }

    /**
     * Every teacher with their weekly hours now and what is approved to come.
     *
     * @return list<array<string, mixed>>
     */
    public function teachers(): array
    {
        $teachers = DB::table('users as u')
            ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('r.role', 'teacher')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.timezone']);
        $schedules = ShiftSchedule::query()->whereIn('teacher_id', $teachers->pluck('id'))->where('status', 'active')
            ->with('rules')->orderByRaw('lower(effective)')->get()->groupBy('teacher_id');
        $open = ShiftProposal::query()->whereIn('teacher_id', $teachers->pluck('id'))
            ->whereIn('status', ['submitted', 'changes_requested', 'countered'])->get()->keyBy('teacher_id');
        $fallback = app(TenantContext::class)->require()->defaultTimezone;

        return $teachers->map(function ($t) use ($schedules, $open, $fallback) {
            $tz = $t->timezone ?? $fallback;
            $today = CarbonImmutable::now($t->timezone ?? $fallback)->toDateString();
            $current = $upcoming = null;
            foreach ($schedules->get($t->id, collect()) as $s) {
                $from = $s->effectiveFrom()->toDateString();
                $until = trim(explode(',', $s->effective)[1] ?? '', ')] ');
                if ($from <= $today && ($until === '' || $until > $today)) {
                    $current = $s;
                } elseif ($from > $today) {
                    $upcoming = $s;
                }
            }
            $proposal = $open->get($t->id);
            $inForce = $current?->shifts() ?? WeeklyShifts::empty($tz);

            return [
                'id' => $t->id,
                'name' => $t->name,
                'initial' => self::initial((string) $t->name),
                'zone' => LiveBoardView::city($tz),
                'current_hours' => $current ? ProposalRules::hours($current->shifts()->weeklyMinutes()) : null,
                'days' => $current ? count(array_unique(array_map(fn (ShiftBlock $b) => $b->weekday, $current->shifts()->blocks))) : 0,
                // The schedule in force, day by day: working or off.
                'days7' => $this->days7($inForce),
                'upcoming' => $upcoming ? [
                    'from' => $this->day($upcoming->effectiveFrom()),
                    'hours' => ProposalRules::hours($upcoming->shifts()->weeklyMinutes()),
                ] : null,
                'open' => $proposal ? ['id' => $proposal->id, 'status' => $proposal->status->value, 'status_label' => __('scheduling::shifts.statuses.'.$proposal->status->value)] : null,
            ];
        })->values()->all();
    }

    /**
     * Two weeks side by side, day by day, each block marked: unchanged, added,
     * removed, or changed (the day has time on both sides, not the same).
     *
     * @return list<array{day: int, label: string, before: list<array{label: string, kind: string}>, after: list<array{label: string, kind: string}>, changed: bool}>
     */
    public function compare(WeeklyShifts $before, WeeklyShifts $after): array
    {
        $days = app()->getLocale() === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7];
        $key = fn (ShiftBlock $b) => $b->start().'-'.$b->end();
        $label = fn (ShiftBlock $b) => Clock::wall($b->start()).' – '.Clock::wall($b->end());
        $rows = [];

        foreach ($days as $day) {
            $was = $this->sorted($before->on($day));
            $now = $this->sorted($after->on($day));
            $wasKeys = array_map($key, $was);
            $nowKeys = array_map($key, $now);

            $rows[] = [
                'day' => $day,
                'label' => __('scheduling::shifts.days.'.$day),
                'before' => array_map(fn (ShiftBlock $b) => ['label' => $label($b), 'kind' => match (true) {
                    in_array($key($b), $nowKeys, true) => 'same',
                    $now !== [] => 'changed',
                    default => 'removed',
                }], $was),
                'after' => array_map(fn (ShiftBlock $b) => ['label' => $label($b), 'kind' => match (true) {
                    in_array($key($b), $wasKeys, true) => 'same',
                    $was !== [] => 'changed',
                    default => 'added',
                }], $now),
                'changed' => $wasKeys !== $nowKeys,
            ];
        }

        return $rows;
    }

    /** One line: hours a week before and after, and the days added, removed or changed. */
    public function summary(WeeklyShifts $before, WeeklyShifts $after): array
    {
        $parts = ['added' => [], 'removed' => [], 'changed' => []];
        foreach ($this->compare($before, $after) as $row) {
            if (! $row['changed']) {
                continue;
            }
            $kind = match (true) {
                $row['before'] === [] => 'added',
                $row['after'] === [] => 'removed',
                default => 'changed',
            };
            $parts[$kind][] = $row['label'];
        }

        return [
            'hours' => __('scheduling::review.summary.hours', [
                'before' => ProposalRules::hours($before->weeklyMinutes()),
                'after' => ProposalRules::hours($after->weeklyMinutes()),
                'arrow' => __('scheduling::shifts.arrow'),
            ]),
            'days' => array_values(array_filter(array_map(
                fn (string $kind) => $parts[$kind] === [] ? null : ['kind' => $kind, 'text' => __('scheduling::review.summary.'.$kind, ['days' => implode('، ', $parts[$kind])])],
                ['added', 'removed', 'changed'],
            ))),
            'same' => $parts === ['added' => [], 'removed' => [], 'changed' => []],
        ];
    }

    /**
     * The week in seven cells, in the order the comparison reads (Saturday
     * first in Arabic): each day added, removed, changed, the same, or off on
     * both sides, with its times for a tooltip. With one week ($after null),
     * each day is a working day ("on") or off.
     *
     * @return list<array{day: int, short: string, name: string, kind: string, title: string}>
     */
    public function days7(WeeklyShifts $before, ?WeeklyShifts $after = null): array
    {
        $single = $after === null;

        return array_map(function (array $row) use ($single): array {
            $kind = match (true) {
                $row['before'] === [] && $row['after'] === [] => 'off',
                $single => 'on',
                ! $row['changed'] => 'same',
                $row['before'] === [] => 'added',
                $row['after'] === [] => 'removed',
                default => 'changed',
            };
            $times = array_column($kind === 'removed' ? $row['before'] : $row['after'], 'label');

            return [
                'day' => $row['day'],
                'short' => __('scheduling::review.days_short.'.$row['day']),
                'name' => $row['label'],
                'kind' => $kind,
                'title' => implode(' · ', array_filter([$row['label'], __('scheduling::review.day_kinds.'.$kind), implode('، ', $times)])),
            ];
        }, $this->compare($before, $after ?? $before));
    }

    /** The first letter of a name, for the round sign beside it. */
    public static function initial(string $name): string
    {
        // A title before the name ("أ.", "Dr.") is skipped.
        $words = array_values(array_filter(preg_split('/\s+/u', trim($name)) ?: [], fn (string $w) => $w !== '' && ! str_ends_with($w, '.')));
        $first = $words[0] ?? trim($name);

        return $first === '' ? '?' : mb_strtoupper(mb_substr($first, 0, 1));
    }

    /** The week a proposal replaces: for an open one, what is approved for its start date; for a decided one, what it was based on. */
    private function before(ShiftProposal $p): WeeklyShifts
    {
        if ($p->status->isOpen()) {
            return $this->proposals->scheduleOn($p->teacher_id, $p->effective_from, $p->timezone);
        }

        return $p->baseSchedule?->shifts() ?? WeeklyShifts::empty($p->timezone);
    }

    /**
     * Messages as bubbles and the proposal's steps as small dividers, in time order.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(ShiftProposal $p, string $teacherName): array
    {
        $names = DB::table('users')->whereIn('id', $p->comments->pluck('author_id')->filter()->unique()->values())->pluck('name', 'id');
        $items = [];

        if ($p->submitted_at !== null) {
            $items[] = $this->event($p->author_role === 'admin' ? 'started' : 'submitted', $p->submitted_at);
        }
        foreach ($p->comments as $c) {
            /** @var ShiftProposalComment $c */
            $items[] = $c->author_role === 'system'
                ? ['type' => 'event', 'event' => null, 'text' => $c->body, 'at' => $this->when($c->created_at), 'ts' => $c->created_at->getTimestamp()]
                : [
                    'type' => 'message',
                    'role' => $c->author_role,
                    'author' => $c->author_role === 'teacher' ? $teacherName : ($names[$c->author_id] ?? ''),
                    'role_label' => __('scheduling::review.roles.'.$c->author_role),
                    'body' => $c->body,
                    'at' => $this->when($c->created_at),
                    'ts' => $c->created_at->getTimestamp(),
                ];
        }
        $last = match ($p->status) {
            ShiftProposalStatus::Countered => $this->event('countered', $p->updated_at),
            ShiftProposalStatus::ChangesRequested => $p->decided_at ? $this->event('changes_requested', $p->decided_at) : null,
            ShiftProposalStatus::Rejected => $p->decided_at ? $this->event('rejected', $p->decided_at) : null,
            default => null,
        };
        if ($last !== null) {
            $items[] = $last;
        }

        usort($items, fn (array $a, array $b) => $a['ts'] <=> $b['ts']);

        return $items;
    }

    /** @return array<string, mixed> */
    private function event(string $event, mixed $at): array
    {
        $at = CarbonImmutable::parse($at);

        return ['type' => 'event', 'event' => $event, 'text' => __('scheduling::review.events.'.$event), 'at' => $this->when($at), 'ts' => $at->getTimestamp()];
    }

    private function when(CarbonImmutable $at): string
    {
        return $at->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat(Clock::pattern('j M · H:i'));
    }

    private function day(CarbonImmutable $date): string
    {
        return $date->locale(app()->getLocale())->translatedFormat('l j F');
    }

    /** @param list<ShiftBlock> $blocks @return list<ShiftBlock> */
    private function sorted(array $blocks): array
    {
        usort($blocks, fn (ShiftBlock $a, ShiftBlock $b) => $a->startMin <=> $b->startMin);

        return $blocks;
    }
}
