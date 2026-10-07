{{-- A statement: the month's paid lessons and its bonuses and deductions. $canReverse shows "take back" on live adjustments. --}}
@php $l = 'payroll::payroll.'; $lines = $this->lines; @endphp
<div class="py-two">
    <section class="py-base py-box">
        <h3>{{ __($l.'statement.lessons') }} <span>{{ count($lines['lessons']) }}</span></h3>
        @forelse ($lines['lessons'] as $row)
            <div class="py-line" data-state="{{ $row['state'] }}" wire:key="ln-{{ $row['key'] }}">
                <div>
                    <strong><span class="py-dot"></span>{{ $row['when'] }}</strong>
                    <p>{{ $row['student'] }}@if ($row['subject']) · {{ $row['subject'] }}@endif</p>
                    <div class="py-tags">
                        <span @if ($row['state'] !== 'completed') data-tone="warning" @endif>{{ $row['outcome'] }}</span>
                        @if ($row['type'])<span>{{ $row['type'] }}</span>@endif
                        @foreach ($row['notes'] as $note)<span data-tone="warning">{{ $note }}</span>@endforeach
                    </div>
                </div>
                <div>
                    <strong @class(['py-neg' => $row['negative']])>{{ $row['amount'] }}</strong>
                    @if ($row['share'])<small>{{ $row['share'] }}</small>@endif
                    @if ($row['rate'])<small>{{ $row['rate'] }}</small>@endif
                </div>
            </div>
        @empty
            <p class="py-empty">{{ __($l.'statement.no_lessons') }}</p>
        @endforelse
    </section>
    <section class="py-base py-box">
        <h3>{{ __($l.'statement.adjustments') }} <span>{{ count($lines['adjustments']) }}</span></h3>
        @forelse ($lines['adjustments'] as $row)
            <div class="py-line" data-state="{{ $row['state'] }}" @if ($row['reversed']) data-out @endif wire:key="adj-{{ $row['id'] }}">
                <div>
                    <strong><span class="py-dot"></span>{{ $row['kind'] }}</strong>
                    <p>{{ $row['note'] }}</p>
                    <small>{{ $row['at'] }}@if ($row['reversed']) · {{ __($l.'statement.reversed_tag') }}@endif</small>
                </div>
                <div>
                    <strong @class(['py-neg' => $row['negative'], 'py-pos' => ! $row['negative']])>{{ $row['amount'] }}</strong>
                    @if (($canReverse ?? false) && $row['live']){{ ($this->reverseAction)(['entry' => $row['id']]) }}@endif
                </div>
            </div>
        @empty
            <p class="py-empty">{{ __($l.'statement.no_adjustments') }}</p>
        @endforelse
    </section>
</div>
