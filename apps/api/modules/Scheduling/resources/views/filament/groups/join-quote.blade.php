{{-- The first invoice as it will come out: the plan's price, what comes off (the lessons missed, the family discount, a coupon), what is left. Needs: $quote (PriceQuote). --}}
@php $w = 'scheduling::group_screens.find.join.quote.'; @endphp
<div style="border-radius: var(--radius-xl); border: 1px solid var(--gray-200); padding: .8rem 1rem; display: flex; flex-direction: column; gap: .35rem; font-size: .875rem; font-variant-numeric: tabular-nums" class="gr-quote" role="group" aria-label="{{ __($w.'title') }}">
    <style>
        .gr-quote-row { display: flex; justify-content: space-between; gap: .75rem; }
        .gr-quote-row[data-off] span:last-child { color: var(--success-700); font-weight: 600; }
        .gr-quote-total { display: flex; justify-content: space-between; gap: .75rem; padding-top: .45rem; margin-top: .2rem; border-top: 1px solid var(--gray-200); font-weight: 800; font-size: 1rem; }
        .dark .gr-quote, .dark .gr-quote-total { border-color: var(--gray-700); }
        .dark .gr-quote-row[data-off] span:last-child { color: var(--success-400); }
    </style>
    <strong style="font-size: .8rem; color: var(--gray-500)">{{ __($w.'title') }}</strong>
    <div class="gr-quote-row"><span>{{ __($w.'price') }}</span><bdi>{{ $quote->price->format() }}</bdi></div>
    @foreach ($quote->discounts as $d)
        <div class="gr-quote-row" data-off><span>{{ $d['label'] }}</span><span style="display: inline-flex; gap: .2rem"><span aria-hidden="true">−</span><bdi>{{ $d['amount']->format() }}</bdi></span></div>
    @endforeach
    <div class="gr-quote-total"><span>{{ __($w.'total') }}</span><bdi>{{ $quote->total->format() }}</bdi></div>
</div>
