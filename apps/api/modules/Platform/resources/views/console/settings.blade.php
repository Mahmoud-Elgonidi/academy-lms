@php $l = 'platform::settings.'; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .ps-promise { margin: 0; padding: .8rem 1rem; display: flex; gap: .6rem; align-items: flex-start; font-size: .88rem; color: var(--gray-700); }
        .ps-promise svg { inline-size: 1.15rem; block-size: 1.15rem; flex-shrink: 0; color: var(--primary-600); margin-block-start: .1rem; }
        .ps-save { position: sticky; inset-block-end: .75rem; z-index: 5; display: flex; justify-content: flex-end; }
    </style>

    <p class="pc-base ps-promise">
        <x-filament::icon icon="heroicon-o-lock-closed" />
        <span>{{ __($l.'promise') }}</span>
    </p>

    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}
        <div class="ps-save">
            <x-filament::button type="submit" icon="heroicon-m-check">{{ __($l.'save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
