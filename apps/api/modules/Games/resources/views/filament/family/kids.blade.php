@php
    /*
     * A child's islands world (the approved design, Main): the sea map with its islands (open ones
     * have a game for this look; the current one holds the first quest), the creatures they
     * hatched flying around, and an island opened by zooming in: its scene and a sheet with its
     * games (each says which quest it plays), its creatures and the next egg.
     */
    $l = 'games::games.';
    $m = $l.'map.';
    $spots = [
        // key => [left, top, width, height, centre x, centre y, bob delay]
        'volcano' => [35, 40, 170, 150, 120, 150, 'gw-d2'],
        'penguins' => [194, 120, 180, 140, 284, 215, ''],
        'monkeys' => [13, 210, 190, 170, 108, 330, 'gw-d3'],
        'turtles' => [187, 390, 190, 130, 282, 470, 'gw-d2'],
        'birds' => [18, 470, 200, 190, 118, 610, ''],
    ];
    $extra = 0;
    foreach ($w['islands'] as $island) {
        if (! isset($spots[$island['key']])) {
            [$x, $y] = [$extra % 2 === 0 ? 190 : 18, 700 + 160 * $extra];
            $spots[$island['key']] = [$x, $y, 180, 140, $x + 90, $y + 95, $extra % 2 ? 'gw-d2' : 'gw-d3'];
            $extra++;
        }
    }
    $height = max(760, ...array_map(fn ($s) => $s[1] + $s[3] + 40, array_values($spots)));
    $secret = 'volcano';   // the world's hidden island, as the design keeps it
    $first = $w['quests'][0]['game']['island'] ?? null;
    $current = collect($w['islands'])->firstWhere('key', $first)['key'] ?? collect($w['islands'])->firstWhere('open', true)['key'] ?? null;
    $hex = fn ($c, $d) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $d;
    $default = $w['islands'][0]['creatures'][0]['look'] ?? [];
    $art = fn (array $g) => ! $g['playable'] ? 'lock' : (['bird-gates' => 'gates', 'bird-balloons' => 'balloons', 'bird-twins' => 'cards', 'turtle-bridge' => 'bridge'][$g['key']]
        ?? (in_array('pair', $g['kinds'], true) ? 'cards' : (in_array('choice', $g['kinds'], true) ? 'balloons' : 'gates')));
    $paint = ['gates' => ['#D0EBFF', '#74C0FC'], 'balloons' => ['#FFE8CC', '#FFA94D'], 'cards' => ['#C3FAE8', '#38D9A9'], 'bridge' => ['#C3FAE8', '#63E6BE'], 'lock' => ['#F1F3F5', '#DEE2E6']];
    $album = \Modules\Games\Filament\Family\FamilyCreatures::getUrl();
    $config = [
        'may' => (bool) $w['allowance']['may'],
        'refusal' => $w['allowance']['may'] ? '' : __($l.'refused.'.$w['allowance']['reason']),
        'open' => $openIsland ?? null,
        'islands' => collect($w['islands'])->mapWithKeys(fn ($i) => [$i['key'] => [
            'open' => $i['open'], 'music' => $i['music'], 'cx' => $spots[$i['key']][4], 'cy' => $spots[$i['key']][5],
            'why' => $i['key'] === $secret ? __($m.'secret') : __($m.'locked', ['island' => $i['name']]),
        ]])->all(),
    ];
