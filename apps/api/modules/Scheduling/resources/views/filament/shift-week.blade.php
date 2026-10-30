{{-- Two weeks side by side (days × blocks), on the teacher's clock; each block marked unchanged, added, removed or changed. --}}
@php $l = 'scheduling::review.'; $single ??= false; @endphp

<div class="sw" data-single="{{ $single ? 'true' : 'false' }}">
    <div class="sw-head">
        <span></span>
        <span>{{ $left }}</span>
        @unless ($single)<span>{{ $right }}</span>@endunless
    </div>
    @foreach ($week as $row)
        <div class="sw-row" data-changed="{{ $row['changed'] && ! $single ? 'true' : 'false' }}" wire:key="sw-{{ $row['day'] }}">
            <span class="sw-day">{{ $row['label'] }}</span>
            <span class="sw-cell">
                @forelse ($row['before'] as $b)
                    <span class="sw-block" data-kind="{{ $single ? 'same' : $b['kind'] }}" dir="auto">{{ $b['label'] }}</span>
                @empty
                    <span class="sw-off">{{ __($l.'day_off') }}</span>
                @endforelse
            </span>
            @unless ($single)
                <span class="sw-cell">
                    @forelse ($row['after'] as $b)
                        <span class="sw-block" data-kind="{{ $b['kind'] }}" dir="auto">{{ $b['label'] }}</span>
                    @empty
                        <span class="sw-off">{{ __($l.'day_off') }}</span>
                    @endforelse
                </span>
            @endunless
        </div>
    @endforeach
    @unless ($single)
        <div class="sw-legend">
            <span><i data-kind="added"></i>{{ __($l.'legend.added') }}</span>
            <span><i data-kind="removed"></i>{{ __($l.'legend.removed') }}</span>
            <span><i data-kind="changed"></i>{{ __($l.'legend_changed') }}</span>
            <span><i data-kind="same"></i>{{ __($l.'legend.same') }}</span>
        </div>
    @endunless
</div>
