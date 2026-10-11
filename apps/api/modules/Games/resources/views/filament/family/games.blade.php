@php
    $l = 'games::games.';
    $w = $this->world;
    $round = $this->round;
    $result = $this->result;
    $limits = $this->limits;
    $look = $this->look();
    $hex = fn ($c, $fallback) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $fallback;
@endphp
<x-filament-panels::page>
    @include('games::filament.family.styles')

    @if ($round)
        {{-- A round: the game's page in a sandboxed frame; what it reports goes back through finish(). --}}
        <section class="gw-stage" data-look="{{ $look }}" wire:key="round-{{ $round['play'] }}">
            <header class="gw-stage-bar">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-arrow-right" wire:click="leave">{{ __($l.'back') }}</x-filament::button>
                <div>
                    <strong>{{ $round['title'] }}</strong>
                    <small>{{ $round['focus'] ? __($l.'practising', ['what' => $round['focus']]) : $round['how'] }}</small>
                </div>
            </header>
            <div class="gw-frame" wire:ignore data-game-player data-round-player
                 data-src="{{ $round['src'] }}" data-items="{{ json_encode($round['items'], JSON_UNESCAPED_UNICODE) }}"
                 data-stage="{{ $round['stage'] }}" data-locale="{{ $round['locale'] }}" data-dir="{{ $round['dir'] }}" data-title="{{ $round['title'] }}"></div>
        </section>
    @elseif ($w === null)
        <p class="gw-empty">{{ __($l.'no_learner') }}</p>
    @else
        <div class="gw" data-look="{{ $look }}" data-live-channel="{{ $this->channel() }}">
            @if ($result)
                {{-- The round's result: stars, how many right first time, a creature hatched, homework handed in. --}}
                <section class="gw-result" wire:key="result-{{ $result['game'] }}-{{ $result['stars'] }}-{{ $result['correct'] }}">
                    <div class="gw-stars" aria-label="{{ trans_choice($l.'stars', $result['stars'], ['count' => $result['stars']]) }}">
                        @for ($i = 1; $i <= 3; $i++)<span data-on="{{ $i <= $result['stars'] ? 'yes' : 'no' }}" style="--i: {{ $i }}">★</span>@endfor
                    </div>
                    <h2>{{ __($l.'result.'.min(3, $result['stars'])) }}</h2>
                    <p>{{ __($l.'result.score', ['correct' => $result['correct'], 'total' => $result['total']]) }}</p>
                    @if ($result['creature'])
                        <div class="gw-hatch" data-rarity="{{ $result['creature']['rarity'] }}" data-celebrate>
                            <span class="gw-egg" aria-hidden="true">🥚</span>
                            <span class="gw-born" aria-hidden="true">{{ $result['creature']['emoji'] }}</span>
                            <div>
                                <strong>{{ __($l.'hatched', ['name' => $result['creature']['name']]) }}</strong>
                                <small>{{ __('shared::games.rarity.'.$result['creature']['rarity']) }}</small>
                            </div>
                        </div>
                    @endif
                    @if ($result['homework'])
                        <p class="gw-done">✅ {{ __($l.'homework_done', ['title' => $result['homework']]) }}</p>
                    @endif
                    <div class="gw-egg-bar"><span>🥚 {{ __($l.'egg', ['have' => $result['egg']['have'], 'need' => $result['egg']['need']]) }}</span><i><b class="lms-bar-fill" style="width: {{ round(100 * $result['egg']['have'] / max(1, $result['egg']['need'])) }}%"></b></i></div>
                    <div class="gw-actions">
                        <x-filament::button icon="heroicon-m-arrow-path" wire:click="again">{{ __($l.'again') }}</x-filament::button>
                        <x-filament::button color="gray" wire:click="leave">{{ __($l.'to_world') }}</x-filament::button>
                    </div>
                </section>
            @endif

            {{-- Stars, the next egg and today's minutes. --}}
            <section class="gw-hero">
                <div class="gw-hero-item"><span>⭐</span><strong data-count="{{ $w['stars'] }}">{{ $w['stars'] }}</strong><small>{{ __($l.'stars_label') }}</small></div>
                <div class="gw-hero-item"><span>🥚</span><strong>{{ $w['egg']['have'] }}/{{ $w['egg']['need'] }}</strong><small>{{ __($l.'egg_label') }}</small></div>
                <div class="gw-hero-item"><span>🐾</span><strong data-count="{{ $w['found'] }}">{{ $w['found'] }}</strong><small>{{ __($l.'found_label') }}</small></div>
                <div class="gw-hero-item"><span>⏱️</span>
                    <strong>{{ $w['allowance']['left'] === null ? '∞' : $w['allowance']['left'] }}</strong>
                    <small>{{ __($l.'minutes_left') }}</small>
                </div>
            </section>
            @if (! $w['allowance']['may'])
                <p class="gw-note">{{ __($l.'refused.'.$w['allowance']['reason']) }}</p>
            @endif

            {{-- Quests: what to practise now, from homework, the last lesson and reviews. --}}
            <section class="gw-section">
                <h2>{{ __($l.'quests_title.'.$look) }}</h2>
                @if ($w['quests'] === [])
                    <p class="gw-empty">{{ __($l.'no_quests') }}</p>
                @else
                    <div class="gw-quests lms-stagger">
                        @foreach ($w['quests'] as $q)
                            <article class="gw-quest lms-lift" data-reason="{{ $q['reason'] }}" @if ($q['done']) data-done @endif wire:key="quest-{{ $q['key'] }}">
                                <span class="gw-chip">{{ __($l.'reasons.'.$q['reason']) }}</span>
                                <h3>{{ $q['title'] ?: __($l.'reasons.'.$q['reason']) }}</h3>
                                <small>🎮 {{ $q['game']['title'] }} · {{ trans_choice($l.'items', $q['items'], ['count' => $q['items']]) }}</small>
                                @if ($q['done'])<span class="gw-tick">✓ {{ __($l.'quest_done') }}</span>@endif
                                <x-filament::button size="sm" :color="$q['done'] ? 'gray' : 'primary'" icon="heroicon-m-play" wire:click="play('{{ $q['game']['id'] }}', '{{ $q['key'] }}')" :disabled="! $w['allowance']['may']">
                                    {{ __($q['done'] ? $l.'play_again' : $l.'play') }}
                                </x-filament::button>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- The islands: creatures hatched (and to come), and the games on each. --}}
            <section class="gw-section">
                <h2>{{ __($l.'islands_title') }}</h2>
                <div class="gw-islands lms-stagger">
                    @foreach ($w['islands'] as $island)
                        @php $c = $island['colors']; $found = collect($island['creatures'])->where('found', true)->count(); @endphp
                        <article class="gw-island" wire:key="island-{{ $island['key'] }}"
                                 style="background: linear-gradient(160deg, {{ $hex($c['sky'] ?? null, '#5ec8f2') }} 0%, {{ $hex($c['sea'] ?? null, '#1a8fb0') }} 100%)">
                            <header>
                                <span class="gw-island-emoji" aria-hidden="true">{{ $island['emoji'] }}</span>
                                <div>
                                    <h3>{{ $island['name'] }}</h3>
                                    <small>{{ $island['story'] }}</small>
                                </div>
                                <span class="gw-count">{{ $found }}/{{ count($island['creatures']) }}</span>
                            </header>
                            <div class="gw-creatures">
                                @foreach ($island['creatures'] as $creature)
                                    <span data-found="{{ $creature['found'] ? 'yes' : 'no' }}" data-rarity="{{ $creature['rarity'] }}"
                                          title="{{ $creature['found'] ? $creature['name'] : __($l.'not_yet') }}">{{ $creature['found'] ? $creature['emoji'] : '?' }}</span>
                                @endforeach
                            </div>
                            <div class="gw-games">
                                @forelse ($island['games'] as $game)
                                    <div class="gw-game" @unless ($game['playable']) data-locked @endunless>
                                        <div>
                                            <strong>{{ $game['title'] }}</strong>
                                            <small>{{ $game['playable'] ? $game['summary'] : __($l.'locked') }}</small>
                                        </div>
                                        @if ($game['playable'])
                                            <x-filament::button size="sm" icon="heroicon-m-play" wire:click="play('{{ $game['id'] }}')" :disabled="! $w['allowance']['may']">{{ __($l.'play') }}</x-filament::button>
                                        @endif
                                    </div>
                                @empty
                                    <p class="gw-soon">{{ __($l.'soon') }}</p>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            @if ($w['recent'] !== [])
                <section class="gw-section">
                    <h2>{{ __($l.'recent') }}</h2>
                    <ul class="gw-recent">
                        @foreach ($w['recent'] as $r)
                            <li><span>{{ $r['game'] }}</span><span>{{ str_repeat('★', $r['stars']) }}{{ str_repeat('☆', 3 - $r['stars']) }}</span><small>{{ __($l.'result.score', ['correct' => $r['correct'], 'total' => $r['total']]) }} · {{ $r['at']?->diffForHumans() }}</small></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($limits)
                {{-- A guardian's choice for this child. --}}
                <section class="gw-section gw-limits" x-data="{ enabled: @js($limits['enabled']), minutes: @js($limits['daily_minutes']) }">
                    <h2>{{ __($l.'limits.title') }}</h2>
                    <p>{{ __($l.'limits.hint', ['academy' => $limits['academy'] ?? '∞']) }}</p>
                    <label class="gw-switch"><input type="checkbox" x-model="enabled"> {{ __($l.'limits.enabled') }}</label>
                    <label>{{ __($l.'limits.minutes') }}
                        <select x-model.number="minutes">
                            <option :value="null">{{ __($l.'limits.academy_default') }}</option>
                            @foreach ($limits['choices'] as $m)
                                @if ($limits['academy'] === null || $m <= $limits['academy'])
                                    <option value="{{ $m }}">{{ trans_choice($l.'limits.n_minutes', $m, ['n' => $m]) }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <div><x-filament::button size="sm" x-on:click="$wire.saveLimits(enabled, minutes)">{{ __($l.'limits.save') }}</x-filament::button></div>
                </section>
            @endif
        </div>
    @endif
</x-filament-panels::page>

@script
<script>
    // A round: mount the sandboxed player, keep every try the game reports, and hand them in when it finishes.
    let answers = [];
    let finishing = false;
    const mount = () => {
        const root = $wire.$el.querySelector('[data-round-player]');
        if (root && window.LmsGame && ! window.LmsGame.of(root)) {
            answers = [];
            finishing = false;
            window.LmsGame.mount(root);
        }
    };
    $wire.$el.addEventListener('lms-game:answer', (e) => { answers.push({ item: e.detail.item, correct: e.detail.correct }); });
    $wire.$el.addEventListener('lms-game:finish', () => {
        if (finishing) return;
        finishing = true;
        $wire.finish(answers);
    });
    new MutationObserver(mount).observe($wire.$el, { childList: true, subtree: true });
    mount();

    // A round played elsewhere (the app), a creature hatched, a limit changed: the world follows the learner's channel.
    const live = $wire.$el.querySelector('[data-live-channel]');
    if (live && window.LmsLive) {
        window.LmsLive.watch({ el: live, channel: live.dataset.liveChannel, event: '.practice.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
