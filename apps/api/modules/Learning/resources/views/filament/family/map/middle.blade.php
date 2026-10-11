@php
    /*
     * Middle school's journey: the same islands as levels, each a path of station nodes to go up; stars as a bar
     * to the next rank, the lesson streak, the missions, the chest and the egg; a station opens its race against
     * yourself and its games.
     */
    $m = 'learning::map.';
    $stars = (int) $j['stars']['total'];
    $rank = $j['stars']['rank'];
    $islands = $course['islands'] ?? [];
    $hereArt = collect($islands)->firstWhere('state', 'here')['art']['key'] ?? null;
    $album = $this->albumUrl();
@endphp
<div class="jm-cols" x-data="{ station: null }" wire:key="jmid-{{ $course['enrollment_id'] ?? 'none' }}">
    <div class="jm-side" style="gap: 1rem">
        <section class="jm-card" aria-labelledby="jm-title">
            <div class="jm-hello">
                @if ($games && $games['buddy'])
                    <a class="jm-buddy" href="{{ $album }}" wire:navigate aria-label="{{ __($m.'album') }}">@include('shared::games.creature', ['look' => $games['buddy']['look'], 'size' => 46, 'motion' => true])</a>
                @endif
                <div style="flex: 1; min-width: 0">
                    <h2 id="jm-title" style="font-size: 1.25rem; font-weight: 800">{{ __($m.'title.self') }}</h2>
                    <small class="jm-muted">{{ trans_choice($m.'streak', $j['streak'], ['count' => $j['streak']]) }}</small>
                </div>
                @if ($album)
                    <a class="jm-btn" data-kind="ghost" href="{{ $album }}" wire:navigate>{{ __($m.'album') }}</a>
                @endif
            </div>
            @unless ($plain)
                <div class="jm-xp" data-celebrate="journey-stars-{{ $this->learner()?->id }}" data-celebrate-value="{{ $stars }}">
                    <div class="jm-row">
                        <span><span class="jm-big" data-count="{{ $stars }}">{{ $stars }}</span> ⭐ <span class="jm-muted">{{ __($m.'stars_label') }}</span></span>
                        <span class="jm-pill" data-tone="star">{{ __('learning::journey.ranks.'.$rank['key']) }}</span>
                    </div>
                    <span class="jm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $rank['progress'] }}"><i class="lms-bar-fill" style="width: {{ $rank['progress'] }}%"></i></span>
                    <small class="jm-muted">{{ $rank['next'] === null ? __($m.'top_rank') : __($m.'to_next', ['n' => $rank['next'] - $stars, 'rank' => __('learning::journey.ranks.'.$rank['next_key'])]) }}</small>
                </div>
            @endunless
        </section>

        @include('learning::filament.family.map.parts.greetings')

        @if (count($j['courses']) > 1)
            <div class="jm-chips" role="group" aria-label="{{ __($m.'courses') }}">
                @foreach ($j['courses'] as $c)
                    <button type="button" aria-pressed="{{ $c['enrollment_id'] === ($course['enrollment_id'] ?? null) ? 'true' : 'false' }}" wire:click="chooseCourse('{{ $c['enrollment_id'] }}')" wire:key="mc-{{ $c['enrollment_id'] }}">{{ $c['subject'] }}</button>
                @endforeach
            </div>
        @endif

        @if ($course && ! $course['has_curriculum'])
            <div class="jm-empty">{{ __($m.'no_program') }}</div>
        @endif

        <div class="lms-stagger" style="display: flex; flex-direction: column; gap: .8rem">
            @foreach ($islands as $i => $island)
                <section class="jm-card jm-level" data-state="{{ $island['state'] }}" aria-labelledby="lvl-{{ $island['id'] }}" wire:key="lvl-{{ $island['id'] }}">
                    <div class="jm-level-head">
                        @if ($island['art'])
                            <span class="jm-level-art" aria-hidden="true"><span>@include('shared::games.island', ['key' => $island['art']['key'], 'locked' => false, 'colors' => $island['art']['colors'], 'emoji' => $island['art']['emoji']])</span></span>
                        @endif
                        <div style="flex: 1; min-width: 0">
                            <small class="jm-muted">{{ __($m.'island.n_of', ['n' => $island['index'], 'of' => count($islands)]) }}</small>
                            <h3 id="lvl-{{ $island['id'] }}">{{ $island['title'] }}</h3>
                        </div>
                        <span class="jm-pill" data-tone="{{ ['done' => 'done', 'here' => 'here', 'ahead' => 'open'][$island['state']] }}">
                            {{ $island['state'] === 'done' ? '🏆 '.__($m.'island.done') : ($island['state'] === 'here' ? '📍 '.__($m.'island.here') : '🔒') }}
                        </span>
                    </div>
                    <span class="jm-bar"><i class="lms-bar-fill" style="width: {{ (int) round(100 * $island['done'] / max(1, $island['lessons'])) }}%"></i></span>
                    @if ($island['state'] === 'ahead')
                        <small class="jm-muted">🔒 {{ __($m.'island.ahead', ['title' => $islands[$i - 1]['title'] ?? $island['title']]) }}</small>
                    @else
                        <ol class="jm-path">
                            @foreach ($island['stations'] as $k => $st)
                                @php
                                    $state = $st['due_review'] && $st['state'] === 'done' ? 'review' : $st['state'];
                                    $flag = collect($st['missions'])->contains(fn ($key) => str_starts_with($key, 'homework:'));
                                @endphp
                                <li wire:key="nd-{{ $st['id'] }}">
                                    <button type="button" class="jm-node" data-state="{{ $state }}" x-on:click="station = @js($st['id'])"
                                            aria-label="{{ __($m.'station.aria', ['title' => $st['title'], 'state' => __($m.'station.states.'.$st['state'])]) }}">
                                        <span>{{ ['done' => '✓', 'review' => '↺', 'here' => '📍', 'todo' => $k + 1][$state] }}</span>
                                        <span>
                                            <strong>{{ $st['title'] }}</strong>
                                            <small>
                                                {{ $st['due_review'] ? __($m.'station.due_review') : __($m.'station.states.'.$st['state']) }}
                                                @if ($st['race']) · 🏁 {{ $st['race']['best_seconds'] !== null ? __($m.'station.best', ['s' => $st['race']['best_seconds']]) : trans_choice($m.'station.rounds', $st['race']['rounds'], ['count' => $st['race']['rounds']]) }}@endif
                                                @if ($st['playable'] && $st['games'] !== []) · 🎮 {{ count($st['games']) }}@endif
                                            </small>
                                        </span>
                                        @if ($flag)<span class="jm-flag" aria-hidden="true">🚩</span>@endif
                                    </button>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endforeach
        </div>
    </div>

    <div class="jm-side">
        @include('learning::filament.family.map.parts.missions')
        @include('learning::filament.family.map.parts.chest', ['hereArt' => $hereArt])
    </div>

    @foreach ($islands as $island)
        @continue($island['state'] === 'ahead')
        @foreach ($island['stations'] as $st)
            @include('learning::filament.family.map.parts.station', ['st' => $st, 'race' => true])
        @endforeach
    @endforeach
</div>
