<div x-data="{ copied: false }" style="display: flex; flex-direction: column; gap: .6rem">
    <input type="text" readonly value="{{ $url }}" dir="ltr" x-ref="link" x-on:focus="$el.select()"
           style="width: 100%; font-size: .8rem; padding: .55rem .65rem; border: 1px solid var(--gray-300); border-radius: var(--radius-lg, .5rem); background: var(--gray-50)">
    <div style="display: flex; gap: .5rem; flex-wrap: wrap">
        <x-filament::button size="sm" icon="heroicon-o-clipboard" x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 1500)">
            <span x-show="! copied">{{ __('billing::payments.settings.copy') }}</span><span x-show="copied" x-cloak>{{ __('billing::payments.settings.copied') }}</span>
        </x-filament::button>
        <x-filament::button size="sm" color="gray" tag="a" href="{{ $url }}" target="_blank" icon="heroicon-o-arrow-top-right-on-square">{{ __('billing::payments.invoice.open') }}</x-filament::button>
    </div>
</div>
