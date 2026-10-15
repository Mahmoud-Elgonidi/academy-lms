<x-filament-panels::page>
    @php($usage = $this->usage())
    <p style="margin:0; color: var(--gray-600)">{{ __('platform::ai.settings.promise') }}</p>
    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}
        <p style="margin:0; font-size:.875rem; color: var(--gray-500)">{{ __('platform::ai.settings.usage', $usage) }}</p>
        <div><x-filament::button type="submit">{{ __('filament-panels::resources/pages/edit-record.form.actions.save.label') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
