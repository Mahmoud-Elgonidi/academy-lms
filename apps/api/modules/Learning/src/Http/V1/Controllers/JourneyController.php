<?php

namespace Modules\Learning\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Learning\Actions\OpenWeeklyChest;
use Modules\Learning\Contracts\DTOs\JourneyGamesLayer;
use Modules\Learning\Domain\Journey\Cheers;
use Modules\Learning\Domain\Journey\Stars;
use Modules\Learning\Models\Cheer;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Queries\Hifz\HifzMap;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A learner's journey for the apps and the portal (docs/architecture/02,
 * section 18): their academy's curriculum as islands and stations, the
 * missions to do now, the stars and the week's chest, and the games on each
 * lesson they may practise (when the academy has games). The student, or a
 * guardian looking at their child; only the student opens the chest.
 */
final class JourneyController
{
    public function __construct(
        private readonly FamilyView $family,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * A learner's journey.
     *
     * For each course: its curriculum's levels as islands (done, here or ahead, each drawn as one of the world's
     * islands) and its lessons as stations on them (done, review, here or todo; playable when the learner may
     * practise it now), with the games that can play the lesson's own practice items. The missions to do now: the
     * next lesson (with when the classroom opens), the teacher's homework and lessons due for review, each on its
     * station. The stars (lessons by lateness and staying to the end, practice, homework, mastered lessons, chests),
     * the rank, the egg and the week's chest. style: playful, calm (no islands) or plain (no stars).
     */
    public function map(Request $request, JourneyMap $journey): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        [$me, $learner] = $this->learner($request);
        $self = $me === $learner;
        $stage = $this->family->stage($learner)->value;
        $j = $journey->for($learner, $stage, ! $self);
        $l = 'learning::map.';

