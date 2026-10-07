{{-- The credit note that cancelled a tax invoice: its own number and time, why, the amounts it takes back, and its QR code. --}}
@php $l = 'billing::invoices.doc.'; @endphp
<section class="cn">
    <header class="cn-top">
        <div>
            <strong>{{ __($l.'credit_note') }} <span class="cn-alt">{{ __($l.'credit_note_alt') }}</span></strong>
            <div class="cn-muted">{{ __($l.'cancels', ['number' => $note['invoice']]) }}</div>
        </div>
        <div class="cn-no">
            <strong dir="ltr">{{ $note['number'] }}</strong>
            <span class="cn-muted">{{ $note['issued'] }}</span>
        </div>
    </header>
    <div class="cn-body">
        <dl>
            <dt>{{ __($l.'reason') }}</dt><dd>{{ $note['reason'] }}</dd>
            <dt>{{ __($l.'subtotal') }}</dt><dd class="cn-num">{{ $note['subtotal'] }}</dd>
            <dt>{{ __($l.'vat', ['rate' => $note['rate']]) }}</dt><dd class="cn-num">{{ $note['tax'] }}</dd>
            <dt><b>{{ __($l.'total') }}</b></dt><dd class="cn-num"><b>{{ $note['total'] }}</b></dd>
        </dl>
        @if ($note['qr']) @include('billing::invoice.qr', ['qr' => $note['qr']]) @endif
    </div>
</section>
<style>
    .cn { border: 1px dashed var(--gray-300, #d6d3d1); border-radius: .75rem; padding: 1rem; display: flex; flex-direction: column; gap: .8rem; }
    .cn-top { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .6rem; }
    .cn-top strong { font-size: 1rem; }
    .cn-alt { font-weight: 500; color: var(--gray-500, #78716c); font-size: .8rem; margin-inline-start: .35rem; }
    .cn-no { display: flex; flex-direction: column; align-items: flex-end; text-align: end; }
    .cn-muted { color: var(--gray-500, #78716c); font-size: .8rem; }
    .cn-body { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: flex-start; }
    .cn-body dl { margin: 0; display: grid; grid-template-columns: auto auto; gap: .3rem 1.2rem; font-size: .88rem; flex: 1 1 14rem; }
    .cn-body dt { color: var(--gray-500, #78716c); }
    .cn-body dd { margin: 0; text-align: end; }
    .cn-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .dark .cn { border-color: var(--gray-700); }
</style>
