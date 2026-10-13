{{-- Where to send the money by hand, in the academy's words (Queries\TransferDetails): text with its line breaks, never HTML, and a button that copies it (the layout's script). --}}
<div class="transfer">
    <div class="transfer-head">
        <strong>{{ __('billing::transfer.title') }}</strong>
        <button type="button" class="copy no-print" data-copy data-copied="{{ __('billing::transfer.copied') }}" hidden>{{ __('billing::transfer.copy') }}</button>
    </div>
    <p class="note" dir="auto" data-transfer>{{ $text }}</p>
</div>
