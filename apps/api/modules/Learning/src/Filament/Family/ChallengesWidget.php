<?php

namespace Modules\Learning\Filament\Family;

use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Actions\ClaimChallenge;
use Modules\Learning\Actions\MarkCheersSeen;
use Modules\Learning\Actions\SendCheer;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\Cheer;
use Modules\Learning\Queries\ChallengesQuery;
use Modules\People\Contracts\FamilyView;

/**
 * The game layer on the family home, as in the apps: the learner's
 * challenges in their stage's way (the kids' missions and chest, middle
 * school's quests and bosses, the older students' goals). The student
 * collects rewards (the points count up, with confetti) and sees the
 * cheers sent to them; a guardian follows and cheers.
 */
final class ChallengesWidget extends Widget
{
    protected string $view = 'learning::filament.family.challenges';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /** Drawn with the page: no placeholder that jumps, no extra request. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return FamilyLearning::learner() !== null;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        $learner = FamilyLearning::learner();
        $me = (string) auth()->id();
        $self = $learner->id === $me;
        $state = app(ChallengesQuery::class)->for($learner->id, app(FamilyView::class)->stage($learner->id)->value);
        $l = 'learning::challenges.';
        $rank = $state['journey']['rank'];
        $cheers = $self ? Cheer::query()->where('student_id', $learner->id)->whereNull('seen_at')->orderBy('created_at')->limit(10)->get() : collect();
        $names = DB::table('users')->whereIn('id', $cheers->pluck('from_id')->unique())->pluck('name', 'id');

        return [
            'learner' => $learner,
            'first' => strtok($learner->name, ' ') ?: $learner->name,
            'self' => $self,
            'stage' => $state['stage'],
            'points' => $state['journey']['points'],
            'rank' => __('learning::journey.ranks.'.$rank['key']),
            'rank_progress' => $rank['progress'],
            'days_left' => max(0, (int) floor(CarbonImmutable::now()->diffInHours($state['week']['to'], false) / 24)),
            'challenges' => array_map(fn (array $c) => $c + [
                'title' => __($l.$state['stage'].'.'.$c['key'], ['world' => $c['world']['title'] ?? '', 'index' => $c['world']['index'] ?? ''])[0],
                'hint' => __($l.$state['stage'].'.'.$c['key'], ['world' => $c['world']['title'] ?? '', 'index' => $c['world']['index'] ?? ''])[1],
                'reward_label' => __($l.'units.'.$state['stage'], ['n' => $c['reward']]),
                'state_label' => __($l.'states.'.$c['state']),
            ], $state['challenges']),
            'stickers' => $state['stickers'],
            'cheers' => $cheers->map(fn (Cheer $c) => ['kind' => $c->kind, 'from' => strtok((string) ($names[$c->from_id] ?? ''), ' ')])->all(),
            'cheer_key' => $cheers->last()?->id,
            'cheers_left' => $self ? 0 : max(0, ChallengeCatalog::CHEERS_PER_DAY - Cheer::query()->where('from_id', $me)->where('student_id', $learner->id)
                ->where('created_at', '>', now()->subDay())->count()),
        ];
    }

    /** The student collects a finished challenge. */
    public function claim(string $challenge, string $period): void
    {
        $learner = FamilyLearning::learner();
        abort_unless($learner !== null && $learner->id === (string) auth()->id(), 403);
        $stage = app(FamilyView::class)->stage($learner->id)->value;
        try {
            $result = app(ClaimChallenge::class)->handle($learner->id, $stage, $challenge, $period);
        } catch (ValidationException $e) {
            Notification::make()->warning()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $l = 'learning::challenges.';
        $unit = array_key_exists($stage, ChallengeCatalog::STAGES) ? $stage : 'classic';
        $body = array_filter([
            $result['sticker'] ? __($l.'web.sticker', ['name' => __($l.'stickers.'.$result['sticker'])]) : null,
            $result['rank']['key'] !== $result['rank_before'] ? __($l.'web.rank_up', ['rank' => __('learning::journey.ranks.'.$result['rank']['key'])]) : null,
        ]);
        $reward = __($l.'units.'.$unit, ['n' => $result['reward']]);
        $this->dispatch('lc-collected', text: '+'.$reward);
        Notification::make()->success()
            ->title(__($l.'web.collected', ['reward' => $reward]))
            ->body($body === [] ? null : implode(' · ', $body))
            ->send();
    }

    /** A guardian cheers the learner on screen. */
    public function cheer(string $kind): void
    {
        $learner = FamilyLearning::learner();
        abort_unless($learner !== null, 404);
        try {
            app(SendCheer::class)->handle((string) auth()->id(), $learner->id, $kind);
        } catch (ValidationException $e) {
            Notification::make()->warning()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        Notification::make()->success()->title(__('learning::challenges.web.cheer_sent', ['name' => strtok($learner->name, ' ')]))->send();
    }

    /** The student saw their cheers. */
    public function seen(): void
    {
        $learner = FamilyLearning::learner();
        abort_unless($learner !== null && $learner->id === (string) auth()->id(), 403);
        app(MarkCheersSeen::class)->handle($learner->id);
    }
}
