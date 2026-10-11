@php
    $l = 'platform::games.';
    $drafts = $this->drafts;
    $islands = $this->world;
    $hex = fn ($c, $fallback) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $fallback;
@endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::games.styles')
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

        <section class="gs-section">
            <h2>{{ __($l.'world') }}</h2>
            <div class="gs-world lms-stagger">
                @foreach ($islands as $island)
                    @php $c = $island['colors']; @endphp
                    <article class="pc-base gs-island" wire:key="island-{{ $island['id'] }}">
                        <header class="gs-island-head" style="background: linear-gradient(135deg, {{ $hex($c['sky'] ?? null, '#5ec8f2') }} 0%, {{ $hex($c['sea'] ?? null, '#1a8fb0') }} 70%, {{ $hex($c['accent'] ?? null, '#ffb703') }} 140%)">
                            <span class="gs-island-emoji" aria-hidden="true">{{ $island['emoji'] }}</span>
                            <div>
                                <h3>{{ $island['name'] }}</h3>
                                <p>{{ $island['story'] }}</p>
                            </div>
                        </header>
                        <div class="gs-creatures" aria-label="{{ __($l.'creatures') }}">
                            @foreach ($island['creatures'] as $creature)
                                <span title="{{ $creature['name'] }} · {{ __('shared::games.rarity.'.$creature['rarity']) }}" data-rarity="{{ $creature['rarity'] }}">{{ $creature['emoji'] }}</span>
                            @endforeach
                        </div>
                        <div class="gs-games">
                            @forelse ($island['games'] as $game)
                                <div class="gs-game" data-status="{{ $game['status'] }}" wire:key="game-{{ $game['id'] }}">
                                    <div class="gs-game-main">
                                        <div>
                                            <strong>{{ $game['title'] }}</strong>
                                            <span class="gs-state" data-state="{{ $game['status'] }}">{{ __($l.'status.'.$game['status']) }}</span>
                                            @if ($game['version'])<small>{{ __($l.'version', ['n' => $game['version']]) }}</small>@endif
                                        </div>
                                        @if ($game['summary'])<p>{{ $game['summary'] }}</p>@endif
                                        <div class="gs-tags">
                                            @foreach ($game['kinds'] as $kind)<span data-kind>{{ $kind }}</span>@endforeach
                                            @foreach ($game['stages'] as $stage)<span>{{ $stage }}</span>@endforeach
                                        </div>
                                    </div>
                                    <div class="gs-game-actions">
                                        @if ($game['version'])
                                            <x-filament::button size="xs" color="gray" icon="heroicon-m-play" wire:click="tryGame('{{ $game['id'] }}')">{{ __($l.'try') }}</x-filament::button>
                                        @endif
                                        <x-filament::button size="xs" icon="heroicon-m-sparkles" wire:click="develop('{{ $game['id'] }}')">{{ __($l.'develop') }}</x-filament::button>
                                        @if ($game['status'] === 'live')
                                            <x-filament::button size="xs" color="gray" outlined wire:click="setStatus('{{ $game['id'] }}', 'retired')" wire:confirm="{{ __($l.'retired_done') }}">{{ __($l.'retire') }}</x-filament::button>
                                        @elseif ($game['version'])
                                            <x-filament::button size="xs" color="success" outlined wire:click="setStatus('{{ $game['id'] }}', 'live')">{{ __($l.'revive') }}</x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="gs-empty">{{ __($l.'no_games') }}</p>
                            @endforelse
                        </div>
                    </article>
                @endforeach
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
