@php
    $l = 'scheduling::calendar.';
    $from = $board['axis']['from'];
    $to = $board['axis']['to'];
    $clip = fn (array $s) => ['s' => max(0, $s['start'] - $from * 60), 'd' => min($s['end'], $to * 60) - max($s['start'], $from * 60)];
@endphp

<div class="cal-card cal-grid-card cal-wide">
    <div class="cal-week" style="--from: {{ $from }}; --hours: {{ $to - $from }}">
        <div class="cal-week-head">
            <div class="cal-corner"></div>
            @foreach ($board['days'] as $d)
                <button type="button" class="cal-wday" wire:click="openDay('{{ $d['date'] }}')" @if ($d['today']) data-today @endif title="{{ $d['full'] }}">
                    <span>{{ $d['name'] }}</span>
                    <strong>{{ $d['num'] }}</strong>
                    <small>{{ trans_choice($l.'totals.lessons', $d['count'], ['count' => $d['count']]) }}</small>
                </button>
            @endforeach
        </div>
        <div class="cal-week-body">
            <div class="cal-axis" aria-hidden="true">
                @for ($h = $from; $h < $to; $h++)
                    <span style="--i: {{ $h - $from }}">{{ \Modules\SharedKernel\Time\Clock::hour($h) }}</span>
                @endfor
            </div>
            @foreach ($board['days'] as $d)
                <div class="cal-col" @if ($d['today']) data-today @endif>
                    @foreach ($d['shades'] as $shade)
                        @php $c = $clip($shade); @endphp
                        @if ($c['d'] > 0)
                            <div class="cal-shade" data-kind="{{ $shade['kind'] }}" style="--s: {{ $c['s'] }}; --d: {{ $c['d'] }}" title="{{ $shade['label'] ?? __($l.'legend.'.$shade['kind']) }}">
                                @if ($shade['kind'] !== 'shift' && $shade['label'])<span>{{ $shade['label'] }}</span>@endif
                            </div>
                        @endif
                    @endforeach
                    @foreach ($d['placed']['items'] as $e)
                        @include('scheduling::filament.calendar.event', ['e' => $e, 'class' => 'cal-ev', 'axisFrom' => $from, 'filter' => $filter])
                    @endforeach
                    @foreach ($d['placed']['more'] as $m)
                        <button type="button" class="cal-more" style="--s: {{ max(0, $m['start'] - $from * 60) }}; --d: {{ max(20, $m['end'] - $m['start']) }}; --lane: {{ $m['lane'] }}; --lanes: {{ $m['lanes'] }}"
                                wire:click="openDay('{{ $d['date'] }}')" title="{{ __($l.'more_hint', ['count' => $m['count']]) }}">{{ __($l.'more', ['count' => $m['count']]) }}</button>
                    @endforeach
                    @if ($d['today'])
                        <div class="cal-now" data-cal-now style="--m: {{ $board['nowMinute'] }}" aria-hidden="true"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @if ($board['shifts'])
        <div class="cal-legend">
            @foreach (['shift', 'off', 'holiday'] as $kind)
                <span data-kind="{{ $kind }}">{{ __($l.'legend.'.$kind) }}</span>
            @endforeach
        </div>
    @endif
</div>

{{-- On a phone the week reads as a list, day by day. --}}
@include('scheduling::filament.calendar.agenda', ['days' => array_values(array_filter($board['days'], fn ($d) => $d['count'] > 0)), 'filter' => $filter, 'phoneOnly' => true])
