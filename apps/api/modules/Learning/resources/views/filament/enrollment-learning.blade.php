@php
    $map = $this->roadmap;
    $eta = $map['eta_weeks'] ?? null;
@endphp
<section class="lr ac-box" style="gap: .9rem">
    <style>
        .lr-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .lr-head h2 { margin: 0; font-size: 1rem; font-weight: 700; }
        .lr-head small { color: var(--gray-500); font-weight: 400; font-size: .8125rem; }
        .lr-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .75rem; }
        .lr-stat { border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: .65rem .8rem; display: flex; flex-direction: column; gap: .15rem; }
        .lr-stat small { color: var(--gray-500); font-size: .75rem; }
        .lr-stat strong { font-size: 1.05rem; }
        .lr-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: .9rem; }
        @media (min-width: 1000px) { .lr-cols { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
        .lr-list { display: flex; flex-direction: column; }
        .lr-list h3 { margin: 0 0 .35rem; font-size: .85rem; font-weight: 700; }
        .lr-row { display: flex; gap: .6rem; align-items: baseline; padding: .4rem 0; border-top: 1px solid var(--gray-100); font-size: .8125rem; }
        .lr-row > span:first-child { color: var(--gray-500); min-width: 5.5rem; white-space: nowrap; }
        .lr-row p { margin: 0; color: var(--gray-700); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lr-pill { white-space: nowrap; padding: .05rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 700; background: var(--gray-100); color: var(--gray-700); }
        .lr-pill[data-s="overdue"], .lr-pill[data-s="missed"] { background: var(--danger-50); color: var(--danger-700); }
        .lr-pill[data-s="due"], .lr-pill[data-s="assigned"] { background: var(--warning-50); color: var(--warning-800); }
        .lr-pill[data-s="submitted"] { background: var(--info-50); color: var(--info-700); }
        .lr-pill[data-s="reviewed"] { background: var(--success-50); color: var(--success-700); }
        .lr-late { color: var(--danger-700); font-size: .75rem; }
        .dark .lr-stat { border-color: var(--gray-700); }
    </style>

    <div class="lr-head">
        <h2 class="lms-heading">{{ __('learning::progress.title') }} @if ($map) <small>· {{ $map['program']['name'] }}</small> @endif</h2>
        @if ($this->attachAction->isVisible()) {{ $this->attachAction }} @endif
    </div>

    @if (! $map)
        <p class="ac-muted" style="margin:0">{{ $this->attachAction->isVisible() ? __('learning::progress.no_program') : __('learning::progress.no_programs') }}</p>
    @else
        <div class="lr-stats">
            <div class="lr-stat">
                <small>{{ __('learning::progress.percent') }}</small>
                <strong class="lms-heading">{{ $map['percent'] }}%</strong>
                <div class="ac-meter"><i style="width: {{ $map['percent'] }}%"></i></div>
                <small>{{ __('learning::progress.lessons', $map['lessons']) }}</small>
            </div>
            <div class="lr-stat">
                <small>{{ __('learning::progress.here') }}</small>
                @if ($map['current'])
                    <strong>{{ $map['current']['title'] }}</strong>
                    <small>{{ implode(' › ', $map['current']['path']) }}</small>
                @else
                    <strong>{{ __('learning::progress.finished') }}</strong>
                @endif
            </div>
            <div class="lr-stat">
                <small>{{ __('learning::progress.eta') }}</small>
                @if ($eta !== null)
                    <strong>{{ trans_choice('learning::progress.eta_value', (int) ceil($eta), ['n' => (int) ceil($eta)]) }}</strong>
                    <small>{{ __('learning::progress.pace', ['per' => $map['lessons_per_week']]) }}</small>
                @else
                    <strong>—</strong>
                    <small>{{ __('learning::progress.eta_unknown') }}</small>
                @endif
            </div>
        </div>

        @include('learning::filament.roadmap', ['map' => $map])
    @endif

    <div class="lr-cols">
        <div class="lr-list">
            <h3>{{ __('learning::progress.reports') }}</h3>
            @forelse ($this->reports as $r)
                <div class="lr-row">
                    <span>{{ $r['when'] }}</span>
                    <span class="lr-pill" data-s="{{ $r['status'] }}">{{ $r['kind'] === 'absence' ? __('learning::progress.absent') : __('learning::reports.statuses.'.$r['status']) }}</span>
                    <p>@if ($r['overall']) {{ __('learning::progress.overall', ['n' => $r['overall']]) }} · @endif{{ $r['note'] }}</p>
                    @if ($r['late']) <span class="lr-late">{{ __('learning::progress.late') }}</span> @endif
                </div>
            @empty
                <span class="ac-muted">{{ __('learning::progress.none') }}</span>
            @endforelse
        </div>
        <div class="lr-list">
            <h3>{{ __('learning::progress.homework') }}</h3>
            @forelse ($this->homework as $h)
                <div class="lr-row">
                    <span>{{ __('learning::progress.due', ['date' => $h['due']]) }}</span>
                    <span class="lr-pill" data-s="{{ $h['status'] }}">{{ __('learning::homework.statuses.'.$h['status']) }}</span>
                    <p>{{ $h['title'] }}@if ($h['grade']) · {{ __('learning::progress.grade', ['n' => $h['grade']]) }} @endif</p>
                </div>
            @empty
                <span class="ac-muted">{{ __('learning::progress.none') }}</span>
            @endforelse
        </div>
    </div>

    <x-filament-actions::modals />
</section>
