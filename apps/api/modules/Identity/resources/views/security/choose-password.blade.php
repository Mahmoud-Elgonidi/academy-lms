<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-6" style="max-width: 32rem">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>
        <div><x-filament::button type="submit">{{ __('identity::security.password.save') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
