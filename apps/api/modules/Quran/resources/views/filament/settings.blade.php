<x-filament-panels::page>
    <form wire:submit="save" class="lms-stagger" style="display: flex; flex-direction: column; gap: 1.5rem">
        {{ $this->form }}
        <div style="position: sticky; bottom: 1rem; display: flex; justify-content: flex-end; z-index: 5">
            <x-filament::button type="submit" icon="heroicon-m-check" size="lg">{{ __('quran::quran.settings.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
