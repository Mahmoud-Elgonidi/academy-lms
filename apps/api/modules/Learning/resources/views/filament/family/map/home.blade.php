{{-- The journey on the family home: a student's home is the journey itself; a guardian's or an adult's starts with its card. --}}
<x-filament-widgets::widget>
    @if ($this->board())
        @livewire(\Modules\Learning\Filament\Family\JourneyBoard::class, [], key('journey-home'))
    @else
        @include('learning::filament.family.styles')
        @php $j = $this->card(); @endphp
        {{-- The learner's journey (the menu's "الرحلة"): where they are, what waits, their stars. --}}
        <a class="lf-card lf-journey lms-lift" href="{{ \Modules\Learning\Filament\Family\FamilyJourney::getUrl() }}" style="text-decoration: none; color: inherit">
            <div class="lf-head">
                <div>
                    <h3>🗺️ {!! __('learning::map.home.title', ['name' => '<bdi>'.e($j['name']).'</bdi>']) !!}</h3>
                    <small>{{ $j['lesson'] ? __('learning::map.home.here', ['lesson' => $j['lesson']]) : trans_choice('learning::map.home.missions', $j['missions'], ['count' => $j['missions']]) }}</small>
                </div>
                @if ($j['stars'] !== null)
                    <span class="lf-pill" data-tone="warn">⭐ <span data-count="{{ $j['stars'] }}">{{ $j['stars'] }}</span> · {{ $j['rank'] }}</span>
                @endif
                </div>
                @if ($j['percent'] !== null)
                    <span class="lf-bar"><i class="lms-bar-fill" style="width: {{ $j['percent'] }}%"></i></span>
                @endif
                <div class="lf-head" style="align-items: center">
                    <small>{{ $j['lesson'] ? trans_choice('learning::map.home.missions', $j['missions'], ['count' => $j['missions']]) : '' }}</small>
                    <span class="lf-go">{{ __('learning::map.home.open') }} <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span></span>
                </div>
            </a>
    @endif
</x-filament-widgets::widget>
