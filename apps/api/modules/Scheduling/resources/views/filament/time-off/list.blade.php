@php $l = 'scheduling::timeoff.admin.'; $groups = $this->groups; @endphp
<x-filament-panels::page>
    @include('scheduling::filament.time-off.styles')

    @foreach (['pending', 'cover', 'ahead', 'past'] as $group)
        <section>
            <h2 class="to-h2 lms-heading">{{ __($l.'groups.'.$group) }} <small>{{ count($groups[$group]) }}</small></h2>
            <div class="to-list lms-stagger">
                @forelse ($groups[$group] as $r)
                    <a class="to-card lms-lift" href="{{ $r['url'] }}" wire:navigate wire:key="to-{{ $r['id'] }}">
                        <div>
                            <strong>{{ $r['teacher'] }}</strong>
                            <span>{{ $r['period'] }}</span>
                            <small>{{ $r['reason'] }} · {{ $r['asked'] }}</small>
                        </div>
                        <div class="to-mid">
                            <small>{{ trans_choice('scheduling::timeoff.lessons', $r['lessons'], ['count' => $r['lessons']]) }}</small>
                            @if ($r['cover'] && $r['cover']['left'] > 0)
                                <span class="to-left">{{ trans_choice($l.'left', $r['cover']['left'], ['count' => $r['cover']['left']]) }}</span>
                            @elseif ($r['cover'] && $r['cover']['total'] > 0)
                                <span class="to-ok">{{ __($l.'all_covered') }}</span>
                            @endif
                        </div>
                        <div class="to-side">
                            <span class="to-pill" data-s="{{ $r['status'] }}">{{ $r['statusLabel'] }}</span>
                            <small style="color: var(--primary-700); font-weight: 600">{{ __($l.'open') }}</small>
                        </div>
                    </a>
                @empty
                    <div class="to-empty">{{ __($l.'empty.'.$group) }}</div>
                @endforelse
            </div>
        </section>
    @endforeach
</x-filament-panels::page>
