{{--
    The join step's head: the group the student takes a seat in, the seat it
    takes (3 → 4 of 5), its weekly times on the student's clock, and where it
    stands. Needs: $g (an OpenGroups row), $student, $subject, $city.
--}}
@php
    use Modules\Scheduling\Filament\GroupView;
    $w = 'scheduling::group_screens.find.join.';
@endphp
@include('scheduling::filament.groups.styles')
<div class="gr-card" style="gap: .8rem" data-status="{{ $g['status'] }}">
    <div class="gr-card-head">
        <div style="min-width: 0">
            <div style="display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; margin-bottom: .35rem">
                @include('scheduling::filament.groups.badge')
                <span class="gr-pill" data-tone="{{ GroupView::tone($g['status']) }}">{{ GroupView::status($g['status']) }}</span>
            </div>
            <h3 class="gr-card-title">{{ $g['name'] ?: $subject }} · {{ $g['teacher_name'] }}</h3>
            <p class="gr-card-sub">{{ __($w.'who', ['name' => $student]) }}</p>
        </div>
    </div>
    <div class="gr-times"><x-filament::icon icon="heroicon-m-clock" /><span>{{ GroupView::timesLine($g['times']) }}</span></div>
    <p class="gr-muted" style="margin-top: -.45rem">{{ __($w.'clock', ['city' => $city]) }} · {{ GroupView::shape($g['minutes'], $g['per_week']) }}</p>
    <div class="gr-foot">
        <span style="display: inline-flex; align-items: center; gap: .6rem">
            @include('scheduling::filament.groups.seats', ['taken' => $g['taken'], 'capacity' => $g['capacity'], 'adding' => true])
            <strong style="font-size: .84rem">{{ __($w.'seat', ['from' => $g['taken'], 'to' => $g['taken'] + 1, 'capacity' => $g['capacity']]) }}</strong>
        </span>
        <span class="gr-muted">
            @if ($g['status'] === 'running')
                {{ trans_choice('scheduling::group_screens.card.held', $g['held'], ['n' => $g['held']]) }}@if ($g['next']) · {{ __('scheduling::group_screens.card.next') }} {{ $g['next']['label'] }}@endif
            @else
                {{ __($w.'forming') }}
            @endif
        </span>
    </div>
</div>
