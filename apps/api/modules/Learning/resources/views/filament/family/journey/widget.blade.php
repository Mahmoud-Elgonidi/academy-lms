@php $d = $this->data(); @endphp
<x-filament-widgets::widget>
    @include('learning::filament.family.journey.'.$d['stage'], $d + ['full' => false])
</x-filament-widgets::widget>
