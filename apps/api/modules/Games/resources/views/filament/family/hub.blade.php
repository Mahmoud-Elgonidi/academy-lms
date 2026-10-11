@php
    /*
     * The games for middle school, secondary school and adult students (the approved designs,
     * Middle and Teen): one dark screen in their look. Middle school races itself on the first
     * quest (your last time against your best, your buddy as the racer); secondary school and
     * adults review on a constellation that the week's stars light up. Then the other quests,
     * the games of every island, and the last rounds.
     */
    $l = 'games::games.';
    $h = $l.'hub.';
    $quest = $w['quests'][0] ?? null;
    $race = $look === 'middle';
    $points = [[20, 120], [58, 88], [98, 112], [136, 66], [176, 92], [208, 44], [244, 76], [284, 30], [314, 92], [276, 130], [236, 146], [196, 126], [156, 146], [116, 140]];
    $lit = min(count($points), collect($w['history'])->filter(fn ($r) => $r['at'] !== null && $r['at']->greaterThan(now()->subDays(7)))->sum('stars'));
    $buddy = $w['buddy']['look'] ?? ($w['islands'][0]['creatures'][0]['look'] ?? []);
    $secs = fn (?int $s) => $s === null ? '—' : __($h.'seconds', ['s' => $s]);
    $may = (bool) $w['allowance']['may'];
