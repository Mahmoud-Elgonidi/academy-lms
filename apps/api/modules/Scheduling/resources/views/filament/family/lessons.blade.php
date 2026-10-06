@php
    $l = 'scheduling::family.';
    $lessons = $this->lessons;
@endphp
<x-filament-panels::page>
    @include('scheduling::filament.family.styles')
    @include('scheduling::filament.time-off.styles')

    @foreach ($this->holidays as $h)
        <div class="to-banner" style="margin-bottom: .5rem"><x-filament::icon icon="heroicon-o-sparkles" />{{ __('scheduling::timeoff.family.holiday', ['name' => $h['name'], 'period' => $h['period']]) }}</div>
    @endforeach

    <h2 class="fl-h2">{{ __($l.'upcoming') }}</h2>
    <div class="fl-list lms-stagger">
        @forelse ($lessons['upcoming'] as $lesson)
            @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson, 'cancel' => $this->cancelAction ?? null, 'refuse' => $this->refuseAction ?? null])
        @empty
            <div class="fl-empty">{{ __($l.'none_upcoming') }}</div>
        @endforelse
    </div>

    <h2 class="fl-h2">{{ __($l.'recent') }}</h2>
    <div class="fl-list lms-stagger">
        @forelse ($lessons['recent'] as $lesson)
            @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson])
        @empty
            <div class="fl-empty">{{ __($l.'none_recent') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
