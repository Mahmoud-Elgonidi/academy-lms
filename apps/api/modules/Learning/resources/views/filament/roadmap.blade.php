{{-- The roadmap: levels → units → lessons, ✓ mastered, ● here, ↻ review, ○ to come. Needs $map (RoadmapQuery::for). --}}
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
    .dark .rm-level { border-color: var(--gray-700); }
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
                            <span class="rm-l" data-s="{{ $l['state'] }}" title="{{ __('learning::progress.states.'.$l['state']) }}"><b>{{ ['done' => '✓', 'here' => '●', 'review' => '↻', 'todo' => '○'][$l['state']] }}</b>{{ $l['title'] }}</span>
                        @endforeach
                    </div>
                @endif
                @foreach ($level['units'] as $unit)
                    <div class="rm-unit">
                        <small>{{ $unit['title'] }} · {{ $unit['percent'] }}%</small>
                        <div class="rm-lessons">
                            @foreach ($unit['lessons'] as $l)
                                <span class="rm-l" data-s="{{ $l['state'] }}" title="{{ __('learning::progress.states.'.$l['state']) }}"><b>{{ ['done' => '✓', 'here' => '●', 'review' => '↻', 'todo' => '○'][$l['state']] }}</b>{{ $l['title'] }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