@endphp
<div class="gw-hub" data-look="{{ $look }}" data-live-channel="{{ $this->channel() }}" wire:key="gw-hub">
    <header class="gw-hub-head">
        <div style="min-width: 0">
            <div class="gw-hub-kicker">{{ $race ? ($quest ? __($h.'from', ['what' => $quest['title'] ?: __($l.'reasons.'.$quest['reason'])]) : __($l.'title.'.$look)) : __($h.'review', ['n' => 5]) }}</div>
            <h1>{{ $race ? __($h.'race') : ($quest ? ($quest['title'] ?: __($l.'reasons.'.$quest['reason'])) : __($l.'title.'.$look)) }}</h1>
        </div>
        @if ($race)
            <div class="gw-hub-pill" aria-label="{{ trans_choice($l.'map.stars', $w['stars'], ['count' => $w['stars']]) }}" dir="ltr">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B"/></svg>
                <span data-count="{{ $w['stars'] }}">{{ $w['stars'] }}</span>
            </div>
        @else
            <div class="gw-hub-pill" aria-label="{{ __($h.'streak_label', ['count' => $w['streak']]) }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--hb-accent)"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></svg>
                {{ trans_choice($h.'streak', $w['streak'], ['count' => $w['streak']]) }}
            </div>
        @endif
    </header>

    @unless ($may)
        <p class="gw-hub-note" role="status">{{ __($l.'refused.'.$w['allowance']['reason']) }}</p>
    @endunless

    @if ($result)
        @php
            $before = $result['best_before'] ?? null;
            $kicker = $before === null ? __($h.'first') : (($result['seconds'] ?? PHP_INT_MAX) < $before ? __($h.'beat') : __($h.'close'));
        @endphp
        <section class="gw-hub-over" role="status" wire:key="over-{{ $result['play'] }}" x-data x-init="LmsIslands.stars({{ (int) $result['stars'] }})"
                 data-celebrate="games-stars-{{ $this->learnerId() }}" data-celebrate-value="{{ $result['total_stars'] }}">
            <div class="gw-hub-over-kicker">{{ $kicker }}</div>
            <h2>{{ __($h.'over', ['correct' => $result['correct'], 'total' => $result['total'], 's' => $secs($result['seconds'] ?? null)]) }}</h2>
            <div class="gw-hub-stars" aria-label="{{ trans_choice($l.'stars', $result['stars'], ['count' => $result['stars']]) }}">
                @for ($i = 1; $i <= 3; $i++)<span data-on="{{ $i <= $result['stars'] ? 'yes' : 'no' }}" style="--i: {{ $i - 1 }}">★</span>@endfor
            </div>
            @if ($result['creature'])
                <p class="gw-hub-hatch">@include('shared::games.creature', ['look' => $result['creature']['look'], 'size' => 40, 'motion' => true]) {{ __($l.'hatched', ['name' => $result['creature']['name']]) }}</p>
            @endif
            @if ($result['homework'])
                <p>✅ {{ __($l.'homework_done', ['title' => $result['homework']]) }}</p>
            @endif
            <p class="gw-hub-sub">{{ __($l.'egg', ['have' => $result['egg']['have'], 'need' => $result['egg']['need']]) }}</p>
            <div class="gw-hub-row">
                <button type="button" class="gw-hub-go" wire:click="again">{{ $race ? __($l.'again') : __($h.'again') }}</button>
                <button type="button" class="gw-hub-ghostbtn" wire:click="leave">{{ __($l.'map.close') }}</button>
            </div>
        </section>
    @endif

    @if ($quest === null)
        <p class="gw-hub-card gw-hub-sub">{{ __($h.'none') }}</p>
    @elseif ($race)
        @php
            $best = $quest['best_seconds'];
            $last = $quest['last_seconds'];
            $ref = max(30, (int) round(max($best ?? 0, $last ?? 0) * 1.3));
            $at = fn (?int $s) => $s === null ? 80 : 6 + 74 * min(1, $s / $ref);
        @endphp
        <section class="gw-hub-card" aria-label="{{ __($h.'race') }}">
            <div class="gw-hub-ends"><span>{{ __($h.'start') }}</span><span>{{ __($h.'finish') }}</span></div>
            <div class="gw-track">
                <i style="top: 30px"></i><i style="top: 94px"></i>
                <svg aria-hidden="true" class="gw-flag" width="22" height="120" viewBox="0 0 22 120"><path d="M4 0 V120" stroke="#FFFFFF" stroke-width="3"/><path d="M6 4 h14 v8 h-14 z M6 12 h7 v8 h-7 z M13 12 h7 v8 h-7 z" fill="#FFFFFF"/><path d="M13 4 h7 v8 h-7 z M6 12 h7 v8 h-7 z" fill="var(--hb-bg)"/></svg>
                @if ($best !== null)
                    <div class="gw-ghost" style="left: {{ $at($best) }}%">
                        @include('shared::games.creature', ['look' => $buddy, 'size' => 56, 'shadow' => true])
                        <small>{{ __($h.'best_ghost') }}</small>
                    </div>
                @endif
                <div class="gw-racer" style="left: {{ $at($last) }}%">
                    <div class="gw-float">@include('shared::games.creature', ['look' => $buddy, 'size' => 60, 'motion' => true, 'line' => '#0B0726'])</div>
                    <small>{{ __($h.'you') }}</small>
                </div>
            </div>
            <div class="gw-timebar">
                <div role="img" aria-label="{{ $secs($last) }}"><b style="width: {{ $last === null ? 100 : round(100 * min(1, $last / $ref)) }}%"></b></div>
                <span dir="ltr">{{ $secs($last ?? $best) }}</span>
            </div>
        </section>
        <section class="gw-hub-card gw-hub-ready">
            <p>{{ __($h.'ready', ['items' => trans_choice($l.'items', $quest['items'], ['count' => $quest['items']]), 'title' => $quest['title'] ?: __($l.'reasons.'.$quest['reason'])]) }}</p>
            <p class="gw-hub-big">{{ $best === null ? __($h.'first_time') : __($h.'best', ['s' => $secs($best)]) }}</p>
            <button type="button" class="gw-hub-go" wire:click="play('{{ $quest['game']['id'] }}', '{{ $quest['key'] }}')" @disabled(! $may)>{{ __($h.'go') }}</button>
        </section>
    @else
        <section class="gw-hub-card" aria-labelledby="gw-const">
            <div class="gw-hub-ends" style="align-items: baseline"><h2 id="gw-const">{{ __($h.'constellation') }}</h2><b style="color: var(--hb-accent)">{{ __($h.'lit', ['lit' => $lit, 'max' => count($points)]) }}</b></div>
            <div role="img" aria-label="{{ __($h.'lit', ['lit' => $lit, 'max' => count($points)]) }}" class="gw-const">
                <svg viewBox="0 0 334 160" width="100%" height="160" aria-hidden="true" preserveAspectRatio="xMidYMid meet">
                    @foreach (array_slice($points, 0, -1) as $i => [$x, $y])
                        <line x1="{{ $x }}" y1="{{ $y }}" x2="{{ $points[$i + 1][0] }}" y2="{{ $points[$i + 1][1] }}" stroke="{{ $i + 1 < $lit ? 'var(--hb-accent)' : '#263248' }}" stroke-width="2"/>
                    @endforeach
                    @foreach ($points as $i => [$x, $y])
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $i < $lit ? 6 : 3.5 }}" fill="{{ $i < $lit ? 'var(--hb-accent)' : '#475569' }}" class="{{ $i < $lit ? 'gw-lit' : '' }}" style="--i: {{ $i }}"/>
                    @endforeach
                </svg>
            </div>
            <div class="gw-hub-sub">{{ __($h.'lit_hint') }}</div>
        </section>
        <section class="gw-hub-deck">
            <span class="gw-hub-kicker" style="color: var(--hb-alt)">{{ __($l.'reasons.'.$quest['reason']) }}</span>
            <strong>{{ $quest['title'] ?: __($l.'reasons.'.$quest['reason']) }}</strong>
            <span class="gw-hub-sub">{{ __($h.'deck', ['items' => $quest['items'], 'game' => $quest['game']['title']]) }}</span>
            <button type="button" class="gw-hub-go" wire:click="play('{{ $quest['game']['id'] }}', '{{ $quest['key'] }}')" @disabled(! $may)>{{ __($h.'start_review') }}</button>
        </section>
    @endif

    @if (count($w['quests']) > 1)
        <h2 class="gw-hub-h2">{{ __($h.'more') }}</h2>
        <div class="gw-hub-list lms-stagger">
            @foreach (array_slice($w['quests'], 1) as $q)
                <button type="button" class="gw-hub-item" wire:click="play('{{ $q['game']['id'] }}', '{{ $q['key'] }}')" @disabled(! $may) wire:key="q-{{ $q['key'] }}">
                    <span class="gw-hub-tag" data-reason="{{ $q['reason'] }}">{{ __($l.'reasons.'.$q['reason']) }}</span>
                    <strong>{{ $q['title'] ?: __($l.'reasons.'.$q['reason']) }}</strong>
                    <small>{{ $q['game']['title'] }} · {{ trans_choice($l.'items', $q['items'], ['count' => $q['items']]) }}@if ($q['best_seconds'] !== null) · {{ __($h.'best', ['s' => $secs($q['best_seconds'])]) }}@endif</small>
                </button>
            @endforeach
        </div>
    @endif

    <h2 class="gw-hub-h2">{{ __($h.'games') }}</h2>
    <div class="gw-hub-games lms-stagger">
        @foreach ($w['islands'] as $island)
            @foreach ($island['games'] as $g)
                <button type="button" class="gw-hub-game" wire:click="play('{{ $g['id'] }}')" @disabled(! $may || ! $g['playable']) wire:key="g-{{ $g['id'] }}">
                    <span class="gw-hub-emoji" aria-hidden="true">{{ $island['emoji'] }}</span>
                    <strong>{{ $g['title'] }}</strong>
                    <small>{{ $g['playable'] ? ($g['plays'] ? str_repeat('★', $g['best']).str_repeat('☆', 3 - $g['best']) : __($l.'map.badge.new')) : __($l.'locked') }}</small>
                </button>
            @endforeach
        @endforeach
    </div>

    @if ($w['recent'] !== [])
        <h2 class="gw-hub-h2">{{ __($h.'recent') }}</h2>
        <ul class="gw-hub-recent">
            @foreach ($w['recent'] as $r)
                <li><span>{{ $r['game'] }}</span><b>{{ str_repeat('★', $r['stars']) }}{{ str_repeat('☆', 3 - $r['stars']) }}</b><small>{{ __($l.'result.score', ['correct' => $r['correct'], 'total' => $r['total']]) }} · {{ $r['at']?->diffForHumans() }}</small></li>
            @endforeach
        </ul>
    @endif
</div>
