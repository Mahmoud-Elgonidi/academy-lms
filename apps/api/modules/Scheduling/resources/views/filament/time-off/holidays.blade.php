@php $l = 'scheduling::timeoff.holidays.'; $h = $this->holidays; @endphp
<x-filament-panels::page>
    @include('scheduling::filament.time-off.styles')

    @foreach (['upcoming', 'past'] as $group)
        @if ($group === 'upcoming' || $h['past'] !== [])
            <section>
                <h2 class="to-h2 lms-heading">{{ __($l.$group) }} <small>{{ count($h[$group]) }}</small></h2>
                <div class="to-list lms-stagger">
                    @forelse ($h[$group] as $r)
                        <article class="to-card" wire:key="hol-{{ $r['id'] }}">
                            <div>
                                <strong>{{ $r['name'] }}</strong>
                                <span>{{ $r['period'] }}</span>
                                <small>{{ $r['days'] }}</small>
                            </div>
                            <div class="to-mid">
                                <small>{{ __($l.'cancelled', ['lessons' => trans_choice('scheduling::timeoff.lessons', $r['cancelled'], ['count' => $r['cancelled']])]) }}</small>
                            </div>
                            <div class="to-side">
                                @if ($r['removable'])
                                    {{ ($this->removeAction)(['id' => $r['id']]) }}
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="to-empty">{{ __($l.'empty') }}</div>
                    @endforelse
                </div>
            </section>
        @endif
    @endforeach
</x-filament-panels::page>
