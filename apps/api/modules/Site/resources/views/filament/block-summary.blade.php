{{-- A closed block in the builder's list: its design drawn small, and a button that opens its form. --}}
@php
    $state = $getContainer()->getRawState();
    $id = (string) ($state['id'] ?? '');
    $variant = (string) ($state['variant'] ?? '');
@endphp
<div class="lms-sum">
    <span class="lms-sum__svg" aria-hidden="true">{!! \Modules\Site\Filament\Forms\Schematics::svg($type, $variant) !!}</span>
    <span class="lms-sum__hint">{{ __('site::builder.types.'.$type.'.hint') }}</span>
    @if ($id !== '')
        <x-filament::button size="sm" color="gray" icon="heroicon-m-pencil-square" wire:click="edit('{{ $id }}')">
            {{ __('site::builder.actions.edit') }}
        </x-filament::button>
    @endif
</div>
