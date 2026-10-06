@php $l = 'learning::family.progress.'; $p = 'learning::progress.'; @endphp
<x-filament-panels::page>
    @if ($this->journey)
        @include('learning::filament.family.journey.'.$this->journey['stage'], $this->journey + ['full' => true])
    @else
    @include('learning::filament.family.styles')
    <div class="lf-stack">
        @forelse ($this->courses as $course)
            @php $map = $course['map']; @endphp
            <section class="lf-card" wire:key="course-{{ $course['id'] }}">
                <div class="lf-head">
                    <div>
                        <h3>{{ $course['subject'] }}</h3>
                        <small>{{ __($l.'with', ['teacher' => $course['teacher']]) }}@if ($map) · {{ $map['program']['name'] }}@endif</small>
                    </div>
                    @if ($map)
                        <span class="lf-pct">{{ $map['percent'] }}%</span>
                    @endif
                </div>
                @if ($map === null)
                    <p class="lf-muted">{{ __($l.'no_program') }}</p>
                @else
                    <span class="lf-bar"><i style="width: {{ $map['percent'] }}%"></i></span>
                    <div class="lf-facts">
                        <div class="lf-fact lf-here">
                            <small>{{ __($p.'here') }}</small>
                            <strong>{{ $map['finished'] ? __($p.'finished') : ($map['current']['title'] ?? '—') }}</strong>
                            @if (! $map['finished'] && $map['current'])
                                <small>{{ implode(' · ', $map['current']['path']) }}</small>
                            @endif
                        </div>
                        <div class="lf-fact">
                            <small>{{ __($l.'lessons') }}</small>
                            <strong>{{ __($p.'lessons', ['mastered' => $map['lessons']['mastered'], 'total' => $map['lessons']['total']]) }}</strong>
                        </div>
                        <div class="lf-fact">
                            <small>{{ __($p.'eta') }}</small>
                            <strong>{{ $map['eta_weeks'] === null ? __($p.'eta_unknown') : trans_choice($p.'eta_value', $map['eta_weeks'], ['n' => $map['eta_weeks']]) }}</strong>
                            @if ($map['lessons_per_week'])
                                <small>{{ __($p.'pace', ['per' => $map['lessons_per_week']]) }}</small>
                            @endif
                        </div>
                    </div>
                    @include('learning::filament.roadmap', ['map' => $map])
                @endif
            </section>
        @empty
            <div class="lf-empty">{{ __($l.'none') }}</div>
        @endforelse
    </div>
    @endif
</x-filament-panels::page>
