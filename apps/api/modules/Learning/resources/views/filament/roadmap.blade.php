{{-- The roadmap: levels → units → lessons, ✓ mastered, ● here, ↻ review, ○ to come, 🎮 how practice in the games went, and the lessons' materials under them. Needs $map (RoadmapQuery::for). --}}
@php
    $mat = fn (array $lessons) => array_values(array_filter($lessons, fn ($l) => ($l['materials'] ?? []) !== []));
@endphp
<style>
    .rm-levels { display: flex; flex-direction: column; gap: .5rem; }
    .rm-level { border: 1px solid var(--gray-200); border-radius: var(--radius-lg); }
    .rm-level > summary { list-style: none; cursor: pointer; display: grid; grid-template-columns: minmax(0, 1fr) 120px 2.8rem; gap: .75rem; align-items: center; padding: .55rem .8rem; }
    .rm-level > summary::-webkit-details-marker { display: none; }
    .rm-level[open] > summary { border-bottom: 1px solid var(--gray-100); }
    .rm-level summary strong { font-size: .875rem; }
    .rm-bar { height: .4rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .rm-bar i { display: block; height: 100%; background: var(--primary-500); border-radius: inherit; }
    .rm-pct { font-size: .75rem; color: var(--gray-500); text-align: end; font-variant-numeric: tabular-nums; }
    .rm-body { padding: .5rem .8rem .7rem; display: flex; flex-direction: column; gap: .5rem; }
    .rm-unit small { display: block; color: var(--gray-500); font-size: .75rem; margin-bottom: .3rem; }
    .rm-lessons { display: flex; flex-wrap: wrap; gap: .3rem; }
    .rm-l { display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; padding: .15rem .5rem; border-radius: 999px; border: 1px solid var(--gray-200); color: var(--gray-600); }
    .rm-l b { font-weight: 700; }
    .rm-l[data-s="done"] { background: var(--success-50); border-color: var(--success-200); color: var(--success-800); }
    .rm-l[data-s="here"] { background: var(--primary-50); border-color: var(--primary-400); color: var(--primary-800); font-weight: 700; }
    .rm-l[data-s="review"] { background: var(--warning-50); border-color: var(--warning-300); color: var(--warning-800); }
    .rm-l .rm-clip { display: inline-flex; align-items: center; gap: .1rem; color: var(--gray-400); font-weight: 600; }
    .rm-l .rm-play { font-size: .7rem; font-weight: 700; color: var(--success-700); }
    .rm-mats { display: flex; flex-direction: column; gap: .3rem; margin-top: .45rem; padding-top: .45rem; border-top: 1px dashed var(--gray-200); }
    .rm-mat { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem .4rem; font-size: .75rem; }
    .rm-mat > span { color: var(--gray-600); font-weight: 600; margin-inline-end: .2rem; }
    .rm-mat a { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: var(--radius-md, .5rem); background: var(--primary-50); color: var(--primary-700); text-decoration: none; }
    .rm-mat a:hover, .rm-mat a:focus-visible { background: var(--primary-100); }
    .rm-mat a small { color: var(--gray-500); }
    .rm-mat svg { width: .9rem; height: .9rem; flex: none; }
    .dark .rm-level { border-color: var(--gray-700); }
    .dark .rm-mats { border-color: var(--gray-700); }
    .dark .rm-mat a { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
</style>
<div class="rm-levels">
    @foreach ($map['levels'] as $level)
        <details class="rm-level" @if ($level['has_current'] || count($map['levels']) === 1) open @endif>
            <summary>
                <strong>{{ $level['title'] }}</strong>
                <span class="rm-bar"><i class="lms-bar-fill" style="width: {{ $level['percent'] }}%"></i></span>
                <span class="rm-pct">{{ $level['percent'] }}%</span>
            </summary>
            <div class="rm-body">
                @if ($level['lessons'] !== [])
                    <div class="rm-lessons">
                        @foreach ($level['lessons'] as $l)
                            <span class="rm-l" data-s="{{ $l['state'] }}" title="{{ __('learning::progress.states.'.$l['state']) }}"><b>{{ ['done' => '✓', 'here' => '●', 'review' => '↻', 'todo' => '○'][$l['state']] }}</b>{{ $l['title'] }}@if (($l['materials'] ?? []) !== [])<span class="rm-clip" title="{{ trans_choice('learning::progress.materials_count', count($l['materials']), ['count' => count($l['materials'])]) }}"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m20 11.5-7.7 7.7a4.5 4.5 0 0 1-6.4-6.4l7.8-7.8a3 3 0 0 1 4.2 4.2l-7.8 7.8a1.5 1.5 0 0 1-2.1-2.1l7.1-7.1"/></svg>{{ count($l['materials']) }}</span>@endif@if ($l['practice'] ?? null)<span class="rm-play" title="{{ __('learning::progress.practice', ['correct' => $l['practice']['correct'], 'answered' => $l['practice']['answered'], 'rounds' => $l['practice']['rounds']]) }}">🎮{{ $l['practice']['percent'] }}%</span>@endif</span>
                        @endforeach
                    </div>
                @endif
                @include('learning::filament.roadmap-materials', ['lessons' => $mat($level['lessons'])])
                @foreach ($level['units'] as $unit)
                    <div class="rm-unit">
                        <small>{{ $unit['title'] }} · {{ $unit['percent'] }}%</small>
                        <div class="rm-lessons">
                            @foreach ($unit['lessons'] as $l)
                                <span class="rm-l" data-s="{{ $l['state'] }}" title="{{ __('learning::progress.states.'.$l['state']) }}"><b>{{ ['done' => '✓', 'here' => '●', 'review' => '↻', 'todo' => '○'][$l['state']] }}</b>{{ $l['title'] }}@if (($l['materials'] ?? []) !== [])<span class="rm-clip" title="{{ trans_choice('learning::progress.materials_count', count($l['materials']), ['count' => count($l['materials'])]) }}"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m20 11.5-7.7 7.7a4.5 4.5 0 0 1-6.4-6.4l7.8-7.8a3 3 0 0 1 4.2 4.2l-7.8 7.8a1.5 1.5 0 0 1-2.1-2.1l7.1-7.1"/></svg>{{ count($l['materials']) }}</span>@endif@if ($l['practice'] ?? null)<span class="rm-play" title="{{ __('learning::progress.practice', ['correct' => $l['practice']['correct'], 'answered' => $l['practice']['answered'], 'rounds' => $l['practice']['rounds']]) }}">🎮{{ $l['practice']['percent'] }}%</span>@endif</span>
                            @endforeach
                        </div>
                        @include('learning::filament.roadmap-materials', ['lessons' => $mat($unit['lessons'])])
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
