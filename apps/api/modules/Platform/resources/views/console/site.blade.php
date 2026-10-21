<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .ps-save { position: sticky; inset-block-end: 0; z-index: 5; display: flex; justify-content: flex-end; gap: .75rem; align-items: center;
            margin-inline: -.5rem; padding: .75rem .5rem; border-block-start: 1px solid var(--gray-200);
            background: color-mix(in oklab, var(--gray-50) 88%, transparent); backdrop-filter: blur(6px); }
        .dark .ps-save { border-block-start-color: var(--gray-800); background: color-mix(in oklab, var(--gray-950) 88%, transparent); }
        .ps-save small { font-size: .8rem; color: var(--gray-500); }
    </style>
    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}
        <div class="ps-save">
            <small>{{ __('platform::site.console.save_hint') }}</small>
            <x-filament::button type="submit" icon="heroicon-m-check">{{ __('platform::site.console.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
