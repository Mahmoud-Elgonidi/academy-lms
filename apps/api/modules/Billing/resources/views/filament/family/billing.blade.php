@php
    $l = 'billing::family.'; $a = $this->account; $online = app(\Modules\Billing\Payments\OnlinePayments::class);
    // Where to transfer, once per text (most payers have one): named by invoice only when their invoices go to different places.
    $transfers = collect($a['invoices'])->filter(fn (array $i) => $i['transfer'] !== null)->groupBy('transfer')
        ->map(fn ($rows, $text) => ['text' => (string) $text, 'numbers' => $rows->pluck('number')->all()])->values();
@endphp
<x-filament-panels::page>
    @include('billing::filament.family.styles')
    <div data-live-channel="{{ $a['channel'] }}" hidden></div>

    @if ($a['result'])
        @php $tone = match ($a['result']->status) { 'paid' => 'good', 'open', 'refund_due' => 'wait', default => 'bad' }; @endphp
        <div class="bf-result" data-tone="{{ $tone }}" role="status" wire:key="result-{{ $a['result']->id }}-{{ $a['result']->status }}">
            <b aria-hidden="true">{{ ['good' => '✓', 'wait' => '…', 'bad' => '!'][$tone] }}</b>
            <div>
                <strong>{{ __('billing::payments.page.result.'.$a['result']->status) }}</strong>
                <small>{{ __('billing::payments.page.result_hint.'.$a['result']->status) }}</small>
            </div>
        </div>
    @endif

    @if ($a['balances'] !== [])
        <h2 class="bf-h2">{{ __($l.'balance_for', ['name' => $a['learner']]) }}</h2>
    @endif
    @include('billing::filament.family.balance-cards', ['balances' => $a['balances'], 'owed' => null, 'extras' => true])

    <h2 class="bf-h2">{{ __($l.'invoices') }}</h2>
    @if ($a['owed'])
        <div class="bf-grid lms-stagger" style="margin-bottom: .6rem">
            <div class="bf-card" data-tone="owed"><small>{{ __($l.'owed') }}</small><span class="bf-big">{{ $a['owed'] }}</span></div>
        </div>
    @endif
    @if ($transfers->isNotEmpty())
        <div class="bf-transfers">
            @foreach ($transfers as $t)
                @include('billing::filament.family.transfer', ['text' => $t['text'], 'numbers' => $transfers->count() > 1 ? $t['numbers'] : []])
            @endforeach
        </div>
    @endif
    <div class="bf-list lms-stagger">
        @forelse ($a['invoices'] as $inv)
            <div class="bf-inv" wire:key="inv-{{ $inv['id'] }}">
                <div>
                    <strong>{{ $inv['total'] }}</strong>
                    <small>· {{ $inv['number'] }}@if ($inv['family']) · {{ __('billing::family.family_invoice') }}@endif @if ($inv['for']) · {{ $inv['for'] }}@endif</small>
                    @foreach ($inv['discounts'] ?? [] as $off)
                        <small class="bf-off">{{ $off['label'] }} {{ $off['amount'] }}</small>
                    @endforeach
                </div>
                <span class="bf-status" data-s="{{ $inv['status'] }}">{{ $inv['statusLabel'] }}</span>
                <small>
                    {{ __($l.'issued', ['date' => $inv['issued']]) }}
                    @if ($inv['paid']) · {{ __($l.'paid_on', ['date' => $inv['paid']]) }}
                    @elseif ($inv['due']) · {{ __($l.'due', ['date' => $inv['due']]) }}@endif
                </small>
                <a class="bf-doc" href="{{ $inv['document'] }}" target="_blank" rel="noopener">{{ __('billing::invoices.doc.view') }}</a>
                @if ($inv['outstanding'] && $inv['status'] === 'partly_paid')
                    <small style="justify-self: end">{{ __($l.'left', ['amount' => $inv['outstanding']]) }}</small>
                @endif
                @if ($inv['proof'] && ($inv['payable'] || $inv['proof']['status'] !== 'rejected'))
                    <div class="bf-proof" data-s="{{ $inv['proof']['status'] }}">{{ $inv['proof']['text'] }}</div>
                @endif
                @if ($inv['payable'] && ($online->offers($inv['currency']) || $inv['canSendProof']))
                    <div class="bf-pay">
                        @if ($inv['canSendProof']) {{ ($this->sendProofAction)(['invoice' => $inv['id']]) }} @endif
                        @if ($online->offers($inv['currency'])) {{ ($this->payAction)(['invoice' => $inv['id']]) }} @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="bf-empty">{{ __($l.'no_invoices') }}</div>
        @endforelse
    </div>
    @if ($a['owed'] && $transfers->isEmpty())
        <p class="bf-note">{{ __($l.'how_to_pay') }}</p>
    @endif
</x-filament-panels::page>

@script
<script>
    // Back from a gateway the payment may still be confirming: the page updates by itself when it goes through
    // (the payer's own live channel; LmsLive, shared/live.blade.php), every two minutes only without a connection.
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.payments.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
