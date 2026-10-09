@php
    $l = 'scheduling::calendar.';
    $from = $board['axis']['from'];
    $to = $board['axis']['to'];
    $clip = fn (array $s) => ['s' => max(0, $s['start'] - $from * 60), 'd' => min($s['end'], $to * 60) - max($s['start'], $from * 60)];
@endphp

@if ($board['holidays'] !== [])
    <div class="cal-banner">
        <x-filament::icon icon="heroicon-o-sun" class="cal-icon" />
        {{ __($l.'legend.holiday') }}: {{ implode('، ', $board['holidays']) }}
    </div>
@endif

<div class="cal-card cal-grid-card cal-wide">
    @if ($board['rows'] === [])
        <div class="cal-empty">
            <x-filament::icon icon="heroicon-o-calendar" class="cal-empty-icon" />
            <strong>{{ __($l.'empty.title') }}</strong>
            <span>{{ __($l.'empty.day') }}</span>
        </div>
    @else
        <div class="cal-day-scroll">
            <div class="cal-day" style="--from: {{ $from }}; --hours: {{ $to - $from }}">
                <div class="cal-day-head" aria-hidden="true">
                    <div class="cal-corner"></div>
                    <div class="cal-ticks">
                        @for ($h = $from; $h < $to; $h++)
                            <span style="--i: {{ $h - $from }}">{{ sprintf('%02d:00', $h) }}</span>
                        @endfor
                    </div>
                </div>
                @foreach ($board['rows'] as $row)
                    @php $lanes = max([1, ...array_column($row['placed']['items'], 'lanes'), ...array_column($row['placed']['more'], 'lanes')]); @endphp
                    <div class="cal-row">
                        <button type="button" class="cal-row-name" wire:click="focus('teacher', '{{ $row['id'] }}')" title="{{ __($l.'details.teacher_calendar') }}">
                            <strong>{{ $row['name'] }}</strong>
                            <small>{{ $row['free'] ? __($l.'free_today') : trans_choice($l.'totals.lessons', $row['count'], ['count' => $row['count']]) }}</small>
                        </button>
                        <div class="cal-track" style="--lanes: {{ $lanes }}">
                            @foreach ($row['shades'] as $shade)
                                @php $c = $clip($shade); @endphp
                                @if ($c['d'] > 0)
                                    <div class="cal-shade" data-kind="{{ $shade['kind'] }}" style="--s: {{ $c['s'] }}; --d: {{ $c['d'] }}" title="{{ $shade['label'] ?? __($l.'legend.'.$shade['kind']) }}"></div>
                                @endif
                            @endforeach
                            @foreach ($row['placed']['items'] as $e)
                                @include('scheduling::filament.calendar.event', ['e' => $e, 'class' => 'cal-tev', 'axisFrom' => $from, 'filter' => new \Modules\Scheduling\Queries\Calendar\CalendarFilter(teacherId: $row['id'], studentId: $filter->studentId)])
                            @endforeach
                            @foreach ($row['placed']['more'] as $m)
                                <span class="cal-tev cal-tev-more" style="--s: {{ max(0, $m['start'] - $from * 60) }}; --d: {{ max(20, $m['end'] - $m['start']) }}; --lane: {{ $m['lane'] }}">{{ __($l.'more', ['count' => $m['count']]) }}</span>
                            @endforeach
                            @if ($board['today'])
                                <div class="cal-now-v" data-cal-now style="--m: {{ $board['nowMinute'] }}" aria-hidden="true"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="cal-legend">
            @foreach (['shift', 'off', 'holiday'] as $kind)
                <span data-kind="{{ $kind }}">{{ __($l.'legend.'.$kind) }}</span>
            @endforeach
        </div>
    @endif
</div>

@include('scheduling::filament.calendar.agenda', ['days' => $board['days'][0]['count'] > 0 ? $board['days'] : [], 'filter' => $filter, 'phoneOnly' => true])
