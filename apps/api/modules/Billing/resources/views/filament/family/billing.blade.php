@php $l = 'billing::family.'; $a = $this->account; @endphp
<x-filament-panels::page>
    @include('billing::filament.family.styles')

    @if ($a['balances'] !== [])
        <h2 class="bf-h2">{{ __($l.'balance_for', ['name' => $a['learner']]) }}</h2>
    @endif
    @include('billing::filament.family.balance-cards', ['balances' => $a['balances'], 'owed' => null])

    <h2 class="bf-h2">{{ __($l.'invoices') }}</h2>
    @if ($a['owed'])
        <div class="bf-grid lms-stagger" style="margin-bottom: .6rem">
            <div class="bf-card" data-tone="owed"><small>{{ __($l.'owed') }}</small><span class="bf-big">{{ $a['owed'] }}</span></div>
        </div>
    @endif
    <div class="bf-list lms-stagger">
        @forelse ($a['invoices'] as $inv)
            <div class="bf-inv" wire:key="inv-{{ $inv['id'] }}">
                <div>
                    <strong>{{ $inv['total'] }}</strong>
                    <small>· {{ $inv['number'] }}@if ($inv['for']) · {{ $inv['for'] }}@endif</small>
                </div>
                <span class="bf-status" data-s="{{ $inv['status'] }}">{{ $inv['statusLabel'] }}</span>
                <small>
                    {{ __($l.'issued', ['date' => $inv['issued']]) }}
                    @if ($inv['paid']) · {{ __($l.'paid_on', ['date' => $inv['paid']]) }}
                    @elseif ($inv['due']) · {{ __($l.'due', ['date' => $inv['due']]) }}@endif
                </small>
                @if ($inv['outstanding'] && $inv['status'] === 'partly_paid')
                    <small style="justify-self: end">{{ __($l.'left', ['amount' => $inv['outstanding']]) }}</small>
                @endif
            </div>
        @empty
            <div class="bf-empty">{{ __($l.'no_invoices') }}</div>
        @endforelse
    </div>
    @if ($a['owed'])
        <p class="bf-note">{{ __($l.'how_to_pay') }}</p>
    @endif
</x-filament-panels::page>
