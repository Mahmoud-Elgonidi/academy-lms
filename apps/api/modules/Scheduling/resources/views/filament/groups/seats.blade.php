{{--
    A group's seats as dots, taken ones filled (●●●○○ 3/5), in the group tone;
    the seat about to be taken (a join) pulses. Needs: $taken, $capacity; $adding (bool).
--}}
@php $adding = $adding ?? false; @endphp
<span class="gr-seats" role="img" aria-label="{{ \Modules\Scheduling\Filament\GroupView::seatsLabel($taken, $capacity) }}">
    <span class="gr-dots" aria-hidden="true">
        @foreach (range(1, max(1, $capacity)) as $seat)
            <i @if ($seat <= $taken) data-on @elseif ($adding && $seat === $taken + 1) data-new @endif></i>
        @endforeach
    </span>
    <span class="gr-seats-n" aria-hidden="true">{{ $adding ? ($taken + 1) : $taken }}/{{ $capacity }}</span>
</span>
