{{-- Where to send the money by hand, in the academy's words (Queries\TransferDetails): text with its line breaks, never HTML, with a button that copies it; $numbers names the invoices it is for when the payer's go to different places. --}}
<div class="bf-transfer" x-data="{ copied: false }">
    <div class="bf-transfer-head">
        <strong>
            {{ __('billing::transfer.title') }}
            @if (! empty($numbers))<small>· {{ __('billing::transfer.for', ['numbers' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', $numbers)]) }}</small>@endif
        </strong>
        <button type="button" class="bf-copy" x-on:click="navigator.clipboard.writeText($refs.text.textContent).then(() => copied = true)">
            <span x-show="! copied">{{ __('billing::transfer.copy') }}</span><span x-show="copied" x-cloak>{{ __('billing::transfer.copied') }}</span>
        </button>
    </div>
    <p class="bf-transfer-text" dir="auto" x-ref="text">{{ $text }}</p>
</div>
