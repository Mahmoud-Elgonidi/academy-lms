{{-- What moved the numbers. $events from KpiView::event(); $manage: the exclude / put back actions. --}}
@php $l = 'evaluation::kpis.'; @endphp
<section class="kp-base kp-box">
    <h3 class="lms-heading">{{ __($l.'events_title') }}</h3>
    @forelse ($events as $e)
        <div class="kp-event" @if ($e['excluded']) data-out @endif wire:key="ev-{{ $e['session'] }}-{{ $e['kind'] }}">
            <div>
                <strong>{{ $e['text'] }}</strong>
                <small>{{ $e['lesson'] }}</small>
                @if ($e['comment'])<p>{{ $e['comment'] }}</p>@endif
                @if ($e['excluded'])<small class="kp-out">{{ $e['excluded'] }}@if ($e['note']) · {{ $e['note'] }}@endif</small>@endif
            </div>
            @if ($manage ?? false)
                <div>
                    @if ($e['excluded']) {{ ($this->undoAction)(['session' => $e['session']]) }} @else {{ ($this->excludeAction)(['session' => $e['session']]) }} @endif
                </div>
            @endif
        </div>
    @empty
        <span class="kp-empty">{{ __($l.'no_events') }}</span>
    @endforelse
</section>
