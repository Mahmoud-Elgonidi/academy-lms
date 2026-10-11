@php
    /*
     * A child's journey (the approved islands design): the course's levels as islands on the sea, from the first
     * at the bottom to the last at the top, each drawn as one of the world's islands (art.key); done ones with a
     * cup, the one they are on with the pin, the ones ahead under clouds. An island opens by zooming in: its
     * scene, and its lessons as a winding path of stations (✓ done, ↺ to review, 📍 here, 🔒 to come), a flag
     * where the teacher's homework is and the lesson's boat at the station they are on. A station opens a
     * sheet with its missions and games. Under the map, the missions, the week's chest and the egg.
     */
    $m = 'learning::map.';
    $name = $this->firstName();
    $islands = $course['islands'] ?? [];
    $n = count($islands);
    $sizes = ['birds' => [200, 190], 'turtles' => [190, 130], 'monkeys' => [190, 170], 'penguins' => [180, 140], 'volcano' => [170, 150]];
    $height = max(600, 150 + max(0, $n - 1) * 140 + 230);
    $spots = [];
    foreach ($islands as $i => $island) {
        [$w, $h] = $sizes[$island['art']['key'] ?? ''] ?? [180, 140];
        $cx = $n === 1 ? 195 : ($i % 2 === 0 ? 112 : 280);
        $cy = $height - 150 - $i * 140;
        $spots[$island['id']] = ['x' => (int) round($cx - $w / 2), 'y' => (int) round($cy - $h * 0.7), 'w' => $w, 'h' => $h, 'cx' => $cx, 'cy' => $cy, 'd' => ['', 'gw-d2', 'gw-d3'][$i % 3]];
    }
    $path = '';
    foreach (array_values($spots) as $i => $s) {
        $path .= $i === 0 ? "M{$s['cx']} {$s['cy']}" : ' C'.$prev['cx'].' '.($prev['cy'] - 70).' '.$s['cx'].' '.($s['cy'] + 70).' '.$s['cx'].' '.$s['cy'];
        $prev = $s;
    }
    $here = collect($islands)->firstWhere('state', 'here');
    $openMissions = collect($j['missions'])->where('state', '!=', 'done');
    $homeworkOn = fn (array $island) => collect($island['stations'])->contains(fn ($s) => collect($s['missions'])->contains(fn ($k) => str_starts_with($k, 'homework:')));
    $hex = fn ($c, $d) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $d;
    $buddy = $games['buddy']['look'] ?? null;
    $album = $this->albumUrl();
    $refusal = $games && ! $games['allowance']['may'] ? __('games::games.refused.'.$games['allowance']['reason']) : '';
    $config = [
        'may' => $games ? (bool) $games['allowance']['may'] : false,
        'refusal' => $refusal,
        'islands' => collect($islands)->mapWithKeys(fn ($island, $i) => [$island['id'] => [
            'open' => $island['state'] !== 'ahead', 'music' => $island['art']['music'] ?? 'major',
            'cx' => $spots[$island['id']]['cx'], 'cy' => $spots[$island['id']]['cy'],
            'why' => __($m.'island.ahead', ['title' => $islands[$i - 1]['title'] ?? $island['title']]),
        ]])->all(),
    ];
    $stars = (int) $j['stars']['total'];
    $rank = $j['stars']['rank'];
