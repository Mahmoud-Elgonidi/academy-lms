@php $l = 'scheduling::family.'; $lessons = $this->lessons(); @endphp
<x-filament-widgets::widget>
    @include('scheduling::filament.family.styles')
    <x-filament::section :heading="__($l.'widget.title')">
        <x-slot name="afterHeader">
            <x-filament::link :href="\Modules\Scheduling\Filament\Family\FamilyLessons::getUrl()" size="sm">{{ __($l.'widget.all') }}</x-filament::link>
        </x-slot>
        <div class="fl-list lms-stagger">
            @forelse ($lessons as $lesson)
                @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson])
            @empty
                <div class="fl-empty">{{ __($l.'widget.none') }}</div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
