@php $l = 'scheduling::timeoff.mine.'; @endphp
<x-filament-panels::page>
    @include('scheduling::filament.time-off.styles')

    @if ($this->holidays !== [])
        <div class="to-banner">
            <x-filament::icon icon="heroicon-o-sparkles" />
            <span><strong>{{ __($l.'holidays') }}:</strong>
                @foreach ($this->holidays as $h) {{ $h['name'] }} ({{ $h['period'] }})@if (! $loop->last)، @endif @endforeach
            </span>
        </div>
    @endif

    <div class="to-list lms-stagger">
        @forelse ($this->requests as $r)
            <article class="to-card" wire:key="to-{{ $r['id'] }}">
                <div>
                    <strong>{{ $r['period'] }}</strong>
                    <small>{{ $r['reason'] }}@if ($r['days']) · {{ $r['days'] }}@endif @if ($r['byAcademy']) · {{ __('scheduling::timeoff.review.requested_by_academy') }}@endif</small>
                    @if ($r['decision'])
                        <div class="to-note"><strong>{{ __($l.'decision_note') }}:</strong> {{ $r['decision'] }}</div>
                    @endif
                </div>
                <div class="to-mid">
                    @if ($r['cover'] && $r['cover']['total'] > 0)
                        <small>{{ trans_choice('scheduling::timeoff.lessons', $r['cover']['total'], ['count' => $r['cover']['total']]) }}</small>
                        <span @class(['to-left' => $r['cover']['left'] > 0, 'to-ok' => $r['cover']['left'] === 0])>
                            {{ __($l.'cover', ['covered' => $r['cover']['covered'], 'cancelled' => $r['cover']['cancelled'], 'left' => $r['cover']['left']]) }}
                        </span>
                    @endif
                </div>
                <div class="to-side">
                    <span class="to-pill" data-s="{{ $r['status'] }}">{{ $r['statusLabel'] }}</span>
                    @if ($r['withdrawable'])
                        {{ ($this->withdrawAction)(['id' => $r['id']]) }}
                    @endif
                </div>
            </article>
        @empty
            <div class="to-empty">{{ __($l.'empty') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