        return response()->json(['data' => [
            'learner_id' => $learner,
            'stage' => $j['stage'],
            /** playful (each stage's look, islands and games), calm (levels and missions) or plain (no stars) */
            'style' => $j['style'],
            /** self: the student; guardian: a guardian looking at their child */
            'viewer' => $self ? 'self' : 'guardian',
            'stars' => [
                'total' => $j['stars']['total'],
                'rank' => $j['stars']['rank'] + [
                    'label' => __('learning::journey.ranks.'.$j['stars']['rank']['key']),
                    'next_label' => $j['stars']['rank']['next_key'] ? __('learning::journey.ranks.'.$j['stars']['rank']['next_key']) : null,
                ],
                /** Where the stars came from: lessons (by lateness, and staying to the end), practice rounds, homework, lessons mastered, chests. */
                'from' => $j['stars']['from'],
                'per_egg' => Stars::PER_EGG,
            ],
            /** Lessons attended in a row. */
            'streak' => $j['streak'],
            /** The latest lesson of the last week and the stars it brought. */
            'last_lesson' => $j['last_lesson'] === null ? null : $this->lessonStars($j['last_lesson']),
            'courses' => array_map(fn (array $c) => [
                'enrollment_id' => $c['enrollment_id'],
                'subject' => $c['subject'],
                'teacher' => $c['teacher'],
                'program' => $c['program'],
                /** false: the course follows no published curriculum yet (its missions only). */
                'has_curriculum' => $c['has_curriculum'],
                'percent' => $c['percent'],
                'finished' => $c['finished'],
                'lessons' => $c['lessons'],
                'eta_weeks' => $c['eta_weeks'],
                'finish_on' => $this->time->present($c['finish_on']),
                'current' => $c['current'],
                /** pending_payment, active or paused (an ended course is not on the journey). */
                'status' => $c['status'],
                /** The pause's first day (it may lie ahead while the course is still active), on the academy's calendar. */
                'paused_from' => $this->time->academyDay($c['paused_from']),
                /** The pause's last day (included): lessons start again the day after. */
                'paused_until' => $this->time->academyDay($c['paused_until']),
                /** The first day without lessons, when the course is set to end. */
                'ends_on' => $this->time->academyDay($c['ends_on']),
                'islands' => array_map(fn (array $i) => [
                    'id' => $i['id'],
                    'index' => $i['index'],
                    'title' => $i['title'],
                    'percent' => $i['percent'],
                    /** done, here or ahead */
                    'state' => $i['state'],
                    'lessons' => $i['lessons'],
                    'done' => $i['done'],
                    /** The world island this level is drawn as (null without the games' world). */
                    'art' => $i['art'],
                    'creatures' => array_map(fn (array $cr) => ['found_at' => $this->time->present($cr['found_at'] ?? null)] + $cr, $i['creatures']),
                    'next_creature' => $i['next_creature'],
                    'stations' => array_map(fn (array $s) => [
                        'id' => $s['id'],
                        'title' => $s['title'],
                        'unit' => $s['unit'],
                        /** done, review (the teacher asked for more), here or todo */
                        'state' => $s['state'],
                        /** Mastered and due to be practised again. */
                        'due_review' => $s['due_review'],
                        /** The learner may practise it now (a lesson still ahead is not). */
                        'playable' => $s['playable'],
                        'practice' => $s['practice'],
                        /** The keys of the open missions on it. */
                        'missions' => $s['missions'],
                        /** The games that can play its items, best first (empty without games). */
                        'games' => $s['games'],
                        /** The race against yourself on it: rounds, best and last time in seconds, best stars. */
                        'race' => $s['race'],
                    ], $i['stations']),
                ], $c['islands']),
            ], $j['courses']),
            'missions' => array_map(fn (array $m) => $this->mission($m, $l), $j['missions']),
            'chest' => $j['chest'] === null ? null : [
                'period' => $j['chest']['period'],
                'target' => $j['chest']['target'],
                'progress' => $j['chest']['progress'],
                /** active, missed, done (ready to open, by the student) or opened */
                'state' => $j['chest']['state'],
                'stars' => $j['chest']['stars'],
                'sticker' => $j['chest']['sticker'] === null ? null : ['key' => $j['chest']['sticker'], 'name' => __('learning::challenges.stickers.'.$j['chest']['sticker'])],
                'ends_at' => $this->time->present($j['chest']['ends_at']),
                'can_open' => $self && $j['chest']['state'] === 'done',
            ],
            /** The kids' sticker album (one sticker for each week's chest opened); null in other looks and the plain style. */
            'stickers' => $j['stickers'] === null ? null : [
                'earned' => (int) $j['stickers']['earned'],
                'items' => array_map(fn (array $s) => $s + ['name' => __('learning::challenges.stickers.'.$s['key'])], $j['stickers']['items']),
            ],
            'games' => $j['games'] === null ? null : $this->games($j['games']),
            /** The hifz review today (GET learning/hifz has it in full): pages left today, done today and a day's pages; null until the learner has pages on their hifz map. */
            'hifz' => $this->hifzToday($learner),
            'cheers' => ! $self ? [] : Cheer::query()->where('student_id', $learner)->whereNull('seen_at')->orderBy('created_at')->limit(10)->get()
                ->map(fn (Cheer $c) => ['id' => $c->id, 'kind' => $c->kind, 'at' => $this->time->present($c->created_at)])->all(),
            /** For a guardian: cheers they may still send this learner today. */
            'cheers_left' => $self ? 0 : max(0, Cheers::PER_DAY - Cheer::query()->where('from_id', $me)->where('student_id', $learner)->where('created_at', '>', now()->subDay())->count()),
        ]]);
    }

    /**
     * Open the week's chest.
     *
     * The student opens it once every lesson of the week was attended and every homework due in it handed in (period:
     * the week's Saturday, as the journey lists it). Its stars add to theirs; the kids' chest brings the next sticker.
     * Once a week. Send an Idempotency-Key.
     *
     * @throws AccessDeniedHttpException only the student opens it
     */
    public function chest(Request $request, OpenWeeklyChest $open): JsonResponse
    {
        $request->validate(['period' => ['required', 'date_format:Y-m-d']]);
        $me = (string) $request->user()?->getAuthIdentifier();
        if ($this->family->learner($me, $me)?->id !== $me) {
            throw new AccessDeniedHttpException;
        }
        $r = $open->handle($me, $this->family->stage($me)->value, (string) $request->string('period'));

        return response()->json(['data' => [
            'stars' => $r['stars'],
            'sticker' => $r['sticker'] === null ? null : ['key' => $r['sticker'], 'name' => __('learning::challenges.stickers.'.$r['sticker'])],
            'total_before' => $r['total_before'],
            'total' => $r['total'],
            'ranked_up' => $r['rank']['key'] !== $r['rank_before'],
            'rank' => $r['rank'] + ['label' => __('learning::journey.ranks.'.$r['rank']['key'])],
        ]], 201);
    }

    /**
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>
     */
    private function mission(array $m, string $l): array
    {
        $out = [
            'key' => $m['key'],
            /** lesson, homework or review */
            'kind' => $m['kind'],
            'kind_label' => __($l.'missions.'.$m['kind']),
            /** open, late (homework past its due time), done */
            'state' => $m['state'],
            'enrollment_id' => $m['enrollment_id'],
            'island_id' => $m['island_id'],
            'station_id' => $m['station_id'],
            'title' => $m['title'],
        ];

        return $out + match ($m['kind']) {
            'lesson' => [
                'lesson_id' => $m['lesson_id'],
                'starts_at' => $this->time->present($m['starts_at']),
                'ends_at' => $this->time->present($m['ends_at']),
                /** Whether the classroom is open now, and when it opens. */
                'join' => ['open' => $m['join']['open'], 'opens_at' => $this->time->present($m['join']['opens_at'])],
                'stars' => $m['stars'],
            ],
            'homework' => [
                'homework_id' => $m['homework_id'],
                'due_at' => $this->time->present($m['due_at']),
                /** any, text, audio, file or practice (handed in by playing) */
                'accepts' => $m['accepts'],
                'practice' => $m['practice'],
                'game' => $m['game'],
                'result' => $m['result'],
                'stars' => $m['stars'],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $l  a lesson's stars (StarsQuery)
     * @return array<string, mixed>
     */
    private function lessonStars(array $l): array
    {
        $labels = [];
        if ($l['status'] === 'student_absent') {
            $labels[] = __('learning::map.lesson.missed');
        } elseif ($l['late_min'] === null) {
            $labels[] = __('learning::map.lesson.marked');
        } else {
            $labels[] = $l['on_time'] ? __('learning::map.lesson.on_time') : trans_choice('learning::map.lesson.late', $l['late_min'], ['n' => $l['late_min']]);
            if ($l['stayed']) {
                $labels[] = __('learning::map.lesson.stayed');
            }
        }

        return [
            'id' => $l['id'],
            'starts_at' => $this->time->present($l['starts_at']),
            'stars' => $l['stars'],
            'late_min' => $l['late_min'],
            'on_time' => $l['on_time'],
            'stayed' => $l['stayed'],
            'labels' => $labels,
        ];
    }

    /** @return array<string, mixed> */
    private function games(JourneyGamesLayer $g): array
    {
        return [
            /** Whether the learner may start a round now, and why not: off (the academy), paused (a guardian), night (a guardian's night lock), limit (today's minutes). */
            'allowance' => $g->allowance + ['reason_label' => $g->allowance['reason'] ? __('games::games.refused.'.$g->allowance['reason']) : null],
            /** Stars toward the next egg; ready: an egg can hatch now. */
            'egg' => $g->egg,
            'buddy' => $g->buddy === null ? null : ['found_at' => $this->time->present($g->buddy['found_at'] ?? null)] + $g->buddy,
            'creatures_found' => $g->found,
            'creatures_total' => $g->total,
            'recent' => array_map(fn (array $r) => ['at' => $this->time->present($r['at'] ?? null)] + $r, $g->recent),
            /** A guardian's limits for this child (null for the student). */
            'limits' => $g->limits,
            /** A guardian's look at the child's week (null for the student). */
            'glance' => $g->glance,
        ];
    }

    /** @return array{0: string, 1: string} the viewer and the learner on their screen */
    /** @return ?array{due: int, done_today: int, daily: int} */
    private function hifzToday(string $learner): ?array
    {
        if (! HifzPage::query()->where('student_id', $learner)->exists()) {
            return null;
        }
        $wird = app(HifzMap::class)->wird($learner, CarbonImmutable::now());

        return ['due' => count($wird['pages']), 'done_today' => $wird['done_today'], 'daily' => $wird['daily']];
    }

    private function learner(Request $request): array
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $asked = $request->query('learner_id');
        $learner = $this->family->learner($me, is_string($asked) && $asked !== '' ? $asked : null)?->id ?? throw new NotFoundHttpException;

        return [$me, $learner];
    }
}
