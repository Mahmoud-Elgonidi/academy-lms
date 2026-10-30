{{-- A teacher's schedule: the one in force now and one approved to start later, side by side. --}}
@php $l = 'scheduling::review.'; @endphp

@if ($t === null)
    <p class="sp-muted">{{ __($l.'missing') }}</p>
@else
    <div class="spo">
        <div class="spo-facts">
            <span><small>{{ __($l.'current_title') }}</small> {{ __($l.'hours_week', ['hours' => $t['current_hours']]) }}</span>
            @if ($t['upcoming'])
                <span><small>{{ __($l.'upcoming_title', ['date' => $t['upcoming']['from']]) }}</small> {{ __($l.'hours_week', ['hours' => $t['upcoming']['hours']]) }}</span>
            @endif
            @if ($t['open'])
                <span class="sp-pill" data-status="{{ $t['open']['status'] }}">{{ $t['open']['status_label'] }}</span>
            @endif
        </div>

        <section class="spo-step">
            <h3 class="lms-heading">{{ __($l.'teacher_clock', ['city' => $t['city']]) }}</h3>
            @if ($t['upcoming'])
                @include('scheduling::filament.shift-week', ['week' => $t['week'], 'left' => __($l.'columns.current'), 'right' => __($l.'columns.upcoming').' · '.$t['upcoming']['from']])
            @else
                @include('scheduling::filament.shift-week', ['week' => $t['week'], 'left' => __($l.'columns.current'), 'right' => '', 'single' => true])
                <p class="sp-muted">{{ __($l.'no_upcoming') }}</p>
            @endif
        </section>
    </div>
@endif
