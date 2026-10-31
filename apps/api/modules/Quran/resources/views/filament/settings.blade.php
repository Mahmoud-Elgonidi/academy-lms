<x-filament-panels::page>
    <x-filament::section :heading="__('quran::hifz.settings.title')">
        <form wire:submit="save" style="display: flex; flex-direction: column; gap: 1rem">
            {{ $this->form }}
            <div><x-filament::button type="submit">{{ __('quran::quran.settings.save') }}</x-filament::button></div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
