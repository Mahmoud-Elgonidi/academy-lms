@php
    /*
     * The calm journey (secondary school, adult students, and everyone when the academy chose the calm or plain
     * style): the level they are on and when they should finish, the next lesson with the last one's stars, the
     * missions, the levels as an accordion of their lessons (practice in the games on any lesson they may play),
     * stars small and the week's chest. Plain: no stars, chest, egg or games.
     */
    $m = 'learning::map.';
    $stars = (int) $j['stars']['total'];
    $rank = $j['stars']['rank'];
    $islands = $course['islands'] ?? [];
    $here = collect($islands)->firstWhere('state', 'here');
    $hereArt = $here['art']['key'] ?? null;
    $next = collect($j['missions'])->first(fn ($mm) => $mm['kind'] === 'lesson' && ($course === null || $mm['enrollment_id'] === $course['enrollment_id']))
        ?? collect($j['missions'])->firstWhere('kind', 'lesson');
    $album = $this->albumUrl();
@endphp
<div class="jm-cols" x-data="{ station: null }" wire:key="jcalm-{{ $course['enrollment_id'] ?? 'none' }}">
    <div class="jm-side">
        <div class="jm-row">
            <h2 class="jm-h" style="font-size: 1.2rem; margin: 0">{{ __($m.'title.self') }}</h2>
            <span style="display: flex; gap: .4rem; align-items: center; flex-wrap: wrap">
                @unless ($plain)
                    <span class="jm-pill" data-tone="star" data-celebrate="journey-stars-{{ $this->learner()?->id }}" data-celebrate-value="{{ $stars }}">⭐ <span data-count="{{ $stars }}">{{ $stars }}</span> · {{ __('learning::journey.ranks.'.$rank['key']) }}</span>
                @endunless
                @if ($album)
                    <a class="jm-pill" href="{{ $album }}" wire:navigate style="text-decoration: none">🐣 {{ __($m.'album') }}</a>
                @endif
            </span>
        </div>

        @include('learning::filament.family.map.parts.greetings')

        @if (count($j['courses']) > 1)
            <div class="jm-chips" role="group" aria-label="{{ __($m.'courses') }}">
                @foreach ($j['courses'] as $c)
                    <button type="button" aria-pressed="{{ $c['enrollment_id'] === ($course['enrollment_id'] ?? null) ? 'true' : 'false' }}" wire:click="chooseCourse('{{ $c['enrollment_id'] }}')" wire:key="cc-{{ $c['enrollment_id'] }}">{{ $c['subject'] }}</button>
                @endforeach
            </div>
        @endif

        @if ($course)
            <section class="jm-card" aria-labelledby="jm-level">
                <div class="jm-row" style="align-items: flex-start">
                    <div>
                        <small class="jm-muted">{{ $course['subject'] }}@if ($course['program']) · {{ $course['program'] }}@endif</small>
                        @if (! $course['has_curriculum'])
                            <h3 id="jm-level">{{ __($m.'no_program') }}</h3>
                        @elseif ($course['finished'])
                            <h3 id="jm-level">{{ __($m.'finished') }}</h3>
                        @elseif ($here)
                            <h3 id="jm-level">{{ __($m.'calm.level', ['n' => $here['index'], 'of' => count($islands)]) }} · {{ $here['title'] }}</h3>
                        @endif
                    </div>
                    @if ($course['percent'] !== null)
                        <span class="jm-big" style="color: var(--primary-700)">{{ $course['percent'] }}%</span>
                    @endif
                </div>
                @if ($course['percent'] !== null)
                    <span class="jm-bar"><i class="lms-bar-fill" style="width: {{ $course['percent'] }}%"></i></span>
                    <div class="jm-row">
                        @if ($course['current'])<small>{{ __($m.'calm.here', ['lesson' => $course['current']['title']]) }}</small>@endif
                        @if ($here)<small class="jm-muted">{{ __($m.'calm.level_pct', ['pct' => $here['percent']]) }}</small>@endif
                    </div>
                    @unless ($course['finished'])
                        <small class="jm-muted">{{ $course['finish_on'] ? __($m.'calm.finish_on', ['date' => $this->day($course['finish_on'])]) : __($m.'calm.finish_unknown') }}</small>
                    @endunless
                @endif
            </section>
        @endif

        <section class="jm-card" aria-labelledby="jm-next">
            <h3 id="jm-next">{{ __($m.'calm.next_lesson') }}</h3>
            @if ($next)
                <div class="jm-row">
                    <div>
                        <strong>{{ $next['title'] }}</strong>
                        <div class="jm-muted">{{ $this->at($next['starts_at']) }}</div>
                    </div>
                    @if ($join = $this->joinUrl($next))
                        <a class="jm-btn" href="{{ $join }}">{{ __($m.'mission.join') }}</a>
                    @else
                        <small class="jm-muted">{{ __($m.'mission.opens', ['time' => $this->at($next['join']['opens_at'])]) }}</small>
                    @endif
                </div>
            @else
                <p class="jm-muted" style="margin: 0">{{ __($m.'calm.no_lesson') }}</p>
            @endif
            @if ($j['last_lesson'])
                @php $l = $j['last_lesson']; @endphp
                <small>
                    {{ __($m.'last_lesson.title') }}:
                    @unless ($plain){{ str_repeat('⭐', $l['stars']) }} @endunless
                    @if ($l['status'] === 'student_absent'){{ __($m.'lesson.missed') }}@elseif ($l['late_min'] === null){{ __($m.'lesson.marked') }}@else{{ $l['on_time'] ? __($m.'lesson.on_time') : trans_choice($m.'lesson.late', $l['late_min'], ['n' => $l['late_min']]) }}@if ($l['stayed']) · {{ __($m.'lesson.stayed') }}@endif @endif
                </small>
            @endif
        </section>

        @include('learning::filament.family.map.parts.missions')
    </div>

    <div class="jm-side">
        @if ($islands !== [])
            <section class="jm-card" aria-labelledby="jm-levels">
                <h3 id="jm-levels">{{ __($m.'calm.levels') }}</h3>
                @foreach ($islands as $i => $island)
                    <details class="jm-level" data-state="{{ $island['state'] }}" @if ($island['state'] === 'here') open @endif wire:key="cl-{{ $island['id'] }}">
                        <summary class="jm-row" style="cursor: pointer; list-style: revert">
                            <span style="font-weight: 700">{{ __($m.'calm.level', ['n' => $island['index'], 'of' => count($islands)]) }} · {{ $island['title'] }}</span>
                            <span class="jm-pill" data-tone="{{ ['done' => 'done', 'here' => 'here', 'ahead' => 'open'][$island['state']] }}">{{ __($m.'island.lessons', ['done' => $island['done'], 'total' => $island['lessons']]) }}</span>
                        </summary>
                        <ol class="jm-path" style="margin-top: .4rem">
                            @foreach ($island['stations'] as $k => $st)
                                @php $state = $st['due_review'] && $st['state'] === 'done' ? 'review' : $st['state']; @endphp
                                <li wire:key="cn-{{ $st['id'] }}">
                                    <button type="button" class="jm-node" data-state="{{ $state }}" x-on:click="station = @js($st['id'])"
                                            aria-label="{{ __($m.'station.aria', ['title' => $st['title'], 'state' => __($m.'station.states.'.$st['state'])]) }}">
                                        <span>{{ ['done' => '✓', 'review' => '↺', 'here' => '•', 'todo' => $k + 1][$state] }}</span>
                                        <span>
                                            <strong>{{ $st['title'] }}</strong>
                                            <small>{{ $st['due_review'] ? __($m.'station.due_review') : __($m.'station.states.'.$st['state']) }}@if ($st['playable'] && $st['games'] !== []) · {{ __($m.'station.practice') }} 🎮@endif</small>
                                        </span>
                                        @if (collect($st['missions'])->contains(fn ($key) => str_starts_with($key, 'homework:')))<span class="jm-flag" aria-hidden="true">📝</span>@endif
                                    </button>
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endforeach
            </section>
        @endif

        @include('learning::filament.family.map.parts.chest', ['hereArt' => $hereArt])

        <div class="jm-links">
            <a href="{{ \Modules\Learning\Filament\Family\FamilyProgress::getUrl() }}" wire:navigate>{{ __($m.'calm.roadmap') }}</a>
            <a href="{{ \Modules\Learning\Filament\Family\FamilyReports::getUrl() }}" wire:navigate>{{ __($m.'calm.reports') }}</a>
        </div>
    </div>

    @foreach ($islands as $island)
        @foreach ($island['stations'] as $st)
            @include('learning::filament.family.map.parts.station', ['st' => $st, 'race' => false])
        @endforeach
    @endforeach
</div>