@endphp
<div class="gw-kids" x-data="LmsIslands.world()" data-live-channel="{{ $this->channel() }}" wire:key="gw-world">
    <div hidden data-gw-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}"></div>
    <div class="gw-map" style="--gw-h: {{ $height }}px">
        <div class="gw-stage">
            <div class="gw-world" wire:ignore.self :style="worldStyle()" :aria-hidden="view === 'island'">
                <svg aria-hidden="true" class="gw-abs" style="inset: 0" width="390" height="{{ $height }}" viewBox="0 0 390 {{ $height }}">
                    <ellipse cx="118" cy="618" rx="128" ry="70" fill="#45ADEE"/>
                    <ellipse cx="282" cy="478" rx="118" ry="56" fill="#45ADEE"/>
                    <ellipse cx="108" cy="340" rx="118" ry="62" fill="#45ADEE"/>
                    <ellipse cx="284" cy="226" rx="110" ry="54" fill="#45ADEE"/>
                    <ellipse cx="120" cy="164" rx="104" ry="56" fill="#3EA4EA"/>
                    <path d="M150 650 C220 620 270 560 282 506 C270 450 170 420 120 376 C150 300 250 290 282 250 C250 210 180 200 132 186" fill="none" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" stroke-dasharray="1 14" opacity=".85"/>
                </svg>
                <div aria-hidden="true">
                    @foreach ([[260, 610, '', .6], [30, 470, 'gw-d2', .6], [320, 360, 'gw-d3', .6], [200, 300, '', .5], [40, 720, 'gw-d2', .55], [330, 700, 'gw-d3', .55], [20, 250, '', .5]] as [$x, $y, $d, $o])
                        <svg class="gw-wave {{ $d }} gw-abs" style="left: {{ $x }}px; top: {{ $y }}px" width="40" height="12" viewBox="0 0 40 12"><path d="M2 8 Q10 0 18 8 T34 8" stroke="#FFFFFF" stroke-width="3" fill="none" stroke-linecap="round" opacity="{{ $o }}"/></svg>
                    @endforeach
                    @foreach ([[230, 410, 12, ''], [60, 690, 10, 'gw-d2'], [350, 560, 12, 'gw-d3']] as [$x, $y, $s, $d])
                        <svg class="gw-twinkle {{ $d }} gw-abs" style="left: {{ $x }}px; top: {{ $y }}px" width="{{ $s }}" height="{{ $s }}" viewBox="0 0 14 14"><path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill="#FFFFFF"/></svg>
                    @endforeach
                </div>

                @foreach (collect($w['islands'])->sortBy(fn ($i) => $spots[$i['key']][1]) as $island)
                    @php
                        [$x, $y, $bw, $bh, , , $delay] = $spots[$island['key']];
                        $found = collect($island['creatures'])->where('found', true)->count();
                        $kind = ! $island['open'] ? 'locked' : ($island['key'] === $current ? 'current' : 'open');
                        $aria = __($island['homework'] ? $m.'aria_homework' : ($island['open'] ? $m.'aria_open' : $m.'aria_locked'), ['island' => $island['name']]);
                    @endphp
                    <button type="button" class="gw-isl" data-island="{{ $island['key'] }}" aria-label="{{ $aria }}" style="left: {{ $x }}px; top: {{ $y }}px; width: {{ $bw }}px; height: {{ $bh }}px"
                            x-on:click="tap(@js($island['key']))" wire:key="isl-{{ $island['key'] }}">
                        <span wire:ignore.self :class="bob(@js($island['key']), @js($delay))">
                            @include('shared::games.island', ['key' => $island['key'], 'locked' => ! $island['open'], 'colors' => $island['colors'], 'emoji' => $island['emoji']])
                            @if ($kind === 'locked')
                                <span class="gw-label" data-kind="locked">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                    {{ $island['key'] === $secret ? __($m.'secret_label') : $island['name'] }}
                                </span>
                            @elseif ($kind === 'current')
                                <span class="gw-label" data-kind="current">{{ $island['name'] }} · {{ __($m.'of', ['found' => $found, 'total' => count($island['creatures'])]) }}</span>
                            @else
                                <span class="gw-label" data-kind="open">{{ $island['name'] }}@if ($island['fresh'])<b>{{ __($m.'new') }}</b>@endif</span>
                            @endif
                            @if ($island['homework'])
                                <span class="gw-pin"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/></svg>{{ __($m.'homework') }}</span>
                            @endif
                        </span>
                    </button>
                @endforeach

                @php $birds = collect(collect($w['islands'])->firstWhere('key', 'birds')['creatures'] ?? [])->where('found', true)->take(3)->values(); @endphp
                @if ($birds->isNotEmpty())
                    {{-- The birds the child hatched circle their island. --}}
                    <div aria-hidden="true" class="gw-abs" style="left: 106px; top: 506px; width: 24px; height: 24px; transform: scaleY(.42)">
                        @foreach ($birds as $i => $bird)
                            @php $d = ['', 'gw-d2', 'gw-d3'][$i]; @endphp
                            <div class="gw-orbit {{ $d }} gw-abs" style="inset: 0"><div class="gw-abs" style="left: 86px; top: -6px"><div class="gw-counter {{ $d }}"><div style="transform: scaleY(2.38)">
                                @include('shared::games.creature', ['look' => $bird['look'], 'size' => [36, 30, 32][$i], 'motion' => true])
                            </div></div></div></div>
                        @endforeach
                    </div>
                @endif
                @php $swimmer = collect(collect($w['islands'])->firstWhere('key', 'turtles')['creatures'] ?? [])->firstWhere('found', true); @endphp
                @if ($swimmer)
                    <div aria-hidden="true" class="gw-abs" style="left: 252px; top: 524px; width: 60px; height: 44px"><span class="gw-swim gw-abs" style="left: 0; top: 0">
                        @include('shared::games.creature', ['look' => $swimmer['look'], 'size' => 44, 'motion' => true])
                    </span></div>
                @endif

                <div aria-hidden="true" class="gw-fish gw-abs" style="left: 340px; top: 620px"><svg width="24" height="14" viewBox="0 0 24 14"><path d="M2 7 C6 1 14 1 18 7 C14 13 6 13 2 7 Z" fill="#FF922B"/><path d="M18 7 L24 2 L24 12 Z" fill="#FF922B"/><circle cx="6" cy="6" r="1.4" fill="#13294B"/></svg></div>
                <div aria-hidden="true" class="gw-boat gw-abs" style="left: 236px; top: 664px"><svg width="58" height="54" viewBox="0 0 58 54"><path d="M28 4 V40" stroke="#7A4A22" stroke-width="3"/><path d="M30 6 L52 36 L30 36 Z" fill="#FFFFFF"/><path d="M26 10 L10 34 L26 34 Z" fill="#FFE066"/><path d="M28 2 L38 6 L28 10 Z" fill="#FF6B6B"/><path d="M4 40 H54 L46 52 H12 Z" fill="#C9622B"/><path d="M4 40 H54" stroke="#8C3F16" stroke-width="2"/></svg></div>
                <div aria-hidden="true">
                    <svg class="gw-cloud gw-abs" style="left: 0; top: 96px; opacity: .85" width="120" height="48" viewBox="0 0 110 44"><path d="M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z" fill="#FFFFFF"/></svg>
                    <svg class="gw-cloud gw-d2 gw-abs" style="left: 0; top: 400px; opacity: .6" width="90" height="36" viewBox="0 0 110 44"><path d="M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z" fill="#FFFFFF"/></svg>
                </div>
            </div>

            <header class="gw-head">
                <div>
                    <a class="gw-avatar" href="{{ $album }}" wire:navigate aria-label="{{ __($m.'album') }}">
                        @include('shared::games.creature', ['look' => $w['buddy']['look'] ?? $default, 'size' => 40])
                    </a>
                    <span class="gw-title">{{ __($m.'world', ['name' => $learnerName]) }}</span>
                </div>
                <div>
                    <div class="gw-pill" aria-label="{{ trans_choice($m.'stars', $w['stars'], ['count' => $w['stars']]) }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" stroke="#13294B" stroke-width="2" stroke-linejoin="round"/></svg>
                        <span data-count="{{ $w['stars'] }}">{{ $w['stars'] }}</span>
                    </div>
                    <button type="button" class="gw-round-btn gw-3d" x-on:click="toggleSound()" :aria-pressed="(! muted).toString()" :aria-label="muted ? @js(__($m.'sound_on')) : @js(__($m.'sound_off'))">
                        <svg x-show="! muted" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3" fill="#FFD43B"/><circle cx="18" cy="16" r="3" fill="#FFD43B"/></svg>
                        <svg x-show="muted" x-cloak width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/><path d="M3 3l18 18"/></svg>
                    </button>
                </div>
            </header>

            <template x-if="toast"><div class="gw-toast gw-pop-in" role="status" x-text="toast"></div></template>
            @unless ($w['allowance']['may'])
                <div class="gw-note-sea" role="status">{{ __($l.'refused.'.$w['allowance']['reason']) }}</div>
            @endunless
        </div>
    </div>

    {{-- An island opened: the circle reveals its scene, the sheet rises with its games. --}}
    <div class="gw-open" x-show="view === 'island'" x-cloak wire:ignore.self :style="openStyle()" role="dialog" aria-modal="true" :aria-labelledby="'isl-' + id">
        @foreach ($w['islands'] as $island)
            @continue(! $island['open'])
            @php
                $found = collect($island['creatures'])->where('found', true);
                $residents = $found->take(3)->pluck('look')->all() ?: [($island['creatures'][0]['look'] ?? [])];
                $questGame = collect($island['games'])->first(fn ($g) => $g['quest'] !== null);
                $next = $island['next'];
            @endphp
            <div class="gw-phone" data-phone="{{ $island['key'] }}" x-show="id === @js($island['key'])" style="background: {{ $hex($island['colors']['sky'] ?? null, '#8ED8FF') }}" wire:key="open-{{ $island['key'] }}">
                @include('shared::games.scene', ['key' => $island['key'], 'residents' => $residents, 'colors' => $island['colors']])
                <button type="button" class="gw-back gw-3d" x-on:click="back()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
                    {{ __($m.'back') }}
                </button>
                <section class="gw-sheet gw-sheet-in">
                    @unless ($w['allowance']['may'])
                        <div class="gw-sheet-note" role="status">{{ __($l.'refused.'.$w['allowance']['reason']) }}</div>
                    @endunless
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px">
                        <div style="min-width: 0">
                            <h1 id="isl-{{ $island['key'] }}">{{ $island['name'] }}</h1>
                            <div class="gw-sheet-sub">{{ $island['story'] }}</div>
                        </div>
                        <span class="gw-chip-progress">
                            @if ($questGame)
                                <span>{{ $questGame['quest']['title'] ?: __($l.'reasons.'.$questGame['quest']['reason']) }}</span>
                                <span>{{ trans_choice($l.'items', $questGame['quest']['items'], ['count' => $questGame['quest']['items']]) }}</span>
                            @else
                                <span>{{ __($m.'creatures') }}</span>
                                <span>{{ __($m.'of', ['found' => $found->count(), 'total' => count($island['creatures'])]) }}</span>
                            @endif
                        </span>
                    </div>

                    <nav aria-label="{{ __($m.'games') }}" style="display: flex; flex-direction: column; gap: 10px">
                        @foreach ($island['games'] as $i => $g)
                            @php
                                $a = $art($g);
                                [$bg, $tile] = $paint[$a];
                                $badge = ($g['quest']['reason'] ?? null) === 'homework' ? ['homework', __($m.'badge.homework')]
                                    : (! $g['playable'] ? ['soon', __($m.'badge.soon')]
                                    : ($g['plays'] === 0 ? ['new', __($m.'badge.new')] : ['stars', trans_choice($m.'stars', $g['best'], ['count' => $g['best']])]));
                                $what = $g['quest'] ? __($l.'reasons.'.$g['quest']['reason']).': '.($g['quest']['title'] ?: $g['summary']) : ($g['playable'] ? $g['summary'] : __($l.'locked'));
                            @endphp
                            <button type="button" class="gw-gamecard gw-rise @if ($g['playable']) gw-card3d @endif" style="--i: {{ $i }}; background: {{ $bg }}; box-shadow: 0 5px 0 #13294B"
                                    aria-disabled="{{ $g['playable'] ? 'false' : 'true' }}" wire:key="g-{{ $g['id'] }}"
                                    x-on:click="play(@js($g['id']), @js($g['quest']['key'] ?? null), @js($g['playable']), @js(__($l.'locked')))">
                                <span class="gw-tile" style="background: {{ $tile }}">
                                    @switch($a)
                                        @case('gates')<svg width="50" height="44" viewBox="0 0 50 44" aria-hidden="true"><ellipse cx="34" cy="22" rx="10" ry="16" fill="none" stroke="#FFD43B" stroke-width="5"/><g transform="translate(2 12) scale(0.3)"><ellipse cx="50" cy="44" rx="30" ry="25" fill="#FFFFFF" stroke="#13294B" stroke-width="7"/><path d="M22 40 L6 46 L22 52 Z" fill="#FF922B"/><path d="M48 40 C50 14 80 8 84 24 C78 34 66 42 56 46 Z" fill="#4DABF7" stroke="#13294B" stroke-width="7"/></g></svg>@break
                                        @case('balloons')<svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true"><ellipse cx="16" cy="18" rx="11" ry="13" fill="#FF6B6B" stroke="#13294B" stroke-width="2.5"/><ellipse cx="32" cy="16" rx="10" ry="12" fill="#38D9A9" stroke="#13294B" stroke-width="2.5"/><path d="M16 31 Q18 38 15 44 M32 28 Q34 36 31 44" stroke="#13294B" stroke-width="1.6" fill="none"/></svg>@break
                                        @case('cards')<svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true"><g transform="rotate(-12 18 24)"><rect x="6" y="8" width="22" height="30" rx="4" fill="#6236E0" stroke="#13294B" stroke-width="2.5"/></g><g transform="rotate(10 30 24)"><rect x="20" y="6" width="22" height="30" rx="4" fill="#FFFFFF" stroke="#13294B" stroke-width="2.5"/><path d="M24 16 h14 M24 22 h10" stroke="#13294B" stroke-width="2.5" stroke-linecap="round"/></g></svg>@break
                                        @case('bridge')<svg width="50" height="40" viewBox="0 0 90 60" aria-hidden="true"><ellipse cx="18" cy="34" rx="12" ry="10" fill="#8CE99A" stroke="#13294B" stroke-width="3"/><ellipse cx="48" cy="34" rx="28" ry="20" fill="#2F9E44" stroke="#13294B" stroke-width="3"/><text x="48" y="41" text-anchor="middle" font-size="20" font-weight="800" fill="#FFFFFF">م</text></svg>@break
                                        @default<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                    @endswitch
                                </span>
                                <span style="flex: 1; min-width: 0">
                                    <span style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap"><strong>{{ $g['title'] }}</strong><span class="gw-badge" data-kind="{{ $badge[0] }}">{{ $badge[1] }}</span></span>
                                    <span class="gw-what">{{ $what }}</span>
                                </span>
                                @if ($g['playable'])
                                    <span class="gw-playdot" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M7 4v16l13-8z" fill="#13294B"/></svg></span>
                                @endif
                            </button>
                        @endforeach
                    </nav>

                    <div class="gw-minicards gw-rise" style="--i: {{ count($island['games']) }}">
                        <a href="{{ \Modules\Games\Filament\Family\FamilyCreatures::getUrl(['island' => $island['key']]) }}" wire:navigate class="gw-card3d" style="flex: 1; min-width: 0; background: #F3F0FF">
                            <span style="display: block; font-size: 13px; font-weight: 800">{{ __($m.'creatures_card', ['found' => $found->count(), 'total' => count($island['creatures'])]) }}</span>
                            <span style="display: flex; align-items: center; gap: 4px; margin-top: 4px">
                                @foreach ($found->take(3) as $c)
                                    @include('shared::games.creature', ['look' => $c['look'], 'size' => 34])
                                @endforeach
                                @if (count($island['creatures']) > $found->count())
                                    <span class="gw-more">+{{ count($island['creatures']) - $found->count() }}</span>
                                @endif
                            </span>
                        </a>
                        <a href="{{ $album }}" wire:navigate class="gw-card3d" style="flex: none; width: 128px; background: #FFF4D6; display: flex; align-items: center; gap: 6px">
                            @if ($next)
                                <svg width="30" height="36" viewBox="0 0 18 22" aria-hidden="true"><ellipse cx="9" cy="12" rx="8" ry="10" fill="{{ $hex($next['look']['belly'] ?? null, '#F3E3C3') }}" stroke="#13294B" stroke-width="1.6"/><path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="#13294B" stroke-width="1.4"/><circle cx="6" cy="7" r="1.6" fill="{{ $hex($next['look']['body'] ?? null, '#E0C79A') }}"/><circle cx="12" cy="16" r="2" fill="{{ $hex($next['look']['body'] ?? null, '#E0C79A') }}"/></svg>
                                <span style="font-size: 12.5px; font-weight: 800; line-height: 1.2">{{ __($m.'egg_card', ['have' => $w['egg']['have'], 'need' => $w['egg']['need']]) }}</span>
                            @else
                                <span style="font-size: 12.5px; font-weight: 800; line-height: 1.2">🏆 {{ __($m.'island_done') }}</span>
                            @endif
                        </a>
                    </div>
                </section>
            </div>
        @endforeach
        <template x-if="toast && view === 'island'"><div class="gw-toast gw-toast-open gw-pop-in" role="status" x-text="toast"></div></template>
    </div>
</div>