@endphp
<div class="jk gw-kids" x-data="LmsIslands.world()" wire:key="jk-{{ $course['enrollment_id'] ?? 'none' }}">
    @include('shared::games.world-styles')
    @include('shared::games.sound')
    @include('shared::games.world-script')
    <div hidden data-gw-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}"></div>

    <div style="min-width: 0">
        <div class="gw-map" style="--gw-h: {{ $height }}px">
            <div class="gw-stage">
                <div class="gw-world" wire:ignore.self :style="worldStyle()" :aria-hidden="view === 'island'">
                    <svg aria-hidden="true" class="gw-abs" style="inset: 0" width="390" height="{{ $height }}" viewBox="0 0 390 {{ $height }}">
                        @foreach ($spots as $s)
                            <ellipse cx="{{ $s['cx'] }}" cy="{{ $s['cy'] + 8 }}" rx="{{ $s['w'] * .62 }}" ry="{{ $s['h'] * .36 }}" fill="#45ADEE"/>
                        @endforeach
                        @if ($path !== '')
                            <path d="{{ $path }}" fill="none" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" stroke-dasharray="1 14" opacity=".85"/>
                        @endif
                    </svg>
                    <div aria-hidden="true">
                        @foreach ([[260, .80, ''], [30, .62, 'gw-d2'], [320, .47, 'gw-d3'], [200, .39, ''], [40, .93, 'gw-d2'], [330, .9, 'gw-d3'], [20, .32, '']] as [$x, $f, $d])
                            <svg class="gw-wave {{ $d }} gw-abs" style="left: {{ $x }}px; top: {{ (int) ($height * $f) }}px" width="40" height="12" viewBox="0 0 40 12"><path d="M2 8 Q10 0 18 8 T34 8" stroke="#FFFFFF" stroke-width="3" fill="none" stroke-linecap="round" opacity=".6"/></svg>
                        @endforeach
                        <svg class="gw-cloud gw-abs" style="left: 0; top: 96px; opacity: .85" width="120" height="48" viewBox="0 0 110 44"><path d="M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z" fill="#FFFFFF"/></svg>
                        <svg class="gw-cloud gw-d2 gw-abs" style="left: 0; top: {{ (int) ($height * .55) }}px; opacity: .6" width="90" height="36" viewBox="0 0 110 44"><path d="M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z" fill="#FFFFFF"/></svg>
                        <div class="gw-boat gw-abs" style="left: 300px; top: {{ $height - 96 }}px"><svg width="58" height="54" viewBox="0 0 58 54"><path d="M28 4 V40" stroke="#7A4A22" stroke-width="3"/><path d="M30 6 L52 36 L30 36 Z" fill="#FFFFFF"/><path d="M26 10 L10 34 L26 34 Z" fill="#FFE066"/><path d="M28 2 L38 6 L28 10 Z" fill="#FF6B6B"/><path d="M4 40 H54 L46 52 H12 Z" fill="#C9622B"/><path d="M4 40 H54" stroke="#8C3F16" stroke-width="2"/></svg></div>
                    </div>

                    @foreach ($islands as $i => $island)
                        @php
                            $s = $spots[$island['id']];
                            $key = $island['art']['key'] ?? '';
                            $aria = __($m.'island.aria_'.$island['state'], ['title' => $island['title']]);
                        @endphp
                        <button type="button" class="gw-isl" data-island="{{ $island['id'] }}" aria-label="{{ $aria }}" style="left: {{ $s['x'] }}px; top: {{ $s['y'] }}px; width: {{ $s['w'] }}px; height: {{ $s['h'] }}px"
                                x-on:click="tap(@js($island['id']))" wire:key="isl-{{ $island['id'] }}">
                            <span wire:ignore.self :class="bob(@js($island['id']), @js($s['d']))">
                                @include('shared::games.island', ['key' => $key, 'locked' => $island['state'] === 'ahead', 'colors' => $island['art']['colors'] ?? [], 'emoji' => $island['art']['emoji'] ?? '🏝️'])
                                @if ($island['state'] === 'ahead')
                                    <span class="gw-label" data-kind="locked">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                        <span>{{ $island['title'] }}</span>
                                    </span>
                                @elseif ($island['state'] === 'here')
                                    <span class="gw-label" data-kind="current"><span>{{ $island['title'] }} · {{ __($m.'island.lessons', ['done' => $island['done'], 'total' => $island['lessons']]) }}</span></span>
                                @else
                                    <span class="gw-label" data-kind="open">🏆 <span>{{ $island['title'] }}</span></span>
                                @endif
                                @if ($homeworkOn($island))
                                    <span class="gw-pin">🚩 {{ __($m.'island.homework') }}</span>
                                @endif
                            </span>
                        </button>
                    @endforeach

                    @if ($here)
                        @php $s = $spots[$here['id']]; @endphp
                        <div class="jk-here" aria-hidden="true" style="left: {{ $s['cx'] - 40 }}px; width: 80px; top: {{ $s['y'] - 34 }}px">
                            <b>📍 {{ __($m.'island.here') }}</b>
                        </div>
                        @if ($buddy)
                            <div aria-hidden="true" class="gw-abs gw-hover" style="left: {{ $s['cx'] + ($s['cx'] < 195 ? 70 : -120) }}px; top: {{ $s['y'] + 10 }}px">
                                @include('shared::games.creature', ['look' => $buddy, 'size' => 50, 'motion' => true])
                            </div>
                        @endif
                    @endif

                    @if ($games && $games['egg']['ready'])
                        <button type="button" class="jk-egg" style="left: {{ ($here && $spots[$here['id']]['cx'] < 195) ? 290 : 30 }}px; top: {{ $here ? $spots[$here['id']]['cy'] + 20 : $height - 200 }}px"
                                x-on:click="hatch(@js($here['art']['key'] ?? null))" aria-label="{{ __($m.'egg.ready') }} {{ __($m.'egg.tap') }}">
                            <svg width="46" height="56" viewBox="0 0 18 22" aria-hidden="true"><ellipse cx="9" cy="12" rx="8" ry="10" fill="#F3E3C3" stroke="#13294B" stroke-width="1.4"/><path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="#13294B" stroke-width="1.2"/><circle cx="6" cy="7" r="1.4" fill="#E0C79A"/><circle cx="12" cy="16" r="1.8" fill="#E0C79A"/></svg>
                            <b>{{ __($m.'egg.ready') }}</b>
                        </button>
                    @endif
                </div>

                <header class="gw-head">
                    <div>
                        @if ($album)
                            <a class="gw-avatar" href="{{ $album }}" wire:navigate aria-label="{{ __($m.'album') }}">
                                @include('shared::games.creature', ['look' => $buddy ?? [], 'size' => 40])
                            </a>
                        @endif
                        <span class="gw-title">{!! __($m.'world', ['name' => '<bdi>'.e($name).'</bdi>']) !!}</span>
                    </div>
                    <div>
                        @unless ($plain)
                            <div class="gw-pill" aria-label="{{ trans_choice($m.'stars', $stars, ['count' => $stars]) }} · {{ __('learning::journey.ranks.'.$rank['key']) }}"
                                 data-celebrate="journey-stars-{{ $this->learner()?->id }}" data-celebrate-value="{{ $stars }}">
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" stroke="#13294B" stroke-width="2" stroke-linejoin="round"/></svg>
                                <span data-count="{{ $stars }}">{{ $stars }}</span>
                            </div>
                        @endunless
                        <button type="button" class="gw-round-btn gw-3d" x-on:click="toggleSound()" :aria-pressed="(! muted).toString()" :aria-label="muted ? @js(__($m.'sound_on')) : @js(__($m.'sound_off'))">
                            <svg x-show="! muted" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3" fill="#FFD43B"/><circle cx="18" cy="16" r="3" fill="#FFD43B"/></svg>
                            <svg x-show="muted" x-cloak width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/><path d="M3 3l18 18"/></svg>
                        </button>
                    </div>
                </header>
                @unless ($plain)
                    <span class="jk-rank">{{ __('learning::journey.ranks.'.$rank['key']) }}</span>
                @endunless

                @if ($islands === [])
                    <div class="gw-note-sea" role="status" style="top: 300px; bottom: auto">{{ $course === null ? __($m.'none') : __($m.'no_program') }}</div>
                @endif
                <template x-if="toast"><div class="gw-toast gw-pop-in" role="status" x-text="toast"></div></template>
            </div>
        </div>

        {{-- An island opened: the circle reveals its scene; the sheet rises with its stations. --}}
        <div class="gw-open" x-show="view === 'island'" x-cloak wire:ignore.self :style="openStyle()" role="dialog" aria-modal="true" :aria-labelledby="'isl-' + id">
            @foreach ($islands as $i => $island)
                @continue($island['state'] === 'ahead')
                @php
                    $key = $island['art']['key'] ?? '';
                    $found = collect($island['creatures'])->where('found', true);
                    $residents = $found->take(3)->pluck('look')->all() ?: array_filter([($island['creatures'][0]['look'] ?? null)]);
                    $stations = $island['stations'];
                    $xs = [50, 74, 50, 26];
                    $points = [];
                    foreach ($stations as $k => $st) {
                        $points[] = [$xs[$k % 4] * 3.58, $k * 92 + 34];
                    }
                    $curve = '';
                    foreach ($points as $k => [$px, $py]) {
                        $curve .= $k === 0 ? "M{$px} {$py}" : ' C'.$points[$k - 1][0].' '.($points[$k - 1][1] + 40).' '.$px.' '.($py - 40).' '.$px.' '.$py;
                    }
                    $boat = collect($j['missions'])->first(fn ($mm) => $mm['kind'] === 'lesson' && $mm['island_id'] === $island['id']);
                    $boatAt = $boat ? collect($stations)->search(fn ($st) => $st['id'] === $boat['station_id']) : false;
                @endphp
                <div class="gw-phone" data-phone="{{ $island['id'] }}" x-show="id === @js($island['id'])" style="background: {{ $hex($island['art']['colors']['sky'] ?? null, '#8ED8FF') }}" wire:key="open-{{ $island['id'] }}">
                    @include('shared::games.scene', ['key' => $key, 'residents' => $residents, 'colors' => $island['art']['colors'] ?? []])
                    <button type="button" class="gw-back gw-3d" x-on:click="back()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
                        {{ __($m.'island.back') }}
                    </button>
                    <section class="gw-sheet gw-sheet-in">
                        @if ($games && ! $games['allowance']['may'])
                            <div class="gw-sheet-note" role="status">{{ $refusal }}</div>
                        @endif
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px">
                            <div style="min-width: 0">
                                <h1 id="isl-{{ $island['id'] }}">{{ $island['title'] }}</h1>
                                <div class="gw-sheet-sub">{{ __($m.'island.n_of', ['n' => $island['index'], 'of' => $n]) }} · {{ __($m.'island.lessons', ['done' => $island['done'], 'total' => $island['lessons']]) }}</div>
                            </div>
                            @if ($island['creatures'] !== [])
                                <span class="gw-chip-progress"><span>{{ __($m.'island.creatures', ['found' => $found->count(), 'total' => count($island['creatures'])]) }}</span></span>
                            @endif
                        </div>

                        <nav aria-label="{{ __($m.'island.stations') }}" class="jk-path" style="height: {{ max(1, count($stations)) * 92 + 16 }}px">
                            <svg aria-hidden="true" viewBox="0 0 358 {{ max(1, count($stations)) * 92 + 16 }}" preserveAspectRatio="none"><path d="{{ $curve }}" fill="none" stroke="#13294B" stroke-opacity=".25" stroke-width="5" stroke-linecap="round" stroke-dasharray="2 12"/></svg>
                            @foreach ($stations as $k => $st)
                                @php
                                    $state = $st['due_review'] && $st['state'] === 'done' ? 'review' : $st['state'];
                                    $icon = ['done' => '✓', 'review' => '↺', 'here' => '📍', 'todo' => '🔒'][$state];
                                    $flag = collect($st['missions'])->contains(fn ($key) => str_starts_with($key, 'homework:'));
                                @endphp
                                <button type="button" class="jk-stop" data-state="{{ $state }}" data-stop="{{ $st['id'] }}" style="--x: {{ $xs[$k % 4] }}%; --k: {{ $k }}"
                                        aria-label="{{ __($m.'station.aria', ['title' => $st['title'], 'state' => __($m.'station.states.'.$st['state'])]) }}"
                                        x-on:click="openStation(@js($st['id']))" wire:key="stop-{{ $st['id'] }}">
                                    <span class="jk-dot">
                                        @if ($state === 'here')<span class="jk-bubble">{{ __($m.'island.here') }}</span>@endif
                                        {{ $icon }}
                                        @if ($flag)<span class="jk-flag" aria-hidden="true">🚩</span>@endif
                                    </span>
                                    <span class="jk-name">{{ $st['title'] }}</span>
                                </button>
                            @endforeach
                            @if ($boat && $boatAt !== false)
                                @php $join = $this->joinUrl($boat); @endphp
                                @php $side = $xs[$boatAt % 4] <= 50 ? 'right: 6px' : 'left: 6px'; @endphp
                                @if ($join)
                                    <a class="jk-boat" data-open href="{{ $join }}" style="--k: {{ $boatAt }}; {{ $side }}">⛵ {{ __($m.'mission.join') }}</a>
                                @else
                                    <span class="jk-boat" style="--k: {{ $boatAt }}; {{ $side }}">⛵ {{ $boat['starts_at']->isPast() ? __($m.'mission.now') : __($m.'mission.in', ['when' => $this->until($boat['starts_at'])]) }}</span>
                                @endif
                            @endif
                        </nav>

                        @if ($games)
                            <div class="gw-minicards">
                                @if ($album)
                                    <a href="{{ $this->albumUrl($key ?: null) }}" wire:navigate class="gw-card3d" style="flex: 1; min-width: 0; background: #F3F0FF">
                                        <span style="display: block; font-size: 13px; font-weight: 800">{{ __($m.'island.creatures', ['found' => $found->count(), 'total' => count($island['creatures'])]) }}</span>
                                        <span style="display: flex; align-items: center; gap: 4px; margin-top: 4px">
                                            @foreach ($found->take(3) as $c)
                                                @include('shared::games.creature', ['look' => $c['look'], 'size' => 34])
                                            @endforeach
                                            @if (count($island['creatures']) > $found->count())
                                                <span class="gw-more">+{{ count($island['creatures']) - $found->count() }}</span>
                                            @endif
                                        </span>
                                    </a>
                                @endif
                                <a href="{{ $album ?? '#' }}" wire:navigate class="gw-card3d" style="flex: none; width: 128px; background: #FFF4D6; display: flex; align-items: center; gap: 6px">
                                    @if ($island['next_creature'])
                                        <svg width="30" height="36" viewBox="0 0 18 22" aria-hidden="true"><ellipse cx="9" cy="12" rx="8" ry="10" fill="{{ $hex($island['next_creature']['look']['belly'] ?? null, '#F3E3C3') }}" stroke="#13294B" stroke-width="1.6"/><path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="#13294B" stroke-width="1.4"/></svg>
                                        <span style="font-size: 12.5px; font-weight: 800; line-height: 1.2">{{ __($m.'egg.progress', ['have' => $games['egg']['have'], 'need' => $games['egg']['need']]) }}</span>
                                    @else
                                        <span style="font-size: 12.5px; font-weight: 800; line-height: 1.2">🏆 {{ __($m.'island.all_found') }}</span>
                                    @endif
                                </a>
                            </div>
                        @endif
                    </section>

                    @foreach ($stations as $st)
                        @include('learning::filament.family.map.parts.kids-station', ['st' => $st, 'island' => $island])
                    @endforeach
                </div>
            @endforeach
            <template x-if="toast && view === 'island'"><div class="gw-toast gw-toast-open gw-pop-in" role="status" x-text="toast"></div></template>
        </div>
    </div>

    <div class="jk-side">
        @include('learning::filament.family.map.parts.greetings', ['kids' => true])

        @if (count($j['courses']) > 1)
            <div class="jk-chips" role="group" aria-label="{{ __($m.'courses') }}">
                @foreach ($j['courses'] as $c)
                    <button type="button" aria-pressed="{{ $c['enrollment_id'] === ($course['enrollment_id'] ?? null) ? 'true' : 'false' }}" wire:click="chooseCourse('{{ $c['enrollment_id'] }}')" wire:key="cc-{{ $c['enrollment_id'] }}">{{ $c['subject'] }}</button>
                @endforeach
            </div>
        @endif

        <section aria-labelledby="jk-missions">
            <h2 id="jk-missions" class="jm-h" style="margin-bottom: .4rem">{{ __($m.'mission.title') }}</h2>
            @if ($j['missions'] === [])
                <div class="jk-card">{{ __($m.'mission.none') }}</div>
            @else
                <div class="jk-strip">
                    @foreach ($j['missions'] as $i => $mission)
                        @continue($mission['kind'] === 'review' && $mission['enrollment_id'] !== ($course['enrollment_id'] ?? null))
                        @include('learning::filament.family.map.parts.kids-mission', ['mission' => $mission, 'i' => $i])
                    @endforeach
                </div>
            @endif
        </section>

        @include('learning::filament.family.map.parts.chest', ['kids' => true])
    </div>
</div>
