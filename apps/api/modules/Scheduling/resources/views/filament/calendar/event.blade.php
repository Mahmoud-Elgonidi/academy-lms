{{-- One lesson in the grid: placed by minutes ($axisFrom in hours), side by side with what overlaps it. --}}
@php
    $who = $filter->studentId !== null ? $e['teacher'] : ($e['student'] !== '' ? $e['student'] : $e['teacher']);
    $what = $filter->teacherId !== null || $filter->studentId !== null ? $e['subject'] : $e['teacher'].' · '.$e['subject'];
@endphp
<button type="button" class="{{ $class }}" data-status="{{ $e['status'] }}" @if ($e['now']) data-live @endif @if ($e['end'] - $e['start'] < 40) data-short @endif
        style="--s: {{ max(0, $e['start'] - $axisFrom * 60) }}; --d: {{ max(10, $e['end'] - max($e['start'], $axisFrom * 60)) }}; --lane: {{ $e['lane'] }}; --lanes: {{ $e['lanes'] }}"
        wire:click="mountAction('lesson', { id: '{{ $e['id'] }}' })"
        title="{{ $e['time'] }} · {{ $e['subject'] }} · {{ $e['teacher'] }}{{ $e['student'] !== '' ? ' → '.$e['student'] : '' }} · {{ $e['statusLabel'] }}{{ $e['typeLabel'] ? ' · '.$e['typeLabel'] : '' }}">
    @if ($class === 'cal-tev')
        {{-- A row is one teacher's: their student first, then the time (the place on the row says it too). --}}
        <span class="cal-ev-line"><span>{{ $who }}</span></span>
        <span class="cal-ev-sub"><b>{{ $e['from'] }}</b><span>{{ $what }}</span></span>
    @else
        <span class="cal-ev-line"><b>{{ $e['from'] }}</b><span>{{ $who }}</span></span>
        <span class="cal-ev-sub"><span>{{ $what }}</span></span>
    @endif
    @if ($e['typeLabel'] || $e['unpaid'] || $e['substitute'])
        <span class="cal-ev-tags">
            @if ($e['typeLabel'])<i>{{ $e['typeLabel'] }}</i>@endif
            @if ($e['unpaid'])<i data-tone="danger">{{ __('scheduling::calendar.unpaid') }}</i>@endif
            @if ($e['substitute'])<i>{{ __('scheduling::calendar.substitute') }}</i>@endif
        </span>
    @endif
</button>
