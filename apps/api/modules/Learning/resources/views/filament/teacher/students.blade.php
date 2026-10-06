@php $l = 'learning::teacher.students.'; @endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    <style>
        .ts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: .75rem; }
        .ts-grid .lf-card { text-decoration: none; color: inherit; }
        .ts-grid .lf-card:hover { border-color: var(--primary-400); }
    </style>
    <div class="ts-grid lms-stagger">
        @forelse ($this->students as $s)
            <a class="lf-card lms-lift" href="{{ $s['url'] }}" wire:key="st-{{ $s['id'] }}">
                <div class="lf-head">
                    <div>
                        <h3>{{ $s['student'] }}</h3>
                        <small>{{ $s['subject'] }}@if ($s['paused']) · {{ __($l.'paused') }}@endif</small>
                    </div>
                    @if ($s['percent'] !== null) <span class="lf-pct" style="font-size: 1.3rem">{{ $s['percent'] }}%</span> @endif
                </div>
                @if ($s['percent'] !== null)
                    <span class="lf-bar"><i class="lms-bar-fill" style="width: {{ $s['percent'] }}%"></i></span>
                @endif
                <small class="lf-muted">{{ $s['here'] ? __($l.'here', ['lesson' => $s['here']]) : __($l.'no_program') }}</small>
                <div style="display: flex; gap: .5rem; flex-wrap: wrap">
                    <span class="lf-pill">{{ $s['last_report'] ? __($l.'last_report', ['when' => $s['last_report']]) : __($l.'no_report') }}</span>
                    @if ($s['to_mark'] > 0) <span class="lf-pill" data-tone="warn">{{ trans_choice($l.'to_mark', $s['to_mark'], ['n' => $s['to_mark']]) }}</span> @endif
                </div>
            </a>
        @empty
            <div class="lf-empty">{{ __($l.'none') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
