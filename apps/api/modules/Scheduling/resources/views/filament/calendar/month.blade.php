@php $l = 'scheduling::calendar.'; @endphp

<div class="cal-card cal-month-card">
    <div class="cal-month" role="grid">
        @foreach ($board['heads'] as $h)
            <div class="cal-mhead" role="columnheader"><span class="cal-long">{{ $h['name'] }}</span><span class="cal-short">{{ $h['short'] }}</span></div>
        @endforeach
        @foreach ($board['weeks'] as $week)
            @foreach ($week as $d)
                <button type="button" class="cal-mday" role="gridcell" wire:click="openDay('{{ $d['date'] }}')" @if ($d['in']) data-in @endif @if ($d['today']) data-today @endif
                        title="{{ $d['full'] }}{{ $d['total'] ? ' · '.collect($d['counts'])->map(fn ($n, $s) => __($l.'statuses.'.$s).' '.$n)->implode('، ') : '' }}">
                    <span class="cal-mnum">{{ $d['num'] }}</span>
                    @if ($d['total'] > 0)
                        <strong class="cal-mtotal">{{ $d['total'] }}</strong>
                        <span class="cal-mbar" aria-hidden="true">
                            @foreach ($d['counts'] as $status => $n)
                                <span data-status="{{ $status }}" style="--n: {{ $n }}"></span>
                            @endforeach
                        </span>
                        <span class="cal-mlist">
                            @foreach (array_slice($d['counts'], 0, 3, true) as $status => $n)
                                <span><i class="cal-dot" data-status="{{ $status }}"></i>{{ __($l.'statuses.'.$status) }} {{ $n }}</span>
                            @endforeach
                        </span>
                    @endif
                </button>
            @endforeach
        @endforeach
    </div>
</div>
