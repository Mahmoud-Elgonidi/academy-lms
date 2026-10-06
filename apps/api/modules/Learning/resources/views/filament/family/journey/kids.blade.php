{{--
    Children: an adventure map. Each level is an island, each lesson a stop on
    a winding path: a star when mastered, the child's own marker where they are,
    ↻ to practise again, grey still to come, and a treasure at the end. The
    home shows the island they are on (a window of 8 stops on long ones); the
    map page shows every island.
--}}
@php
    $j = 'learning::journey.kids.';
    $courses = $journey['courses'] ?? [];
    $first = strtok((string) ($learner?->name ?? ''), ' ');
    $zigzag = [50, 74, 50, 26];
    $curve = function (array $points): string {
        $d = '';
        foreach ($points as $i => [$x, $y]) {
            if ($i === 0) {
                $d = "M{$x} {$y}";
                continue;
            }
            [$px, $py] = $points[$i - 1];
            $d .= ' C'.$px.' '.($py + 6).' '.$x.' '.($y - 6).' '.$x.' '.$y;
        }

        return $d;
    };
@endphp
<div class="jk">
    <style>
        .jk { --row: 6.4rem; display: grid; gap: 1.1rem; }
        /* Phones: hello, then the map, then the rest; wide screens: the map with a side column. */
        .jk-side { display: contents; }
        .jk-hello { order: 0; }
        .jk-course-list { order: 1; display: flex; flex-direction: column; gap: 1.25rem; }
        .jk-side > :not(.jk-hello) { order: 2; }
        @media (min-width: 1024px) {
            .jk { grid-template-columns: minmax(0, 1fr) 19rem; align-items: start; }
            .jk-side { display: flex; flex-direction: column; gap: .9rem; position: sticky; top: 5.5rem; order: 2; }
        }
        .jk-card { background: white; border: 2px solid var(--gray-200); border-radius: 1.75rem; box-shadow: 0 5px 0 var(--gray-200); padding: 1rem 1.15rem; }
        .jk-hello { display: flex; align-items: center; gap: .9rem; background: linear-gradient(135deg, var(--primary-500), var(--primary-700)); color: white; border: 0; box-shadow: 0 5px 0 var(--primary-800); }
        .jk-hello strong { display: block; font-size: 1.2rem; line-height: 1.35; }
        .jk-hello small { opacity: .9; font-size: .9rem; }
        .jk-starbox { flex: none; display: grid; place-items: center; width: 4.6rem; height: 4.6rem; border-radius: 1.4rem; background: white; color: var(--warning-600); box-shadow: 0 4px 0 color-mix(in oklab, var(--primary-900) 40%, transparent); }
        .jk-starbox b { font-size: 1.6rem; line-height: 1; }
        .jk-starbox span { font-size: 1.15rem; line-height: 1; }
        .jk-chip { display: inline-flex; align-items: center; gap: .3rem; font-weight: 700; font-size: .92rem; padding: .3rem .8rem; border-radius: 999px; background: var(--warning-50); color: var(--warning-800); border: 2px solid var(--warning-200); }
        .jk-chip[data-tone="done"] { background: var(--success-50); color: var(--success-800); border-color: var(--success-200); }
        .jk-chip[data-tone="soon"] { background: var(--gray-50); color: var(--gray-600); border-color: var(--gray-200); }
        .jk-chips { display: flex; flex-wrap: wrap; gap: .45rem; }
        .jk-course { display: flex; flex-direction: column; gap: .8rem; }
        .jk-course-head { display: flex; justify-content: space-between; align-items: baseline; gap: .6rem; flex-wrap: wrap; }
        .jk-course-head h3 { margin: 0; font-size: 1.3rem; font-weight: 800; }
        .jk-course-head small, .jk-soft { color: var(--gray-500); font-size: .95rem; }
        .jk-island { border-radius: 2rem; padding: .9rem .9rem 1rem; background: linear-gradient(180deg, color-mix(in oklab, var(--info-100) 70%, white), color-mix(in oklab, var(--success-100) 60%, white)); border: 3px solid color-mix(in oklab, var(--info-200) 80%, white); }
        .jk-island-head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .jk-island-head span { font-size: .85rem; font-weight: 700; color: var(--info-800); }
        .jk-island-head strong { display: block; font-size: 1.1rem; }
        .jk-map { max-width: 30rem; margin: 0 auto; padding: 2rem 0 .5rem; }
        .jk-track { position: relative; height: calc(var(--rows) * var(--row)); }
        .jk-track svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
        .jk-track path { fill: none; stroke-width: 7; stroke-linecap: round; vector-effect: non-scaling-stroke; }
        .jk-road { stroke: color-mix(in oklab, var(--gray-400) 55%, white); stroke-dasharray: 2 14; }
        .jk-walked { stroke: var(--warning-400); }
        .jk-stop { position: absolute; left: var(--x); top: calc((var(--k) + .5) * var(--row)); transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; gap: .25rem; width: 8.5rem; text-align: center; }
        .jk-dot { display: grid; place-items: center; width: 3.5rem; height: 3.5rem; border-radius: 50%; font-size: 1.5rem; font-weight: 800; line-height: 1; border: 3px solid white; }
        .jk-name { font-size: .82rem; font-weight: 700; line-height: 1.25; color: var(--gray-700); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; background: color-mix(in oklab, white 75%, transparent); border-radius: .5rem; padding: 0 .3rem; }
        .jk-stop[data-state="done"] .jk-dot { background: var(--warning-400); color: white; box-shadow: 0 4px 0 var(--warning-600); }
        .jk-stop[data-state="review"] .jk-dot { background: var(--info-400); color: white; box-shadow: 0 4px 0 var(--info-600); }
        .jk-stop[data-state="todo"] .jk-dot { background: var(--gray-200); color: var(--gray-500); box-shadow: 0 4px 0 var(--gray-300); font-size: 1.15rem; }
        .jk-stop[data-state="here"] .jk-dot { width: 4.3rem; height: 4.3rem; background: var(--primary-500); color: white; font-size: 1.6rem; box-shadow: 0 5px 0 var(--primary-700), 0 0 0 .45rem color-mix(in oklab, var(--primary-400) 30%, transparent); }
        .jk-stop[data-state="here"] .jk-name { color: var(--primary-800); }
        .jk-bubble { position: absolute; bottom: calc(100% - .2rem); white-space: nowrap; background: var(--gray-900); color: white; font-weight: 800; font-size: .85rem; padding: .25rem .7rem; border-radius: 999px; }
        .jk-bubble::after { content: ''; position: absolute; top: 100%; left: 50%; margin-left: -.35rem; border: .35rem solid transparent; border-top-color: var(--gray-900); }
        .jk-goal .jk-dot { width: 4rem; height: 4rem; background: white; border: 3px dashed var(--warning-400); font-size: 1.9rem; }
        .jk-goal[data-state="done"] .jk-dot { border-style: solid; background: var(--warning-100); box-shadow: none; }
        .jk-more { text-align: center; font-size: .82rem; font-weight: 700; color: var(--gray-600); }
        .jk-left { margin: .3rem 0 0; text-align: center; font-weight: 800; font-size: 1.05rem; color: var(--gray-800); }
        .jk-homework { display: flex; align-items: center; justify-content: space-between; gap: .75rem; text-decoration: none; color: inherit; }
        .jk-homework strong { font-size: 1.05rem; }
        .jk-go { flex: none; font-weight: 800; color: white; background: var(--success-500); box-shadow: 0 4px 0 var(--success-700); border-radius: 999px; padding: .4rem 1rem; }
        .jk-next small { display: block; color: var(--gray-500); font-size: .85rem; }
        .jk-next strong { font-size: 1.05rem; }
        .jk-party { text-align: center; font-size: 1.15rem; font-weight: 800; }

        /* Motion: the hello card hops in, the road draws itself from the top, the stops pop up one after
           another along it (stars spin in), the child's marker drops in and keeps breathing, the treasure
           wiggles now and then, the star count counts up, and new stars get confetti (shared motion script). */
        @media (prefers-reduced-motion: no-preference) {
            .jk-hello { animation: jk-hop .6s var(--lms-spring) backwards; }
            .jk-starbox span { display: inline-block; animation: jk-twinkle 3.2s ease-in-out 1.4s infinite; }
            .jk-side > :not(.jk-hello) { animation: lms-rise .5s var(--lms-ease) .45s backwards; }
            .jk-island { animation: lms-rise .5s var(--lms-ease) .1s backwards; }
            .jk-track svg { animation: jk-reveal 1.4s var(--lms-ease) .3s backwards; }
            .jk-stop > .jk-dot, .jk-stop > .jk-name { animation: jk-pop .55s var(--lms-spring) calc(.35s + var(--k) * .15s) backwards; }
            .jk-stop[data-state="done"] > .jk-dot { animation-name: jk-star; }
            .jk-stop[data-state="here"] > .jk-dot { animation: jk-pop .6s var(--lms-spring) calc(.35s + var(--k) * .15s) backwards, jk-pulse 2.2s ease-in-out calc(1.1s + var(--k) * .15s) infinite; }
            .jk-bubble { animation: jk-drop .5s var(--lms-spring) calc(.7s + var(--k) * .15s) backwards, jk-bounce 1.4s ease-in-out calc(1.2s + var(--k) * .15s) infinite; }
            .jk-goal > .jk-dot { animation: jk-pop .6s var(--lms-spring) calc(.35s + var(--k) * .15s) backwards, jk-wiggle 4.5s ease-in-out calc(1.8s + var(--k) * .15s) infinite; }
            .jk-left, .jk-course .jk-chips, .jk-more { animation: lms-fade .5s ease 1s backwards; }
            .jk-go { animation: jk-nudge 2.8s ease-in-out 2.2s infinite; }
        }
        @keyframes jk-hop { from { opacity: 0; transform: translateY(-16px) scale(.95); } }
        @keyframes jk-pop { from { opacity: 0; transform: scale(.3); } }
        @keyframes jk-star { from { opacity: 0; transform: scale(.2) rotate(-150deg); } }
        @keyframes jk-drop { from { opacity: 0; transform: translateY(-14px) scale(.6); } }
        @keyframes jk-reveal { from { clip-path: inset(0 0 100% 0); } to { clip-path: inset(0); } }
        @keyframes jk-bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        @keyframes jk-pulse { 0%, 100% { box-shadow: 0 5px 0 var(--primary-700), 0 0 0 .3rem color-mix(in oklab, var(--primary-400) 35%, transparent); } 50% { box-shadow: 0 5px 0 var(--primary-700), 0 0 0 .7rem color-mix(in oklab, var(--primary-400) 15%, transparent); } }
        @keyframes jk-wiggle { 0%, 82%, 100% { transform: rotate(0); } 86% { transform: rotate(-12deg) scale(1.08); } 90% { transform: rotate(10deg) scale(1.08); } 94% { transform: rotate(-6deg); } }
        @keyframes jk-twinkle { 0%, 70%, 100% { transform: scale(1) rotate(0); } 80% { transform: scale(1.35) rotate(18deg); } 90% { transform: scale(.95) rotate(-8deg); } }
        @keyframes jk-nudge { 0%, 75%, 100% { transform: scale(1); } 82% { transform: scale(1.08); } 90% { transform: scale(.98); } }
        .dark .jk-card { background: var(--gray-900); border-color: var(--gray-700); box-shadow: 0 5px 0 var(--gray-800); }
        .dark .jk-hello { background: linear-gradient(135deg, var(--primary-600), var(--primary-800)); }
        .dark .jk-island { background: linear-gradient(180deg, color-mix(in oklab, var(--info-900) 60%, var(--gray-900)), color-mix(in oklab, var(--success-900) 50%, var(--gray-900))); border-color: var(--gray-700); }
        .dark .jk-name { color: var(--gray-200); background: color-mix(in oklab, var(--gray-900) 70%, transparent); }
        .dark .jk-left, .dark .jk-more { color: var(--gray-200); }
        .dark .jk-island-head span { color: var(--info-300); }
    </style>

    <aside class="jk-side">
        <div class="jk-card jk-hello">
            <div class="jk-starbox" title="{{ __($j.'stars_label') }}"
                 data-celebrate="stars:{{ $learner?->id }}" data-celebrate-value="{{ $journey['stars'] ?? 0 }}" data-celebrate-text="{{ __($j.'cheer') }}">
                <span>⭐</span><b data-count="{{ $journey['stars'] ?? 0 }}" data-count-key="stars:{{ $learner?->id }}">{{ $journey['stars'] ?? 0 }}</b>
            </div>
            <div>
                <strong>{{ trans_choice($j.'stars', $journey['stars'] ?? 0, ['n' => $journey['stars'] ?? 0]) }} ⭐</strong>
                <small>{{ __($j.'hello', ['name' => $first]) }}</small>
            </div>
        </div>
        @if (($journey['streak'] ?? 0) > 0)
            <div class="jk-chips"><span class="jk-chip">{{ trans_choice($j.'streak', $journey['streak'], ['n' => $journey['streak']]) }}</span></div>
        @endif
        @foreach ($courses as $course)
            @if ($course['next'] !== [])
                <div class="jk-card jk-next">
                    <small>{{ __($j.'next') }} · {{ $course['subject'] }}</small>
                    <strong>{{ $course['next'][0]['title'] }}</strong>
                </div>
            @endif
        @endforeach
        <a class="jk-card jk-homework lms-lift" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}">
            <strong>{{ trans_choice($j.'homework', $homework, ['n' => $homework]) }}</strong>
            @if ($homework > 0) <span class="jk-go">{{ __($j.'homework_go') }}</span> @endif
        </a>
    </aside>

    <div class="jk-course-list">
        @forelse ($courses as $course)
            <section class="jk-course" wire:key="jk-{{ $course['id'] }}">
                <div class="jk-course-head">
                    <h3>{{ $course['subject'] }}</h3>
                    <small>{{ __('learning::journey.with', ['teacher' => $course['teacher']]) }}</small>
                </div>
                @if ($course['level'] === null)
                    <div class="jk-card jk-soft">{{ __($j.'no_program') }}</div>
                @else
                    @php
                        $worlds = $course['worlds'];
                        $shown = $full ? $worlds : array_values(array_filter($worlds, fn ($w) => $w['index'] === $course['level']['index']));
                        $before = $full ? [] : array_values(array_filter($worlds, fn ($w) => $w['index'] < $course['level']['index']));
                        $after = array_values(array_filter($worlds, fn ($w) => $w['index'] > $course['level']['index']));
                    @endphp
                    @if ($before !== [])
                        <div class="jk-chips">
                            @foreach ($before as $w)
                                <span class="jk-chip" data-tone="done">🏝️ {{ __($j.'island_done', ['n' => $w['index']]) }} · ⭐ {{ $w['done'] }}</span>
                            @endforeach
                        </div>
                    @endif
                    @foreach ($shown as $world)
                        @php
                            $stops = $world['lessons'];
                            $hereAt = collect($stops)->search(fn ($l) => $l['state'] === 'here');
                            $from = (! $full && count($stops) > 8) ? max(0, min(($hereAt === false ? 0 : $hereAt) - 2, count($stops) - 8)) : 0;
                            $window = array_slice($stops, $from, $full ? null : 8);
                            $hidden = count($stops) - $from - count($window);
                            $points = [];
                            foreach ([...$window, null] as $k => $_) {
                                $points[] = [$zigzag[($k + $from) % 4], $k * 10 + 5];
                            }
                            $walked = collect($window)->search(fn ($l) => $l['state'] === 'here' || $l['state'] === 'todo');
                            $walked = $walked === false ? count($window) : $walked;
                            $islandDone = $world['done'] === count($stops) && $stops !== [];
                        @endphp
                        <div class="jk-island" wire:key="jk-{{ $course['id'] }}-{{ $world['index'] }}">
                            <div class="jk-island-head">
                                <div>
                                    <span>🏝️ {{ __($j.'island', ['n' => $world['index'], 'of' => count($worlds)]) }}</span>
                                    <strong>{{ $world['title'] }}</strong>
                                </div>
                                <span class="jk-chip">⭐ {{ __($j.'of', ['n' => $world['done'], 'of' => count($stops)]) }}</span>
                            </div>
                            @if ($from > 0)
                                <p class="jk-more" style="margin: .6rem 0 0">{{ trans_choice($j.'before', $from, ['n' => $from]) }}</p>
                            @endif
                            <div class="jk-map">
                                <div class="jk-track" style="--rows: {{ count($window) + 1 }}">
                                    <svg viewBox="0 0 100 {{ (count($window) + 1) * 10 }}" preserveAspectRatio="none" aria-hidden="true">
                                        <path class="jk-road" d="{{ $curve($points) }}" />
                                        @if ($walked > 0)
                                            <path class="jk-walked" d="{{ $curve(array_slice($points, 0, $walked + 1)) }}" />
                                        @endif
                                    </svg>
                                    @foreach ($window as $k => $lesson)
                                        <div class="jk-stop" data-state="{{ $lesson['state'] }}" style="--x: {{ $points[$k][0] }}%; --k: {{ $k }}"
                                             aria-label="{{ $lesson['title'] }}" @if ($lesson['state'] === 'here') aria-current="step" @endif>
                                            @if ($lesson['state'] === 'here')
                                                <span class="jk-bubble">{{ __($j.'here') }}</span>
                                            @endif
                                            <span class="jk-dot">{{ match ($lesson['state']) { 'done' => '★', 'review' => '↻', 'here' => mb_substr($first, 0, 1), default => $from + $k + 1 } }}</span>
                                            <span class="jk-name">{{ $lesson['title'] }}@if ($lesson['state'] === 'review') · {{ __($j.'review') }}@endif</span>
                                        </div>
                                    @endforeach
                                    <div class="jk-stop jk-goal" data-state="{{ $islandDone ? 'done' : 'todo' }}" style="--x: {{ $points[count($window)][0] }}%; --k: {{ count($window) }}">
                                        <span class="jk-dot">{{ $islandDone ? '🏆' : '🎁' }}</span>
                                        @if ($hidden > 0)
                                            <span class="jk-name">{{ trans_choice($j.'after', $hidden, ['n' => $hidden]) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if (! $full || $world['state'] === 'here')
                                <p class="jk-left">{{ $course['map']['finished'] ? __($j.'finished') : trans_choice($j.'left', count($stops) - $world['done'], ['n' => count($stops) - $world['done']]) }}</p>
                            @endif
                        </div>
                    @endforeach
                    @if (! $full && ! $course['map']['finished'])
                        <div class="jk-chips">
                            @if ($after !== [])
                                <span class="jk-chip" data-tone="soon">🔒 {{ __($j.'next_island', ['title' => $after[0]['title']]) }}</span>
                            @else
                                <span class="jk-chip" data-tone="soon">{{ __($j.'last_island') }}</span>
                            @endif
                        </div>
                    @endif
                @endif
            </section>
        @empty
            <div class="jk-card jk-soft">{{ __('learning::journey.none') }}</div>
        @endforelse
    </div>
</div>
