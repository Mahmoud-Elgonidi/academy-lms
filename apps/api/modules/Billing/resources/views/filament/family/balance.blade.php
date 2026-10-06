@php $d = $this->data(); @endphp
<x-filament-widgets::widget>
    @include('billing::filament.family.styles')
    @if ($d['balances'] !== [] || $d['owed'])
        @include('billing::filament.family.balance-cards', ['balances' => $d['balances'], 'owed' => $d['owed']])
    @endif
</x-filament-widgets::widget>
