@php
    $l = 'scheduling::family.';
    $lessons = $this->lessons;
@endphp
<x-filament-panels::page>
    @include('scheduling::filament.family.styles')

    <h2 class="fl-h2">{{ __($l.'upcoming') }}</h2>
    <div class="fl-list">
        @forelse ($lessons['upcoming'] as $lesson)
            @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson, 'cancel' => $this->cancelAction ?? null])
        @empty
            <div class="fl-empty">{{ __($l.'none_upcoming') }}</div>
        @endforelse
    </div>

    <h2 class="fl-h2">{{ __($l.'recent') }}</h2>
    <div class="fl-list">
        @forelse ($lessons['recent'] as $lesson)
            @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson])
        @empty
            <div class="fl-empty">{{ __($l.'none_recent') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
