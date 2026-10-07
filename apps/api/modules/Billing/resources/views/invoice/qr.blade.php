{{-- A Saudi tax document's QR code (ZATCA, phase 1), drawn on the server, with what it is. --}}
<figure class="zq">
    <div class="zq-code">{!! $qr !!}</div>
    <figcaption>{{ __('billing::invoices.doc.qr') }}</figcaption>
</figure>
<style>
    .zq { margin: 0; display: flex; flex-direction: column; align-items: center; gap: .35rem; }
    .zq-code { width: 8.5rem; height: 8.5rem; padding: .25rem; background: #fff; border-radius: .5rem; border: 1px solid var(--gray-200, #e7e5e4); }
    .zq-code svg { width: 100%; height: 100%; display: block; }
    .zq figcaption { font-size: .7rem; color: var(--gray-500, #78716c); text-align: center; max-width: 11rem; line-height: 1.4; }
    @media print { .zq-code { border: 0; } }
</style>
