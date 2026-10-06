{{--
    Middle school: a game. The player card with the rank, the points to the
    next one, the streak and the stars; each level is a world and its lessons
    are stages on a path, with "you are here"; badges earned and still locked.
    The home shows the world they are in; the worlds page shows them all.
--}}
@php
    $j = 'learning::journey.middle.';
    $courses = $journey['courses'] ?? [];
    $first = strtok((string) ($learner?->name ?? ''), ' ');
    $rank = $journey['rank'] ?? ['key' => 'beginner', 'points' => 0, 'next' => 300, 'next_key' => 'explorer', 'progress' => 0];
    $rankIcons = ['beginner' => '🌱', 'explorer' => '🧭', 'star' => '⭐', 'hero' => '🦸', 'legend' => '👑'];
    $badgeIcons = ['first_star' => '⭐', 'five_stars' => '🌟', 'streak_3' => '🔥', 'homework_3' => '⏰', 'halfway' => '⛰️', 'level_done' => '🏆'];
    $badges = $journey['badges'] ?? [];
    $earned = count(array_filter($badges, fn ($b) => $b['earned']));
@endphp
<div class="jm">
    <style>
        .jm { display: grid; gap: 1rem; }
        .jm-main, .jm-side { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
        @media (min-width: 1024px) {
            .jm { grid-template-columns: minmax(0, 1fr) 20rem; align-items: start; }
            .jm-hud { grid-column: 1 / -1; }
        }
        .jm-card { background: white; border: 1px solid var(--gray-200); border-radius: 1.25rem; padding: 1rem 1.1rem; box-shadow: 0 1px 2px rgb(0 0 0 / .04); }
        .jm-hud { position: relative; overflow: hidden; color: white; border-radius: 1.5rem; padding: 1.1rem 1.2rem 1.2rem;
            background: radial-gradient(circle at 12% 0%, color-mix(in oklab, var(--warning-400) 35%, transparent), transparent 45%),
                        radial-gradient(circle at 95% 110%, color-mix(in oklab, var(--info-400) 35%, transparent), transparent 50%),
                        linear-gradient(135deg, var(--primary-600), var(--primary-900)); }
        .jm-player { display: flex; align-items: center; gap: .9rem; }
        .jm-avatar { flex: none; display: grid; place-items: center; width: 3.6rem; height: 3.6rem; font-size: 1.5rem; font-weight: 800; color: var(--primary-800);
            background: linear-gradient(160deg, white, var(--warning-100)); clip-path: polygon(25% 4%, 75% 4%, 100% 50%, 75% 96%, 25% 96%, 0 50%); }
        .jm-player small { display: block; opacity: .85; font-size: .85rem; }
        .jm-player strong { font-size: 1.35rem; font-weight: 800; letter-spacing: -.01em; }
        .jm-points { margin-inline-start: auto; text-align: center; background: rgb(255 255 255 / .14); border: 1px solid rgb(255 255 255 / .25); border-radius: 1rem; padding: .35rem .8rem; }
        .jm-points b { display: block; font-size: 1.45rem; line-height: 1.1; font-variant-numeric: tabular-nums; }
        .jm-points small { font-size: .75rem; opacity: .85; }
        .jm-xp { height: .8rem; margin-top: .9rem; border-radius: 999px; background: rgb(255 255 255 / .2); overflow: hidden; }
        .jm-xp i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--warning-300), var(--warning-500)); box-shadow: 0 0 12px color-mix(in oklab, var(--warning-300) 70%, transparent); }
        .jm-xp-note { display: block; margin-top: .35rem; font-size: .85rem; opacity: .9; }
        .jm-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; margin-top: .9rem; }
        .jm-stat { background: rgb(255 255 255 / .12); border-radius: .9rem; padding: .45rem .6rem; text-align: center; }
        .jm-stat b { display: block; font-size: 1.3rem; font-variant-numeric: tabular-nums; }
        .jm-stat small { font-size: .75rem; opacity: .9; }
        .jm-course-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
        .jm-course-head h3 { margin: 0; font-size: 1.15rem; font-weight: 800; }
        .jm-course-head small { color: var(--gray-500); font-size: .85rem; }
        .jm-ring { --p: 0; flex: none; display: grid; place-items: center; width: 3.6rem; height: 3.6rem; border-radius: 50%; background: conic-gradient(var(--primary-500) calc(var(--p) * 1%), var(--gray-200) 0); }
        .jm-ring span { display: grid; place-items: center; width: 2.8rem; height: 2.8rem; border-radius: 50%; background: white; font-weight: 800; font-size: .85rem; font-variant-numeric: tabular-nums; }
        .jm-worlds { display: flex; gap: .4rem; flex-wrap: wrap; margin-top: .8rem; }
        .jm-wpill { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; border: 1px solid var(--gray-200); color: var(--gray-500); background: var(--gray-50); max-width: 100%; }
        .jm-wpill[data-state="done"] { background: var(--success-50); border-color: var(--success-200); color: var(--success-700); }
        .jm-wpill[data-state="here"] { background: var(--primary-50); border-color: var(--primary-300); color: var(--primary-700); }
        .jm-world { margin-top: 1rem; }
        .jm-world-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; flex-wrap: wrap; }
        .jm-world-head span { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--primary-600); }
        .jm-world-head strong { display: block; font-size: 1.05rem; }
        .jm-world-head small { color: var(--gray-500); font-size: .82rem; }
        .jm-path { display: flex; overflow-x: auto; scroll-snap-type: x proximity; margin: .6rem -.25rem 0; padding: .9rem .25rem .4rem; list-style: none; scrollbar-width: thin; }
        .jm-path li { position: relative; flex: none; width: 6.6rem; display: flex; flex-direction: column; align-items: center; gap: .35rem; text-align: center; scroll-snap-align: center; }
        .jm-path li:not(:last-child)::after { content: ''; position: absolute; top: 1.45rem; inset-inline-start: calc(50% + 1.5rem); width: calc(100% - 3rem); height: 4px; border-radius: 4px; background: var(--gray-200); }
        .jm-path li[data-state="done"]:not(:last-child)::after { background: var(--primary-400); }
        .jm-node { display: grid; place-items: center; width: 2.9rem; height: 2.9rem; border-radius: .9rem; font-weight: 800; font-size: 1.05rem; background: var(--gray-100); color: var(--gray-400); border: 2px solid var(--gray-200); transform: rotate(45deg); }
        .jm-node > span { transform: rotate(-45deg); }
        .jm-path li[data-state="done"] .jm-node { background: var(--primary-500); border-color: var(--primary-600); color: white; }
        .jm-path li[data-state="review"] .jm-node { background: var(--warning-400); border-color: var(--warning-500); color: white; }
        .jm-path li[data-state="here"] .jm-node { background: white; border: 3px solid var(--primary-500); color: var(--primary-600); box-shadow: 0 0 0 .35rem color-mix(in oklab, var(--primary-400) 25%, transparent), 0 0 18px color-mix(in oklab, var(--primary-500) 45%, transparent); animation: jm-glow 2s ease-in-out infinite; }
        .jm-label { font-size: .78rem; line-height: 1.3; color: var(--gray-600); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .jm-path li[data-state="here"] .jm-label { color: var(--gray-900); font-weight: 700; }
        .jm-tag { font-size: .68rem; font-weight: 800; padding: .05rem .45rem; border-radius: 999px; background: var(--primary-600); color: white; white-space: nowrap; }
        .jm-tag[data-tone="review"] { background: var(--warning-500); }
        .jm-badges { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; margin-top: .7rem; }
        .jm-badge { display: flex; flex-direction: column; align-items: center; gap: .25rem; text-align: center; padding: .55rem .3rem; border-radius: 1rem; background: var(--gray-50); }
        .jm-badge i { font-style: normal; display: grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: 50%; font-size: 1.35rem; background: linear-gradient(160deg, var(--warning-100), var(--warning-300)); box-shadow: inset 0 -3px 0 color-mix(in oklab, var(--warning-600) 35%, transparent); }
        .jm-badge strong { font-size: .78rem; line-height: 1.2; }
        .jm-badge small { font-size: .68rem; color: var(--gray-500); line-height: 1.25; }
        .jm-badge[data-earned="0"] i { filter: grayscale(1); opacity: .45; background: var(--gray-200); box-shadow: none; }
        .jm-badge[data-earned="0"] strong { color: var(--gray-500); }
        .jm-section-head { display: flex; justify-content: space-between; align-items: baseline; }
        .jm-section-head h3 { margin: 0; font-size: 1.05rem; font-weight: 800; }
        .jm-section-head small { color: var(--gray-500); font-size: .85rem; }
        .jm-homework { display: flex; align-items: center; justify-content: space-between; gap: .75rem; text-decoration: none; color: inherit; font-weight: 700; }
        .jm-homework span { flex: none; font-size: .85rem; color: white; background: var(--primary-600); border-radius: .75rem; padding: .4rem .8rem; }
        .jm-how { color: var(--gray-500); font-size: .78rem; line-height: 1.6; }
        @keyframes jm-glow { 0%, 100% { box-shadow: 0 0 0 .3rem color-mix(in oklab, var(--primary-400) 25%, transparent), 0 0 12px color-mix(in oklab, var(--primary-500) 35%, transparent); } 50% { box-shadow: 0 0 0 .5rem color-mix(in oklab, var(--primary-400) 15%, transparent), 0 0 22px color-mix(in oklab, var(--primary-500) 55%, transparent); } }
        @media (prefers-reduced-motion: reduce) { .jm-path li[data-state="here"] .jm-node { animation: none; } }
        .dark .jm-card { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .jm-ring span { background: var(--gray-900); }
        .dark .jm-badge, .dark .jm-wpill { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .jm-node { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .jm-path li[data-state="here"] .jm-node { background: var(--gray-900); }
        .dark .jm-label { color: var(--gray-300); }
        .dark .jm-path li[data-state="here"] .jm-label { color: white; }
    </style>

    <section class="jm-hud">
        <div class="jm-player">
            <div class="jm-avatar">{{ mb_substr($first, 0, 1) }}</div>
            <div>
                <small>{{ __($j.'rank') }}</small>
                <strong>{{ $rankIcons[$rank['key']] }} {{ __('learning::journey.ranks.'.$rank['key']) }}</strong>
            </div>
            <div class="jm-points"><b>{{ number_format($rank['points']) }}</b><small>{{ __($j.'points_label') }}</small></div>
        </div>
        <div class="jm-xp" role="progressbar" aria-valuenow="{{ $rank['progress'] }}" aria-valuemin="0" aria-valuemax="100"><i style="width: {{ max(3, $rank['progress']) }}%"></i></div>
        <small class="jm-xp-note">{{ $rank['next'] === null ? __($j.'top_rank') : __($j.'to_next', ['n' => number_format($rank['next'] - $rank['points']), 'rank' => __('learning::journey.ranks.'.$rank['next_key'])]) }}</small>
        <div class="jm-stats">
            <div class="jm-stat"><b>🔥 {{ $journey['streak'] ?? 0 }}</b><small>{{ __($j.'streak') }}</small></div>
            <div class="jm-stat"><b>⭐ {{ $journey['stars'] ?? 0 }}</b><small>{{ __($j.'stars') }}</small></div>
            <div class="jm-stat"><b>✅ {{ $journey['on_time'] ?? 0 }}</b><small>{{ __($j.'on_time') }}</small></div>
        </div>
    </section>

    <div class="jm-main">
        @forelse ($courses as $course)
            <section class="jm-card" wire:key="jm-{{ $course['id'] }}">
                <div class="jm-course-head">
                    <div>
                        <h3>{{ $course['subject'] }}</h3>
                        <small>{{ __('learning::journey.with', ['teacher' => $course['teacher']]) }}@if ($course['map']) · {{ $course['map']['program']['name'] }}@endif</small>
                    </div>
                    @if ($course['map'])
                        <div class="jm-ring" style="--p: {{ $course['map']['percent'] }}"><span>{{ $course['map']['percent'] }}%</span></div>
                    @endif
                </div>
                @if ($course['level'] === null)
                    <p class="jm-how" style="margin: .6rem 0 0">{{ __('learning::journey.no_program') }}</p>
                @else
                    <div class="jm-worlds">
                        @foreach ($course['worlds'] as $w)
                            <span class="jm-wpill" data-state="{{ $w['state'] }}" title="{{ $w['title'] }}">
                                {{ match ($w['state']) { 'done' => '✓', 'here' => '▶', default => '🔒' } }} {{ __($j.'world_short', ['n' => $w['index']]) }}
                            </span>
                        @endforeach
                    </div>
                    @foreach ($full ? $course['worlds'] : array_filter($course['worlds'], fn ($w) => $w['index'] === $course['level']['index']) as $world)
                        <div class="jm-world" wire:key="jm-{{ $course['id'] }}-{{ $world['index'] }}">
                            <div class="jm-world-head">
                                <div>
                                    <span>{{ __($j.'world', ['n' => $world['index'], 'of' => count($course['worlds'])]) }}</span>
                                    <strong>{{ $world['title'] }}</strong>
                                </div>
                                <small>{{ $world['state'] === 'ahead' ? '🔒 '.__($j.'locked') : __($j.'stages', ['done' => $world['done'], 'total' => count($world['lessons'])]) }}</small>
                            </div>
                            <ol class="jm-path" x-data x-init="$nextTick(() => { const n = $el.querySelector('[aria-current]'); if (n) { const r = n.getBoundingClientRect(), c = $el.getBoundingClientRect(); $el.scrollBy({ left: (r.left + r.width / 2) - (c.left + c.width / 2) }) } })">
                                @foreach ($world['lessons'] as $k => $lesson)
                                    @php $state = $world['state'] === 'ahead' ? 'locked' : $lesson['state']; @endphp
                                    <li data-state="{{ $state }}" @if ($state === 'here') aria-current="step" @endif>
                                        <span class="jm-node"><span>{{ match ($state) { 'done' => '✓', 'review' => '↻', 'locked' => '🔒', default => $k + 1 } }}</span></span>
                                        <span class="jm-label">{{ $lesson['title'] }}</span>
                                        @if ($state === 'here')
                                            <span class="jm-tag">{{ __($j.'here') }}</span>
                                        @elseif ($state === 'review')
                                            <span class="jm-tag" data-tone="review">{{ __($j.'review') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                    @if ($course['map']['finished'])
                        <p class="jm-section-head" style="margin: .8rem 0 0"><strong>{{ __($j.'finished') }}</strong></p>
                    @endif
                @endif
            </section>
        @empty
            <div class="jm-card jm-how">{{ __('learning::journey.none') }}</div>
        @endforelse
    </div>

    <aside class="jm-side">
        <section class="jm-card">
            <div class="jm-section-head">
                <h3>{{ __($j.'badges') }}</h3>
                <small>{{ __($j.'badges_count', ['n' => $earned, 'of' => count($badges)]) }}</small>
            </div>
            <div class="jm-badges">
                @foreach ($badges as $badge)
                    @php [$name, $hint] = __('learning::journey.badges.'.$badge['key']); @endphp
                    <div class="jm-badge" data-earned="{{ $badge['earned'] ? 1 : 0 }}" title="{{ $hint }}">
                        <i aria-hidden="true">{{ $badgeIcons[$badge['key']] }}</i>
                        <strong>{{ $name }}</strong>
                        <small>{{ $hint }}</small>
                    </div>
                @endforeach
            </div>
        </section>
        <a class="jm-card jm-homework" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}">
            {{ trans_choice($j.'homework', $homework, ['n' => $homework]) }}
            <span>{{ __($j.'homework_go') }}</span>
        </a>
        <p class="jm-how">{{ __($j.'how', \Modules\Learning\Queries\JourneyQuery::POINTS) }}</p>
    </aside>
</div>
