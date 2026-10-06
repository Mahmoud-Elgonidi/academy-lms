@php $l = 'learning::family.reports.'; $a = 'learning::reports.attributes.'; @endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    <div class="lf-stack lms-stagger">
        @forelse ($this->reports as $report)
            <article class="lf-card" wire:key="report-{{ $report['id'] }}">
                <div class="lf-head">
                    <div>
                        <h3>{{ $report['date'] }}</h3>
                        <small>{{ __($l.'by', ['teacher' => $report['teacher']]) }}</small>
                    </div>
                    @if ($report['absence'])
                        <span class="lf-pill" data-tone="warn">{{ __('learning::reports.kinds.absence') }}</span>
                    @elseif ($report['overall'])
                        <span title="{{ __($a.'overall') }}"><span class="lf-stars">{{ str_repeat('★', $report['overall']) }}{{ str_repeat('☆', 5 - $report['overall']) }}</span></span>
                    @endif
                </div>
                @if ($report['note'])
                    <p class="lf-note">{{ $report['note'] }}</p>
                @endif
                @if ($report['items'] !== [])
                    <ul class="lf-items">
                        @foreach ($report['items'] as $item)
                            <li><span>{{ $item['title'] }}</span><span class="lf-pill" @if ($item['tone']) data-tone="{{ $item['tone'] }}" @endif>{{ $item['outcome'] }}@if ($item['score']) · {{ $item['score'] }}/5 @endif</span></li>
                        @endforeach
                    </ul>
                @endif
                @if (! $report['absence'] && $report['engagement'])
                    <small class="lf-muted">{{ __($a.'engagement') }}: {{ $report['engagement'] }}/5</small>
                @endif
            </article>
        @empty
            <div class="lf-empty">{{ __($l.'none') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
