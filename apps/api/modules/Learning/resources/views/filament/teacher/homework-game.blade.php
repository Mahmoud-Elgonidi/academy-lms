@php
    /*
     * A lesson's homework as a game (the approved design, Teacher): what was taught, the games that
     * can play it for this student, the practice they will get, when it is due, try and send, and
     * the student's last round. Calm cards in the academy's colours.
     */
    $h = 'learning::homework.game.';
    $student = $this->studentName();
    $items = $this->items;
    $games = $this->games;
    $last = $this->last;
    $tiles = ['#74C0FC', '#FFA94D', '#38D9A9', '#63E6BE', '#B197FC', '#FFD43B'];
@endphp
<x-filament-panels::page>
    <style>
        .hg { max-width: 560px; margin-inline: auto; display: flex; flex-direction: column; gap: 12px; }
        .hg-head { display: flex; align-items: center; gap: 10px; }
        .hg-head a { flex: none; width: 44px; height: 44px; border-radius: 12px; background: white; border: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: center; }
        .hg-head small { display: block; font-size: 12.5px; color: var(--gray-500); }
        .hg-head h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .hg-card { padding: 12px 14px; border-radius: 16px; background: white; border: 1px solid var(--gray-200); }
        .hg-card h2 { margin: 0 0 6px; font-size: 13px; font-weight: 600; color: var(--gray-500); }
        .hg-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .hg-chip { padding: 4px 10px; border-radius: 999px; background: var(--primary-50); color: var(--primary-800); font-size: 13.5px; font-weight: 600; }
        .hg-chip[data-kind="focus"] { background: var(--warning-50); color: var(--warning-800); }
        .hg-chip[data-kind="item"] { background: var(--gray-100); color: var(--gray-700); font-weight: 500; font-size: 12.5px; }
        .hg-title { margin: 0 0 8px; font-size: 16px; font-weight: 600; }
        .hg-games { display: flex; flex-direction: column; gap: 8px; }
        .hg-game { width: 100%; min-height: 64px; padding: 8px 12px; border-radius: 14px; display: flex; align-items: center; gap: 12px; text-align: start; background: white; border: 1px solid var(--gray-200); cursor: pointer; transition: transform .08s; }
        .hg-game:active { transform: scale(.99); }
        .hg-game[aria-checked="true"] { background: var(--primary-50); border: 2px solid var(--primary-600); }
        .hg-game:disabled { opacity: .55; cursor: default; }
        .hg-letter { flex: none; width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; color: #24124F; }
        .hg-game strong { display: block; font-size: 15px; font-weight: 600; }
        .hg-game small { display: block; font-size: 12.5px; color: var(--gray-500); }
        .hg-radio { flex: none; width: 22px; height: 22px; border-radius: 999px; border: 2px solid var(--gray-400); box-sizing: border-box; }
        .hg-game[aria-checked="true"] .hg-radio { border: 7px solid var(--primary-600); }
        .hg-toggle { min-height: 52px; display: flex; align-items: center; justify-content: space-between; gap: 10px; cursor: pointer; font-size: 14.5px; }
        .hg-toggle + .hg-toggle { border-top: 1px solid var(--gray-200); }
        .hg-toggle input { width: 22px; height: 22px; accent-color: var(--primary-600); }
        .hg-info { font-size: 13px; color: var(--gray-500); padding: 8px 0 4px; border-top: 1px solid var(--gray-200); }
        .hg-actions { display: flex; gap: 10px; }
        .hg-try { flex: none; height: 52px; padding: 0 16px; border-radius: 12px; background: white; border: 1px solid var(--gray-300); font-size: 15px; font-weight: 600; cursor: pointer; }
        .hg-send { flex: 1; height: 52px; border-radius: 12px; border: 0; color: white; font-size: 16px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--primary-600); cursor: pointer; }
        .hg-send:disabled, .hg-try:disabled { opacity: .5; cursor: default; }
        .hg-stats { margin-top: 8px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; text-align: center; }
        .hg-stats div { padding: 6px; border-radius: 10px; background: var(--gray-50); }
        .hg-stats b { display: block; font-size: 18px; font-weight: 700; }
        .hg-stats span { font-size: 11.5px; color: var(--gray-500); }
        .hg-skip { text-align: center; font-size: 14px; color: var(--gray-500); }
        .hg-trying { position: fixed; inset: 0; z-index: 50; display: flex; flex-direction: column; background: var(--gray-900); }
        .hg-trying header { display: flex; align-items: center; gap: 10px; padding: 10px 14px; color: white; }
        .hg-trying header div { flex: 1; min-width: 0; }
        .hg-trying header small { display: block; opacity: .7; font-size: 12.5px; }
        .hg-trying .hg-frame { flex: 1; min-height: 0; margin: 0 10px 10px; border-radius: 18px; overflow: hidden; background: white; }
        .hg-trying .lms-game-frame { width: 100%; height: 100%; border: 0; display: block; }
        .dark .hg-card, .dark .hg-game, .dark .hg-head a, .dark .hg-try { background: var(--gray-900); border-color: var(--gray-700); }
        @media (prefers-reduced-motion: no-preference) { .hg-fly { animation: hg-fly .9s cubic-bezier(.4, 0, .2, 1) forwards; } }
        @keyframes hg-fly { to { transform: translate(-220px, -420px) rotate(-30deg) scale(.5); opacity: 0; } }
    </style>

    <div class="hg" x-data="{ flying: false }" x-on:homework-game-sent.window="flying = true; setTimeout(() => flying = false, 950)">
        <header class="hg-head">
            <a href="{{ \Modules\Learning\Filament\Teacher\WriteReport::getUrl(['report' => $this->sessionReport->id]) }}" wire:navigate aria-label="{{ __('learning::teacher.write.title_lesson', ['student' => $student]) }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="{{ app()->getLocale() === 'ar' ? '' : 'transform: scaleX(-1)' }}"><path d="M9 5l7 7-7 7"/></svg>
            </a>
            <div><small>{{ $this->kicker() }}</small><h1>{{ __($h.'page', ['student' => $student]) }}</h1></div>
        </header>

        <section class="hg-card" aria-labelledby="hg-taught">
            <h2 id="hg-taught">{{ __($h.'taught') }}</h2>
            <div class="hg-chips">
                @foreach ($this->taught as $t)
                    @if ($t['outcome'] === 'needs_review')
                        <span class="hg-chip" data-kind="focus">{{ __($h.'focus', ['title' => $t['title']]) }}</span>
                    @else
                        <span class="hg-chip">{{ $t['title'] }}</span>
                    @endif
                @endforeach
            </div>
        </section>

        @if ($items === [])
            <p class="hg-card">{{ __($h.'none') }}</p>
        @else
            <section aria-labelledby="hg-pick">
                <h2 id="hg-pick" class="hg-title">{{ __($h.'pick') }}</h2>
                <div role="radiogroup" aria-labelledby="hg-pick" class="hg-games lms-stagger">
                    @foreach ($games as $i => $g)
                        <button type="button" role="radio" class="hg-game" aria-checked="{{ $this->game === $g['key'] ? 'true' : 'false' }}" wire:click="pick('{{ $g['key'] }}')" @disabled(! $g['fits']) wire:key="hg-{{ $g['key'] }}">
                            <span class="hg-letter" aria-hidden="true" style="background: {{ $tiles[$i % count($tiles)] }}">{{ $g['letter'] }}</span>
                            <span style="flex: 1; min-width: 0">
                                <strong>{{ $g['title'] }}</strong>
                                <small>{{ $g['fits'] ? trans_choice($h.'fits', $g['items'], ['count' => $g['items']]).' · '.\Illuminate\Support\Str::limit($g['summary'], 60) : __($h.'no_fit') }}</small>
                            </span>
                            <span class="hg-radio" aria-hidden="true"></span>
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="hg-card" aria-labelledby="hg-items">
                <h2 id="hg-items">{{ __($h.'items') }}</h2>
                <div class="hg-chips">
                    @foreach (array_slice($items, 0, 6) as $item)
                        <span class="hg-chip" data-kind="item">{{ \Illuminate\Support\Str::limit($item->summary(), 40) }}</span>
                    @endforeach
                    @if (count($items) > 6)<span class="hg-chip" data-kind="item">+{{ count($items) - 6 }}</span>@endif
                </div>
            </section>

            <section class="hg-card" style="padding-block: 4px">
                <label class="hg-toggle"><span>{{ __($h.'before_next') }}</span><input type="checkbox" wire:model="beforeNext"></label>
                <label class="hg-toggle"><span>{{ __($h.'only') }}</span><input type="checkbox" wire:model="practiceOnly"></label>
                <div class="hg-info">⭐ {{ __($h.'egg') }}</div>
            </section>

            <div class="hg-actions">
                <button type="button" class="hg-try" wire:click="tryIt" @disabled($this->game === null)>{{ __($h.'try') }}</button>
                <button type="button" class="hg-send" wire:click="send" @disabled($this->game === null)>
                    <svg :class="flying && 'hg-fly'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>
                    {{ __($h.'send', ['student' => $student]) }}
                </button>
            </div>
            <a class="hg-skip" href="{{ \Modules\Learning\Filament\Teacher\MyReports::getUrl() }}" wire:navigate>{{ __($h.'skip') }}</a>
        @endif

        <section class="hg-card" aria-labelledby="hg-last">
            @if ($last)
                <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 8px">
                    <h2 id="hg-last" class="hg-title" style="margin: 0; font-size: 15px">{{ __($h.'last', ['game' => $last['game']]) }}</h2>
                    <small style="color: var(--gray-500)">{{ $last['at']?->diffForHumans() }}</small>
                </div>
                <div class="hg-stats">
                    <div><b>{{ __($h.'of', ['a' => $last['correct'], 'b' => $last['total']]) }}</b><span>{{ __($h.'right') }}</span></div>
                    <div><b>{{ trans_choice($h.'times', $last['rounds'], ['count' => $last['rounds']]) }}</b><span>{{ __($h.'rounds') }}</span></div>
                    <div><b>{{ __($h.'of', ['a' => $last['stars'], 'b' => 3]) }}</b><span>{{ __($h.'stars') }}</span></div>
                </div>
            @else
                <p id="hg-last" style="margin: 0; color: var(--gray-500); font-size: 14px">{{ __($h.'never') }}</p>
            @endif
        </section>
    </div>

    @if ($trying)
        <div class="hg-trying" role="dialog" aria-modal="true" aria-label="{{ $trying['title'] }}" wire:key="hg-try-{{ $trying['key'] }}">
            <header>
                <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" wire:click="stopTrying" :label="__($h.'close')" />
                <div><strong>{{ $trying['title'] }}</strong><small>{{ __($h.'trying') }}</small></div>
            </header>
            <div class="hg-frame" wire:ignore data-game-player data-try-player
                 data-src="{{ $trying['src'] }}" data-items="{{ json_encode($trying['items'], JSON_UNESCAPED_UNICODE) }}"
                 data-stage="{{ $trying['stage'] }}" data-locale="{{ $trying['locale'] }}" data-dir="{{ $trying['dir'] }}" data-title="{{ $trying['title'] }}"></div>
        </div>
    @endif
</x-filament-panels::page>

@script
<script>
    // Trying a game: the shared player opens it with this lesson's practice; nothing it reports is kept.
    const mount = () => {
        const root = $wire.$el.querySelector('[data-try-player]');
        if (root && window.LmsGame && ! window.LmsGame.of(root)) window.LmsGame.mount(root);
    };
    new MutationObserver(mount).observe($wire.$el, { childList: true, subtree: true });
    mount();
</script>
@endscript
