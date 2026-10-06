@php $groups = $this->groups; @endphp

<x-filament-panels::page>
    <style>
        .sp { display: flex; flex-direction: column; gap: 1.25rem; }
        .sp-group { display: flex; flex-direction: column; gap: .5rem; }
        .sp-group h2 { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .sp-group h2 small { font-weight: 400; color: var(--gray-500); font-size: .8125rem; }
        .sp-row { display: grid; grid-template-columns: minmax(180px, 1.4fr) 180px minmax(150px, 1fr) 140px 150px auto; align-items: center; gap: .75rem;
            padding: .8rem 1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); text-decoration: none; color: inherit; }
        .sp-row:hover { border-color: var(--primary-400); }
        .sp-row > span { display: flex; flex-direction: column; font-size: .875rem; min-width: 0; }
        .sp-row small { color: var(--gray-500); font-size: .75rem; }
        .sp-pill { white-space: nowrap; align-self: flex-start; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
        .sp-pill[data-status="submitted"] { background: var(--warning-50); color: var(--warning-800); }
        .sp-pill[data-status="countered"], .sp-pill[data-status="changes_requested"] { background: var(--info-50); color: var(--info-700); }
        .sp-pill[data-status="approved"] { background: var(--primary-50); color: var(--primary-700); }
        .sp-pill[data-status="rejected"] { background: var(--danger-50); color: var(--danger-700); }
        .sp-late { color: var(--danger-700) !important; font-weight: 600; }
        .sp-conf { font-weight: 600; color: var(--danger-700); }
        .sp-ok { color: var(--primary-700); }
        .sp-go { color: var(--primary-700); font-weight: 600; font-size: .8125rem; }
        .sp-empty { padding: 1rem; color: var(--gray-500); font-size: .875rem; border: 1px dashed var(--gray-300); border-radius: var(--radius-xl); }
        @media (max-width: 900px) { .sp-row { grid-template-columns: 1fr 1fr; } }
        .dark .sp-row { background: var(--gray-900); border-color: var(--gray-800); }
    </style>

    <div class="sp">
        @foreach (['admin', 'teacher', 'done'] as $group)
            <section class="sp-group">
                <h2 class="lms-heading">{{ __('scheduling::review.groups.'.$group) }} <small>{{ count($groups[$group]) }}</small></h2>
                @forelse ($groups[$group] as $row)
                    <a class="sp-row" href="{{ $row['url'] }}" wire:key="sp-{{ $row['id'] }}">
                        <span><strong>{{ $row['teacher'] }}</strong><small @class(['sp-late' => $row['overdue']])>{{ __('scheduling::review.sent', ['ago' => $row['sent']]) }}@if ($row['overdue']) · {{ __('scheduling::review.overdue') }}@endif</small></span>
                        <span><span class="sp-pill" data-status="{{ $row['status'] }}">{{ $row['status_label'] }}</span></span>
                        <span><small>{{ __('scheduling::review.starts') }}</small>{{ $row['from'] }}</span>
                        <span><small>{{ __('scheduling::review.weekly_hours') }}</small>{{ $row['before'] }} ← {{ $row['after'] }}</span>
                        <span>
                            @if ($row['conflicts'] > 0)
                                <span @class(['sp-conf' => $row['unresolved'] > 0, 'sp-ok' => $row['unresolved'] === 0])>{{ trans_choice('scheduling::review.conflicts_count', $row['conflicts'], ['count' => $row['conflicts']]) }}</span>
                                <small>{{ $row['unresolved'] > 0 ? __('scheduling::review.unresolved_count', ['count' => $row['unresolved']]) : __('scheduling::review.all_resolved') }}</small>
                            @elseif ($row['status'] !== 'approved' && $group !== 'done')
                                <span class="sp-ok">{{ __('scheduling::review.no_conflicts') }}</span>
                            @endif
                        </span>
                        <span class="sp-go">{{ __('scheduling::review.open') }}</span>
                    </a>
                @empty
                    <div class="sp-empty">{{ __('scheduling::review.empty.'.$group) }}</div>
                @endforelse
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
