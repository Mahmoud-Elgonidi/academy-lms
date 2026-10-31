<?php

namespace Modules\Scheduling\Queries\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\AgeGroup;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * The academy's groups for the back office (Scheduling › Groups; docs/
 * architecture/02, "الحصص الجماعية"): each with its subject, teacher,
 * weekly times on the viewer's clock, seats, status, lessons held and the
 * next one, read each time; and one group's members (first name and the
 * initial of the last, the day they joined, the lessons they came to) and
 * its next lessons. Names only, never a contact.
 */
final class GroupBoard
{
    public function __construct(
        private readonly CourseDays $days,
        private readonly LocalTimeResolver $resolver,
        private readonly LocalTimePresenter $times,
    ) {}

    /**
     * @return array{groups: list<array<string, mixed>>, counts: array{running: int, forming: int, ended: int, free: int}}
     */
    public function list(string $zone, ?string $subjectId = null, ?string $teacherId = null, ?string $status = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $this->days->today($now)->toDateString();
        $all = LessonGroup::query()
            ->withCount(['members as taken' => fn ($q) => $q->where(fn ($w) => $w->whereNull('left_on')->orWhere('left_on', '>', $today))])
            ->get();
        $live = $all->where('status', '!=', LessonGroup::ENDED);
        $counts = [
            'running' => $all->where('status', LessonGroup::RUNNING)->count(),
            'forming' => $all->where('status', LessonGroup::FORMING)->count(),
            'ended' => $all->where('status', LessonGroup::ENDED)->count(),
            'free' => (int) $live->sum(fn (LessonGroup $g) => max(0, $g->capacity - (int) $g->getAttribute('taken'))),
        ];

        $shown = $all
            ->when($subjectId !== null, fn ($c) => $c->where('subject_id', $subjectId))
            ->when($teacherId !== null, fn ($c) => $c->where('teacher_id', $teacherId))
            ->when($status !== null, fn ($c) => $c->where('status', $status), fn ($c) => $c->where('status', '!=', LessonGroup::ENDED))
            // Running first, then forming, then ended; the newest first in each.
            ->sortBy([fn (LessonGroup $a, LessonGroup $b) => self::order($a->status) <=> self::order($b->status), fn (LessonGroup $a, LessonGroup $b) => $b->created_at <=> $a->created_at])
            ->values();

        return ['groups' => $this->cards($shown->all(), $zone, $now), 'counts' => $counts];
    }

    /** One group as a card (the page's header). */
    public function card(LessonGroup $group, string $zone, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $this->days->today($now)->toDateString();
        $group->setAttribute('taken', $group->seatsTaken($today));

        return $this->cards([$group], $zone, $now)[0];
    }

