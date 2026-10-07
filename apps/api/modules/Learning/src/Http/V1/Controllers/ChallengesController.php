<?php

namespace Modules\Learning\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Actions\ClaimChallenge;
use Modules\Learning\Actions\MarkCheersSeen;
use Modules\Learning\Actions\SendCheer;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\Cheer;
use Modules\Learning\Queries\ChallengesQuery;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The game layer of a student's own area, for the apps: the challenges of
 * their stage this week (and month, and level), collecting a reward, the
 * kids' sticker album, and the cheers a guardian sends. A guardian sees a
 * child's challenges and cheers them on; only the student collects.
 */
final class ChallengesController
{
    private const ICONS = [
        'attend' => 'calendar', 'homework' => 'homework', 'on_time' => 'clock', 'master' => 'star',
        'focus' => 'bolt', 'month' => 'trophy', 'boss' => 'crown', 'chest' => 'chest',
    ];

    public function __construct(
        private readonly FamilyView $family,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * A learner's challenges.
     *
     * For the student themself (learner_id left out) or a guardian's child. Each challenge has its target, progress and
     * state: active, missed, done (ready to collect, by the student) or claimed. Rewards finished last week or last month
     * come first while they can still be collected. cheers: the guardians' cheers the student has not seen yet.
     */
    public function index(Request $request, ChallengesQuery $challenges): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        [$me, $learner] = $this->learner($request);
        $stage = $this->family->stage($learner)->value;
        $state = $challenges->for($learner, $stage);
        $self = $learner === $me;
        $l = 'learning::challenges.';
        $journey = $state['journey'];

        return response()->json(['data' => [
            'learner_id' => $learner,
            'stage' => $state['stage'],
            /** Only the student collects rewards; a guardian watches and cheers. */
            'can_claim' => $self,
            'week' => ['starts_at' => $this->time->present($state['week']['from']), 'ends_at' => $this->time->present($state['week']['to'])],
            'points' => $journey['points'],
            'rank' => $this->rank($journey['rank']),
            'challenges' => array_map(fn (array $c) => [
                'key' => $c['key'],
                'period' => $c['period'],
                /** week, last_week, month, last_month or world (a level) */
                'group' => $c['group'],
                'title' => __($l.$state['stage'].'.'.$c['key'], ['world' => $c['world']['title'] ?? '', 'index' => $c['world']['index'] ?? ''])[0],
                'hint' => __($l.$state['stage'].'.'.$c['key'], ['world' => $c['world']['title'] ?? '', 'index' => $c['world']['index'] ?? ''])[1],
                /** calendar, homework, clock, star, bolt, trophy, crown or chest */
                'icon' => self::ICONS[$c['key']] ?? 'star',
                'target' => $c['target'],
                'progress' => $c['progress'],
                /** active, missed, done (ready to collect) or claimed */
                'state' => $c['state'],
                'state_label' => __($l.'states.'.$c['state']),
                'claimable' => $self && $c['state'] === 'done',
                'reward' => $c['reward'],
                'reward_label' => __($l.'units.'.$state['stage'], ['n' => $c['reward']]),
                /** The kids' chest: the sticker it holds (or held). */
                'sticker' => $c['sticker'] === null ? null : ['key' => $c['sticker'], 'name' => __($l.'stickers.'.$c['sticker'])],
                'world' => $c['world'],
                'ends_at' => $c['ends_at'] === null ? null : $this->time->present($c['ends_at']),
            ], $state['challenges']),
            /** The kids' album (null for other stages). */
            'stickers' => $state['stickers'] === null ? null : [
                'earned' => $state['stickers']['earned'],
                'items' => array_map(fn (array $s) => $s + ['name' => __($l.'stickers.'.$s['key'])], $state['stickers']['items']),
            ],
            'cheers' => ! $self ? [] : Cheer::query()->where('student_id', $learner)->whereNull('seen_at')->orderBy('created_at')->limit(10)->get()
                ->map(fn (Cheer $c) => [
                    'id' => $c->id,
                    /** clap, star, heart or fire */
                    'kind' => $c->kind,
                    'from' => $this->firstName($c->from_id),
                    'at' => $this->time->present($c->created_at),
                ])->all(),
            /** For a guardian: cheers they may still send this learner today. */
            'cheers_left' => $self ? 0 : max(0, ChallengeCatalog::CHEERS_PER_DAY - Cheer::query()->where('from_id', $me)->where('student_id', $learner)->where('created_at', '>', now()->subDay())->count()),
        ]]);
    }

    /**
     * Collect a reward.
     *
     * The student collects a challenge they finished (challenge and period as listed). Its points add to theirs; the
     * kids' chest also brings the next sticker. Once only. Send an Idempotency-Key.
     *
     * @throws AccessDeniedHttpException only the student collects
     */
    public function claim(Request $request, ClaimChallenge $claim): JsonResponse
    {
        $request->validate(['challenge' => ['required', 'string', 'max:30'], 'period' => ['required', 'string', 'max:60']]);
        $me = (string) $request->user()?->getAuthIdentifier();
        if ($this->family->learner($me, $me)?->id !== $me) {
            throw new AccessDeniedHttpException;
        }
        $stage = $this->family->stage($me)->value;
        $result = $claim->handle($me, $stage, (string) $request->string('challenge'), (string) $request->string('period'));
        $l = 'learning::challenges.';

        return response()->json(['data' => [
            'reward' => $result['reward'],
            'reward_label' => __($l.'units.'.(array_key_exists($stage, ChallengeCatalog::STAGES) ? $stage : 'classic'), ['n' => $result['reward']]),
            'sticker' => $result['sticker'] === null ? null : ['key' => $result['sticker'], 'name' => __($l.'stickers.'.$result['sticker'])],
            'points_before' => $result['points_before'],
            'points' => $result['points'],
            /** The rank moved up with this reward. */
            'ranked_up' => $result['rank']['key'] !== $result['rank_before'],
            'rank' => $this->rank($result['rank']),
        ]], 201);
    }

    /**
     * Cheer a learner on.
     *
     * A guardian sends one of clap, star, heart or fire; the student sees it in their app and hears it by push. A few a
     * day per learner.
     */
    public function cheer(Request $request, SendCheer $send): JsonResponse
    {
        $request->validate(['learner_id' => ['required', 'string', 'max:64'], 'kind' => ['required', 'string', 'in:'.implode(',', Cheer::KINDS)]]);
        $cheer = $send->handle((string) $request->user()?->getAuthIdentifier(), (string) $request->string('learner_id'), (string) $request->string('kind'));

        return response()->json(['data' => ['id' => $cheer->id, 'kind' => $cheer->kind]], 201);
    }

    /** The student saw their cheers. */
    public function seen(Request $request, MarkCheersSeen $seen): Response
    {
        $seen->handle((string) $request->user()?->getAuthIdentifier());

        return response()->noContent();
    }

    /** @return array{0: string, 1: string} the viewer and the learner they asked for */
    private function learner(Request $request): array
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $asked = $request->query('learner_id');
        $learner = $this->family->learner($me, is_string($asked) && $asked !== '' ? $asked : null)?->id ?? throw new NotFoundHttpException;

        return [$me, $learner];
    }

    /** @return array<string, mixed> */
    private function rank(array $rank): array
    {
        $l = 'learning::journey.ranks.';

        return $rank + ['label' => __($l.$rank['key']), 'next_label' => $rank['next_key'] ? __($l.$rank['next_key']) : null];
    }

    private function firstName(string $userId): string
    {
        return strtok((string) DB::table('users')->where('id', $userId)->value('name'), ' ') ?: '';
    }
}
