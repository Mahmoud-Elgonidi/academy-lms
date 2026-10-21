@php($s = 'learning::digest.settings.')
<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-6">
        <x-filament::section>
            <div class="flex flex-col gap-4">
                {{ $this->form }}
                <p style="margin:0; color: var(--gray-600)">{{ __($s.'what') }}</p>
                <p style="margin:0; color: var(--gray-600)">{{ __($s.'never') }}</p>
                <p style="margin:0; color: var(--gray-600)">{{ __($this->worded() ? $s.'wording_on' : $s.'wording_off') }}</p>
                <p style="margin:0; font-size:.875rem; color: var(--gray-500)">{{ trans_choice($s.'last_week', $this->lastWeek(), ['count' => $this->lastWeek()]) }}</p>
            </div>
        </x-filament::section>
        <div><x-filament::button type="submit">{{ __('filament-panels::resources/pages/edit-record.form.actions.save.label') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
