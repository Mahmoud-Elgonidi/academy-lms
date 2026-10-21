<x-filament-panels::page>
    <style>
        .site-save { position: sticky; inset-block-end: 0; z-index: 5; display: flex; justify-content: flex-end; gap: .75rem; align-items: center;
            margin-inline: -.5rem; padding: .75rem .5rem; border-block-start: 1px solid var(--gray-200);
            background: color-mix(in oklab, var(--gray-50) 88%, transparent); backdrop-filter: blur(6px); }
        .dark .site-save { border-block-start-color: var(--gray-800); background: color-mix(in oklab, var(--gray-950) 88%, transparent); }
        .site-save small { font-size: .8rem; color: var(--gray-500); }
    </style>
    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}
        <div class="site-save">
            <small>{{ __('site::site.settings.save_hint') }}</small>
            <x-filament::button type="submit" icon="heroicon-m-check">{{ __('site::site.settings.save') }}</x-filament::button>
        </div>
    </form>
    <x-filament-actions::modals />
</x-filament-panels::page>
