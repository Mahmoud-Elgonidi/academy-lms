@php
    $l = 'platform::games.';
    $drafts = $this->drafts;
    $islands = $this->world;
    $hex = fn ($c, $fallback) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $fallback;
@endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::games.styles')
    @include('shared::games.art-styles')
    @include('shared::games.sound')
    <div class="gs" data-live-channel="{{ $this->channel() }}">
        @if (($designer = $this->designer()) !== 'claude')
            <div class="gs-note" role="status">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="gs-note-icon" />
                <span>{{ __($l.'designer.'.$designer) }}</span>
            </div>
        @endif

        <section class="gs-section">
            <h2>{{ __($l.'open') }}</h2>
            @if ($drafts === [])
                <p class="pc-base pc-empty">{{ __($l.'open_none') }}</p>
            @else
                <div class="pc-grid lms-stagger">
                    @foreach ($drafts as $d)
                        <a href="{{ $d['url'] }}" wire:navigate class="pc-base gs-draft lms-lift" wire:key="draft-{{ $d['id'] }}">
                            <header>
                                <h3>{{ $d['title'] }}</h3>
                                <span class="gs-state" data-state="{{ $d['status'] }}">{{ __($l.'status.'.$d['status']) }}</span>
                            </header>
                            <p>{{ $d['story'] }}</p>
                            <footer>
                                <span>{{ $d['island'] }}</span>
                                <span>{{ trans_choice($l.'rounds', $d['rounds'], ['count' => $d['rounds']]) }} · {{ $d['updated'] }}</span>
                            </footer>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        @php $st = $this->stats; @endphp
        <div class="gs-stats lms-stagger" role="list">
            <div class="pc-base gs-stat" role="listitem"><small>{{ __($l.'stats.players') }}</small><b data-count="{{ $st['players'] }}">{{ number_format($st['players']) }}</b><span data-good>{{ __($l.'stats.players_sub', ['academies' => $st['academies']]) }}</span></div>
            <div class="pc-base gs-stat" role="listitem"><small>{{ __($l.'stats.rounds') }}</small><b data-count="{{ $st['rounds'] }}">{{ number_format($st['rounds']) }}</b><span>{{ __($l.'stats.rounds_sub', ['avg' => $st['per_player']]) }}</span></div>
            <div class="pc-base gs-stat" role="listitem"><small>{{ __($l.'stats.finished') }}</small><b>{{ $st['finished'] }}%</b><span>{{ __($l.'stats.finished_sub') }}</span></div>
            <div class="pc-base gs-stat" role="listitem"><small>{{ __($l.'stats.eggs') }}</small><b data-count="{{ $st['eggs'] }}">{{ number_format($st['eggs']) }}</b><span>{{ __($l.'stats.eggs_sub', ['mastery' => $st['mastery_eggs']]) }}</span></div>
        </div>

        <section class="gs-section" x-data="{ tab: 'islands', island: null }">
            <div class="gs-tabs" role="tablist" aria-label="{{ __($l.'world') }}">
                @foreach (['islands', 'games', 'creatures', 'sounds'] as $tab)
                    <button type="button" role="tab" :aria-selected="(tab === @js($tab)).toString()" x-on:click="tab = @js($tab)">{{ __($l.'tabs.'.$tab) }}</button>
                @endforeach
            </div>

            {{-- The islands as the children see them on the map. --}}
            <div class="gs-icards lms-stagger" x-show="tab === 'islands'" role="tabpanel">
                @foreach ($islands as $n => $island)
                    @php $c = $island['colors']; $live = collect($island['games'])->contains(fn ($g) => $g['status'] === 'live'); @endphp
                    <article class="pc-base gs-icard lms-lift" wire:key="icard-{{ $island['id'] }}">
                        <div class="gs-icard-art" style="background: linear-gradient(180deg, {{ $hex($c['sky'] ?? null, '#8ED8FF') }} 0%, #2F9BE3 100%)">
                            <span class="gs-pos">{{ __($l.'island.position', ['n' => $n + 1]) }}</span>
                            <span class="gs-pub" data-state="{{ $live ? 'live' : 'draft' }}">{{ __($l.'island.'.($live ? 'live' : 'draft')) }}</span>
                            <span class="gs-icard-island" style="width: 200px; height: 190px; display: block">
                                <span style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center">
                                    <span style="position: relative; display: block">@include('shared::games.island', ['key' => $island['key'], 'locked' => false, 'colors' => $island['colors'], 'emoji' => $island['emoji']])</span>
                                </span>
                            </span>
                        </div>
                        <div class="gs-icard-body">
                            <h3>{{ $island['name'] }}</h3>
                            <div class="gs-chiprow">
                                <span>{{ trans_choice($l.'island.games', count($island['games']), ['count' => count($island['games'])]) }}</span>
                                <span data-tone="green">{{ __($l.'island.creatures', ['count' => count($island['creatures'])]) }}</span>
                                <span data-tone="orange">{{ __($l.'island.music', ['scale' => __($l.'scales.'.$island['music'])]) }}</span>
                            </div>
                            <p>{{ __($l.'island.opens') }}</p>
                            <p>{{ __($l.'island.who') }}</p>
                            <footer>
                                <x-filament::button size="sm" x-on:click="tab = 'games'; island = {{ \Illuminate\Support\Js::from($island['id']) }}">{{ __($l.'island.games_btn') }}</x-filament::button>
                                <x-filament::button size="sm" color="gray" outlined wire:click="preview('{{ $island['id'] }}')">{{ __($l.'island.preview') }}</x-filament::button>
                            </footer>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Every game, with its island; open its page, try, develop, retire. --}}
            <div class="gs-section" x-show="tab === 'games'" x-cloak role="tabpanel">
                <div class="gs-stages" role="group" aria-label="{{ __($l.'attributes.island') }}">
                    <button type="button" :aria-pressed="(! island).toString()" x-on:click="island = null">{{ __($l.'all_islands') }}</button>
                    @foreach ($islands as $island)
                        <button type="button" :aria-pressed="(island === @js($island['id'])).toString()" x-on:click="island = @js($island['id'])">{{ $island['emoji'] }} {{ $island['name'] }}</button>
                    @endforeach
                </div>
                <div class="gs-games lms-stagger">
                    @foreach ($islands as $island)
                        @php $sky = $hex($island['colors']['sky'] ?? null, '#8ED8FF'); @endphp
                        @foreach ($island['games'] as $game)
                            <article class="pc-base gs-game" data-status="{{ $game['status'] }}" x-show="! island || island === @js($island['id'])" wire:key="game-{{ $game['id'] }}">
                                <a href="{{ $game['url'] }}" wire:navigate class="gs-game-art" style="background: linear-gradient(180deg, {{ $sky }} 0%, #2F9BE3 100%)" aria-hidden="true" tabindex="-1">
                                    <span><span style="position: relative; display: block">@include('shared::games.island', ['key' => $island['key'], 'locked' => false, 'colors' => $island['colors'], 'emoji' => $island['emoji']])</span></span>
                                </a>
                                <div class="gs-game-main">
                                    <div>
                                        <a href="{{ $game['url'] }}" wire:navigate><strong>{{ $game['title'] }}</strong></a>
                                        <span class="gs-state" data-state="{{ $game['status'] }}">{{ __($l.'status.'.$game['status']) }}</span>
                                    </div>
                                    <small>{{ $island['emoji'] }} {{ $island['name'] }}@if ($game['version']) · {{ __($l.'version', ['n' => $game['version']]) }}@endif · {{ trans_choice($l.'rounds', $game['rounds'], ['count' => $game['rounds']]) }}</small>
                                    @if ($game['summary'])<p>{{ $game['summary'] }}</p>@endif
                                    <div class="gs-tags">
                                        @foreach ($game['kinds'] as $kind)<span data-kind>{{ $kind }}</span>@endforeach
                                        @foreach ($game['stages'] as $stage)<span>{{ $stage }}</span>@endforeach
                                    </div>
                                </div>
                                <div class="gs-game-actions">
                                    <x-filament::button size="sm" tag="a" :href="$game['url']">{{ __($l.'open_game') }}</x-filament::button>
                                    @if ($game['version'])
                                        <x-filament::button size="sm" color="gray" outlined icon="heroicon-m-play" wire:click="tryGame('{{ $game['id'] }}')">{{ __($l.'try') }}</x-filament::button>
                                    @endif
                                    <x-filament::button size="sm" color="gray" outlined icon="heroicon-m-sparkles" wire:click="develop('{{ $game['id'] }}')">{{ __($l.'develop') }}</x-filament::button>
                                    @if ($game['status'] === 'live')
                                        <x-filament::button size="sm" color="gray" outlined wire:click="setStatus('{{ $game['id'] }}', 'retired')" wire:confirm="{{ __($l.'retired_done') }}">{{ __($l.'retire') }}</x-filament::button>
                                    @elseif ($game['version'])
                                        <x-filament::button size="sm" color="success" outlined wire:click="setStatus('{{ $game['id'] }}', 'live')">{{ __($l.'revive') }}</x-filament::button>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                        @if ($island['games'] === [])
                            <p class="pc-base gs-empty" x-show="island === @js($island['id'])">{{ $island['emoji'] }} {{ $island['name'] }} — {{ __($l.'no_games') }}</p>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- The creatures, drawn. --}}
            <div class="gs-ccards" x-show="tab === 'creatures'" x-cloak role="tabpanel">
                @foreach ($islands as $island)
                    @foreach ($island['creatures'] as $creature)
                        <div class="pc-base gs-ccard" data-rarity="{{ $creature['rarity'] }}" title="{{ $creature['story'] }}">
                            @include('shared::games.creature', ['look' => $creature['look'], 'size' => 72, 'motion' => true])
                            <strong>{{ $creature['name'] }}</strong>
                            <small>{{ $island['emoji'] }} {{ $island['name'] }} · {{ __('shared::games.rarity.'.$creature['rarity']) }}</small>
                        </div>
                    @endforeach
                @endforeach
            </div>

            {{-- The music of each island and the world's sounds, made in the browser. --}}
            <div class="gs-section" x-show="tab === 'sounds'" x-cloak role="tabpanel" x-data="{ playing: null, timer: null,
                    listen(scale) { const s = window.LmsIslandsSound; if (! s) return; clearTimeout(this.timer); if (this.playing === scale) { s.stop(false); this.playing = null; return; } s.stop(true); s.scale(scale); if (s.muted()) s.setMuted(false); s.start(); this.playing = scale; this.timer = setTimeout(() => { s.stop(false); this.playing = null; }, 12000); } }">
                <h3 style="font-weight: 800">{{ __($l.'sounds.music') }}</h3>
                <p class="gs-hint">{{ __($l.'sounds.music_hint') }}</p>
                <div class="gs-sounds">
                    @foreach ($islands as $island)
                        <div class="pc-base gs-sound">
                            <span aria-hidden="true">{{ $island['emoji'] }}</span>
                            <div><strong>{{ $island['name'] }}</strong><small>{{ __($l.'scales.'.$island['music']) }}</small></div>
                            <x-filament::button size="xs" color="gray" x-on:click="listen({{ \Illuminate\Support\Js::from($island['music']) }})"><span x-text="playing === @js($island['music']) ? @js(__($l.'sounds.stop')) : @js(__($l.'sounds.play'))">{{ __($l.'sounds.play') }}</span></x-filament::button>
                        </div>
                    @endforeach
                </div>
                <h3 style="font-weight: 800">{{ __($l.'sounds.effects') }}</h3>
                <div class="gs-sounds">
                    @foreach (__($l.'sounds.names') as $effect => $name)
                        <div class="pc-base gs-sound">
                            <span aria-hidden="true">🔊</span>
                            <div><strong>{{ $name }}</strong></div>
                            <x-filament::button size="xs" color="gray" x-on:click="window.LmsIslandsSound?.setMuted(false); window.LmsIslandsSound?.play({{ \Illuminate\Support\Js::from($effect) }})">{{ __($l.'sounds.play') }}</x-filament::button>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <x-filament::modal id="game-try" width="2xl">
        @if ($tried = $this->tried)
            <x-slot name="heading">{{ $tried['title'] }}</x-slot>
            <div class="gs-section">
                <div class="gs-stages" role="group" aria-label="{{ __($l.'draft.look') }}">
                    @foreach ($tried['stages'] as $stage)
                        <button type="button" wire:click="setTryStage('{{ $stage }}')" aria-pressed="{{ $this->tryStage === $stage ? 'true' : 'false' }}">{{ __('shared::games.stages.'.$stage) }}</button>
                    @endforeach
                </div>
                <div class="gs-phone" wire:key="try-{{ $this->trying }}-{{ $this->tryStage }}">
                    <div class="gs-player" wire:ignore data-game-player data-src="{{ $tried['src'] }}" data-items="{{ json_encode($tried['items'], JSON_UNESCAPED_UNICODE) }}"
                         data-stage="{{ $this->tryStage }}" data-locale="ar" data-dir="rtl" data-title="{{ $tried['title'] }}"></div>
                </div>
                <p class="gs-hint">{{ __($l.'draft.preview_hint') }}</p>
            </div>
        @endif
    </x-filament::modal>

    <x-filament::modal id="island-preview" width="xl">
        @if ($this->previewing && ($island = collect($islands)->firstWhere('id', $this->previewing)))
            <x-slot name="heading">{{ $island['emoji'] }} {{ $island['name'] }}</x-slot>
            <div class="gs-scene-box" wire:key="scene-{{ $island['id'] }}">
                @include('shared::games.scene', ['key' => $island['key'], 'residents' => array_slice(array_column($island['creatures'], 'look'), 0, 3), 'colors' => $island['colors']])
            </div>
            <p class="gs-hint">{{ $island['story'] }}</p>
            <div><x-filament::button size="sm" color="gray" icon="heroicon-m-musical-note" x-on:click="const s = window.LmsIslandsSound; if (s) { s.setMuted(false); s.scale({{ \Illuminate\Support\Js::from($island['music']) }}); s.start(); s.cue({{ \Illuminate\Support\Js::from($island['music']) }}); setTimeout(() => s.stop(false), 8000); }">{{ __($l.'island.listen') }}</x-filament::button></div>
        @endif
    </x-filament::modal>
</x-filament-panels::page>

@script
<script>
    // A draft finishes a round or a game is published: the studio follows the console's games channel (LmsLive).
    const live = $wire.$el.querySelector('[data-live-channel]');
    if (live && window.LmsLive) {
        window.LmsLive.watch({ el: live, channel: live.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 60000 });
    }
    // The try modal's player is mounted when it appears (a new look renders a new one).
    // (Filament may move a modal to <body>, so the whole page is watched.)
    const mount = () => document.querySelectorAll('[data-game-player]').forEach((root) => window.LmsGame?.mount(root));
    new MutationObserver(mount).observe(document.body, { childList: true, subtree: true });
    mount();
</script>
@endscript
