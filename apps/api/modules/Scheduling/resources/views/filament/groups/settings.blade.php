<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-6" style="max-width: 56rem">
        {{ $this->form }}
        <div><x-filament::button type="submit" icon="heroicon-m-check">{{ __('scheduling::group_screens.settings.save') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
