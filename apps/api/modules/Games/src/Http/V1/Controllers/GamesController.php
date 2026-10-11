<?php

namespace Modules\Games\Http\V1\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Games\Actions\ChooseBuddy;
use Modules\Games\Actions\FinishPlay;
use Modules\Games\Actions\NameCreature;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Domain\Round;
use Modules\Games\Models\GameLimit;
use Modules\Games\Models\GamePlay;
use Modules\Games\Models\GameSettings;
use Modules\Games\Queries\GamesWorld;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The games for the apps and the portal (docs/architecture/02, section 18):
 * a learner's world (islands, creatures, games, quests from their own
 * practice), playing a round (the page opens in a sandboxed frame or a
 * WebView and is handed the round's items through the bridge), and a
 * guardian's limits. A guardian can play on their child's screen; only a
 * guardian sets limits, and only the learner names their creatures and
 * picks their buddy.
 */
final class GamesController
{
    public function __construct(
        private readonly FamilyView $family,
        private readonly LocalTimePresenter $time,
        private readonly GameCatalog $catalog,
    ) {}

    /**
     * A learner's games world.
     *
     * The islands in order with their creatures (found or not) and games (playable when the learner has practice items
     * of the kinds it plays), the quests (what to practise now: homework, the last lesson, reviews, the current lesson;
     * each with the game for it), stars and the next egg, today's minutes, and the last rounds. For the student
     * themself (learner_id left out) or a guardian's child; a guardian also gets the limits they can set.
     */
    public function world(Request $request, GamesWorld $world): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        [$me, $learner] = $this->learner($request);
        $w = $world->for($learner);
        $l = 'games::games.';
        $guardian = $me !== $learner;

        return response()->json(['data' => [
            'learner_id' => $learner,
            'stage' => $w['stage'],
            /** Whether the learner may start a round now, and why not: off (the academy), paused (a guardian), night (a guardian's night lock, 21:00 to 7:00 on the learner's clock), limit (today's minutes). */
            'allowance' => $w['allowance'] + ['reason_label' => $w['allowance']['reason'] ? __($l.'refused.'.$w['allowance']['reason']) : null],
            'stars' => $w['stars'],
            /** Stars toward the next egg on an island. */
            'egg' => $w['egg'],
            'creatures_found' => $w['found'],
            'creatures_total' => $w['total'],
            /** The creature that keeps the learner company (their chosen buddy, else the last one hatched; null before the first). */
            'buddy' => $w['buddy'] === null ? null : ['found_at' => $this->time->present($w['buddy']['found_at'])] + $w['buddy'],
            'quests' => array_map(fn (array $q) => [
                'key' => $q['key'],
                'reason' => $q['reason'],
                'reason_label' => __($l.'reasons.'.$q['reason']),
                'title' => $q['title'],
                'homework_id' => $q['homework_id'],
                'due_at' => $this->time->present($q['due_at']),
                'done' => $q['done'],
                'items' => $q['items'],
                'game' => $q['game'],
                /** Rounds played on this quest's lessons only, and their best and last time in seconds (the race against yourself). */
                'rounds' => $q['rounds'],
                'best_seconds' => $q['best_seconds'],
                'last_seconds' => $q['last_seconds'],
            ], $w['quests']),
            /** Days in a row with a finished round, ending today (or yesterday while today is open), on the learner's clock. */
            'streak' => $w['streak'],
            'islands' => array_map(fn (array $i) => [
                'creatures' => array_map(fn (array $c) => ['found_at' => $this->time->present($c['found_at'])] + $c, $i['creatures']),
            ] + $i, $w['islands']),
            'recent' => array_map(fn (array $r) => ['at' => $this->time->present($r['at'])] + $r, $w['recent']),
            /** A guardian's limits for this child (null for the student). */
            'limits' => $guardian ? $this->limits($learner) : null,
        ]]);
    }

    /**
     * A learner's album of creatures.
     *
     * Every creature of the world in its order: hatched ones with when, how (stars or a mastered lesson), the name the
     * learner gave and whether it is their buddy; the rest with only how they look (to draw as a shadow) and their island.
     */
    public function creatures(Request $request, GamesWorld $world): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        [, $learner] = $this->learner($request);

        return response()->json(['data' => array_map(fn (array $c) => ['found_at' => $this->time->present($c['found_at'])] + $c, $world->album($learner))]);
    }

    /**
     * Name a creature or make it the buddy.
     *
     * The learner's own, hatched creatures only: a name (letters only, at most 24; empty takes its own name back) and/or
     * buddy: true (one at a time). A guardian looks but does not change them (403).
     *
     * @throws AccessDeniedHttpException a guardian does not name their child's creatures
     */
    public function updateCreature(Request $request, string $creature, NameCreature $name, ChooseBuddy $buddy, GamesWorld $world): JsonResponse
    {
        $data = $request->validate([
            'learner_id' => ['nullable', 'string', 'max:64'],
            'name' => ['sometimes', 'nullable', 'string', 'max:'.NameCreature::MAX],
            'buddy' => ['sometimes', 'accepted'],
        ]);
        [$me, $learner] = $this->learner($request);
        if ($me !== $learner) {
            throw new AccessDeniedHttpException;
        }
        try {
            if (array_key_exists('name', $data)) {
                $name->handle($learner, $creature, $data['name']);
            }
            if (! empty($data['buddy'])) {
                $buddy->handle($learner, $creature);
            }
        } catch (ModelNotFoundException) {
            throw new NotFoundHttpException;
        }
        $found = collect($world->album($learner))->firstWhere('key', $creature) ?? throw new NotFoundHttpException;

        return response()->json(['data' => ['found_at' => $this->time->present($found['found_at'])] + $found]);
    }

    /**
     * Start a round.
     *
     * Of a game (id or key) for a quest (its key), for a homework's practice (homework_id), or with all of the learner's
     * practice. Answers the page to open (page_url, sandboxed; never cached personal data) and the round to hand it
     * through the bridge: {lms: 1, type: "start", round}. 403 games-off / games-paused / games-night / games-limit, 422
     * games-not-enough-items when the learner has too few items for that game.
     */
    public function start(Request $request, StartPlay $start): JsonResponse
    {
        $data = $request->validate([
            'learner_id' => ['nullable', 'string', 'max:64'],
            'game' => ['required', 'string', 'max:64'],
            'quest' => ['nullable', 'string', 'max:120'],
            'homework_id' => ['nullable', 'uuid'],
        ]);
        [$me, $learner] = $this->learner($request);
        $r = $start->handle($learner, $me, $data['game'], $data['quest'] ?? null, $data['homework_id'] ?? null);
        $ar = app()->getLocale() === 'ar';

        return response()->json(['data' => [
            'play_id' => $r['play']->id,
            'page_url' => route('games.play', $r['game']->versionId),
            'game' => ['id' => $r['game']->id, 'key' => $r['game']->key, 'title' => $r['game']->title(), 'how_to_play' => $r['game']->howToPlay(), 'island' => $r['game']->islandKey],
            'focus' => $r['focus'] === null ? null : ['reason' => $r['focus']->reason, 'title' => $r['focus']->title],
            /** What the page is handed: {lms: 1, type: "start", round: this}. */
            'round' => ['items' => $r['round'], 'stage' => $r['stage'], 'locale' => $ar ? 'ar' : 'en', 'dir' => $ar ? 'rtl' : 'ltr', 'title' => $r['game']->title()],
            'max_seconds' => Round::MAX_SECONDS,
        ]], 201);
    }

    /**
     * Finish a round.
     *
     * Every try the page reported (item and whether it was right), in order. Answers the stars, how many were right
     * first time, a creature if one hatched, the next egg, and the homework handed in by this practice. Finishing again
     * answers the same stars. Send an Idempotency-Key.
     */
    public function finish(Request $request, string $play, FinishPlay $finish): JsonResponse
    {
        $request->validate(['answers' => ['present', 'array', 'max:500']]);
        $me = (string) $request->user()?->getAuthIdentifier();
        $row = GamePlay::query()->find($play) ?? throw new NotFoundHttpException;
        if ($this->family->learner($me, $row->student_id)?->id !== $row->student_id) {
            throw new NotFoundHttpException;
        }
        $r = $finish->handle($row, $me, (array) $request->input('answers', []));
        $p = $r['play'];
        $stars = (int) GamePlay::query()->where('student_id', $p->student_id)->whereNotNull('finished_at')->sum('stars');

        return response()->json(['data' => [
            'stars' => (int) $p->stars,
            'correct' => (int) $p->correct,
            'total' => $p->total,
            'creature' => $r['creature'] === null ? null : $this->creature($r['creature']),
            'egg' => ['have' => $stars % Round::STARS_PER_EGG, 'need' => Round::STARS_PER_EGG],
            'homework' => $r['homework'] === null ? null : ['title' => $r['homework'], 'handed_in' => $r['handed_in']],
        ]]);
    }

    /**
     * Set a child's play.
     *
     * A guardian turns games on or off for their child, sets the minutes a day (one of the choices; null: the academy's,
     * never more) and the night lock (no games from 21:00 to 7:00 on the child's clock; left out: unchanged).
     *
     * @throws AccessDeniedHttpException a student does not set their own
     */
    public function setLimits(Request $request, SaveGameLimit $save): JsonResponse
    {
        $data = $request->validate([
            'learner_id' => ['required', 'string', 'max:64'],
            'enabled' => ['required', 'boolean'],
            'daily_minutes' => ['nullable', 'integer'],
            'night_lock' => ['sometimes', 'boolean'],
        ]);
        [$me, $learner] = $this->learner($request, $data['learner_id']);
        if ($me === $learner) {
            throw new AccessDeniedHttpException;
        }
        $save->handle($learner, $me, (bool) $data['enabled'], isset($data['daily_minutes']) ? (int) $data['daily_minutes'] : null,
            array_key_exists('night_lock', $data) ? (bool) $data['night_lock'] : null);

        return response()->json(['data' => $this->limits($learner)]);
    }

    /** @return array{enabled: bool, daily_minutes: ?int, night_lock: bool, academy_minutes: ?int, choices: list<int>} */
    private function limits(string $learner): array
    {
        $limit = GameLimit::query()->find($learner);

        return [
            'enabled' => $limit?->enabled ?? true,
            'daily_minutes' => $limit?->daily_minutes,
            /** No games from 21:00 to 7:00 on the child's clock. */
            'night_lock' => (bool) ($limit?->night_lock ?? false),
            /** The academy's own minutes a day (null: no limit); a guardian's choice never goes over it. */
            'academy_minutes' => GameSettings::current()->daily_minutes,
            'choices' => SaveGameLimit::MINUTES,
        ];
    }

    /** @return array<string, mixed> */
    private function creature(CatalogCreature $c): array
    {
        return ['key' => $c->key, 'name' => $c->name(), 'emoji' => $c->emoji, 'rarity' => $c->rarity, 'island' => $c->islandKey, 'look' => $c->look, 'story' => $c->story()];
    }

    /** @return array{0: string, 1: string} the viewer and the learner on their screen */
    private function learner(Request $request, ?string $asked = null): array
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $asked ??= $request->input('learner_id');
        $learner = $this->family->learner($me, is_string($asked) && $asked !== '' ? $asked : null)?->id ?? throw new NotFoundHttpException;

        return [$me, $learner];
    }
}