    /**
     * The members holding a seat (and those who left, after them): their
     * names as classmates see them, the day they joined, the lessons they came to.
     *
     * @return list<array{id: string, student_id: string, enrollment_id: string, name: string, initial: string, joined: string, left: ?string, attended: int, live: bool}>
     */
    public function members(LessonGroup $group, ?CarbonImmutable $now = null): array
    {
        $today = $this->days->today($now)->toDateString();
        $members = GroupMember::query()->where('group_id', $group->id)->orderBy('joined_on')->orderBy('created_at')->get();
        if ($members->isEmpty()) {
            return [];
        }
        $names = DB::table('users')->whereIn('id', $members->pluck('student_id'))->pluck('name', 'id');
        // A seat counts as come when its lesson was taught and the student was not marked away.
        $attended = DB::table('session_participants as p')
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'p.academy_id')->on('s.id', '=', 'p.session_id'))
            ->where('s.group_id', $group->id)->whereIn('p.user_id', $members->pluck('student_id'))->where('p.role', 'student')
            ->where('s.status', SessionStatus::Completed->value)
            ->where(fn ($w) => $w->whereNull('p.outcome')->orWhere('p.outcome', 'attended'))
            ->groupBy('p.user_id')->selectRaw('p.user_id, count(*) as n')->pluck('n', 'p.user_id');

        return $members->map(function (GroupMember $m) use ($names, $attended, $today) {
            $name = self::classmateName((string) ($names[$m->student_id] ?? ''));

            return [
                'id' => $m->id,
                'student_id' => $m->student_id,
                'enrollment_id' => $m->enrollment_id,
                'name' => $name,
                'initial' => mb_substr($name, 0, 1),
                'joined' => $m->joined_on->locale(app()->getLocale())->translatedFormat('j F Y'),
                'left' => $m->left_on?->locale(app()->getLocale())->translatedFormat('j F Y'),
                'attended' => (int) ($attended[$m->student_id] ?? 0),
                'live' => $m->left_on === null || $m->left_on->toDateString() > $today,
            ];
        })->sortBy(fn (array $m) => $m['live'] ? 0 : 1)->values()->all();
    }

    /**
     * The group's next booked lessons, with the students seated in each.
     *
     * @return list<array{id: string, at: array, seated: int}>
     */
    public function upcoming(LessonGroup $group, string $zone, int $limit = 6, ?CarbonImmutable $now = null): array
    {
        return Session::query()->where('group_id', $group->id)->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>', $now ?? CarbonImmutable::now())->orderBy('starts_at')->limit($limit)
            ->withCount(['participants as seated' => fn ($q) => $q->where('role', 'student')])
            ->get()
            ->map(fn (Session $s) => ['id' => $s->id, 'at' => $this->times->present($s->starts_at, $zone), 'seated' => (int) $s->getAttribute('seated')])
            ->all();
    }

    /** "عمر ش." — a first name and the initial of the last, as classmates see each other. */
    public static function classmateName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim(preg_replace('/^(أ\.|د\.)\s*/u', '', $name))) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }

        // The family name's own first letter, past the article ("الشريف": ش).
        $last = (string) end($parts);
        $last = mb_strlen($last) > 3 && str_starts_with($last, 'ال') ? mb_substr($last, 2) : $last;

        return $parts[0].' '.mb_substr($last, 0, 1).'.';
    }

    /**
     * @param  list<LessonGroup>  $groups  each with its seats taken as "taken"
     * @return list<array<string, mixed>>
     */
    private function cards(array $groups, string $zone, CarbonImmutable $now): array
    {
        if ($groups === []) {
            return [];
        }
        $ids = array_map(fn (LessonGroup $g) => $g->id, $groups);
        $locale = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';
        $teachers = DB::table('users')->whereIn('id', array_unique(array_map(fn (LessonGroup $g) => $g->teacher_id, $groups)))->pluck('name', 'id');
        $subjects = DB::table('subjects')->whereIn('id', array_unique(array_map(fn (LessonGroup $g) => $g->subject_id, $groups)))->pluck($locale, 'id');
        $levelIds = array_values(array_filter(array_unique(array_map(fn (LessonGroup $g) => $g->level_id, $groups))));
        $levels = $levelIds === [] ? collect() : DB::table('subject_levels')->whereIn('id', $levelIds)->pluck($locale, 'id');
        $slots = RecurringSlot::query()->whereIn('group_id', $ids)->where('status', 'active')->get()->groupBy('group_id');
        $held = Session::query()->whereIn('group_id', $ids)->whereIn('status', [SessionStatus::Completed->value, SessionStatus::StudentAbsent->value])
            ->groupBy('group_id')->selectRaw('group_id, count(*) as n')->pluck('n', 'group_id');
        $next = Session::query()->whereIn('group_id', $ids)->where('status', SessionStatus::Scheduled->value)->where('starts_at', '>', $now)
            ->groupBy('group_id')->selectRaw('group_id, min(starts_at) as at')->pluck('at', 'group_id');

        return array_map(fn (LessonGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'teacher_id' => $g->teacher_id,
            'teacher' => (string) ($teachers[$g->teacher_id] ?? ''),
            'subject_id' => $g->subject_id,
            'subject' => (string) ($subjects[$g->subject_id] ?? ''),
            'level' => $g->level_id === null ? null : ($levels[$g->level_id] ?? null),
            'age' => $g->age_group === null ? null : AgeGroup::tryFrom($g->age_group)?->label(),
            'status' => $g->status,
            'capacity' => $g->capacity,
            'taken' => (int) $g->getAttribute('taken'),
            'free' => max(0, $g->capacity - (int) $g->getAttribute('taken')),
            'min_to_start' => $g->min_to_start,
            'minutes' => $g->session_minutes,
            'per_week' => $g->sessions_per_week,
            'times' => $slots->get($g->id, collect())->map(fn (RecurringSlot $s) => self::onClock($this->resolver, $s, $zone, $now))
                ->sortBy(fn ($t) => $t['weekday'] * 1440 + (int) substr($t['start'], 0, 2) * 60 + (int) substr($t['start'], 3, 2))->values()->all(),
            'held' => (int) ($held[$g->id] ?? 0),
            'next' => isset($next[$g->id]) ? $this->times->present(CarbonImmutable::parse($next[$g->id]), $zone) : null,
            'started_on' => $g->started_on?->locale(app()->getLocale())->translatedFormat('j F'),
            'ended_on' => $g->ended_on?->locale(app()->getLocale())->translatedFormat('j F'),
        ], $groups);
    }

    /** @return array{weekday: int, start: string} a weekly time on another clock, as its next lesson falls there */
    public static function onClock(LocalTimeResolver $resolver, RecurringSlot $slot, string $zone, CarbonImmutable $now): array
    {
        $from = $now->setTimezone($slot->anchor_tz)->startOfDay()->max($slot->startsOn());
        $date = $slot->weeklyTime()->datesBetween($from, $from->addDays(7))[0] ?? $from->toDateString();
        $at = $resolver->resolve($date, $slot->weeklyTime()->clock(), $slot->anchor_tz)->utc->setTimezone($zone);

        return ['weekday' => $at->dayOfWeekIso, 'start' => $at->format('H:i')];
    }

    private static function order(string $status): int
    {
        return match ($status) {
            LessonGroup::RUNNING => 0,
            LessonGroup::FORMING => 1,
            default => 2,
        };
    }
}
