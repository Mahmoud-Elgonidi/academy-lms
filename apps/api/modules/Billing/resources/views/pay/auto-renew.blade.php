{{-- A payer's saved card and automatic renewal (J5b), from the signed link: what is saved, what is coming, and one button that turns it off. --}}
@php $c = 'billing::cards.page.'; @endphp
<x-billing::pay.layout :branding="$branding" :title="__($c.'title')">
    <section class="card">
        <h1 style="margin: 0 0 .4rem; font-size: 1.25rem">{{ __($c.'title') }}</h1>

        @if (session('auto_renew_off'))
            <div class="banner" data-tone="good" role="status"><b>✓</b><div>{{ __($c.'turned_off') }}</div></div>
        @endif

        @if ($state['cards'] === [])
            <p>{{ __($c.'no_card') }}</p>
        @else
            @foreach ($state['cards'] as $card)
                <p style="margin: .3rem 0"><b dir="ltr">{{ $card['label'] }}</b> · {{ __($c.'expires', ['month' => sprintf('%02d', $card['exp_month']), 'year' => $card['exp_year']]) }}</p>
            @endforeach
        @endif

        <p style="margin: .7rem 0">{{ $state['auto_renew'] ? __($c.'on') : __($c.'off') }}</p>

        @foreach ($state['coming'] as $charge)
            <p style="margin: .3rem 0">{{ $charge['at'] !== null
                ? __($c.'coming', ['number' => $charge['number'], 'amount' => $charge['amount'], 'at' => $charge['at']['label']])
                : __($c.'under_way', ['number' => $charge['number'], 'amount' => $charge['amount']]) }}</p>
        @endforeach

        @if ($state['auto_renew'] || collect($state['coming'])->whereIn('status', ['scheduled', 'retry'])->isNotEmpty())
            <form method="post" action="{{ $off }}" style="margin-top: 1rem">
                @csrf
                <button type="submit" class="pay-btn secondary"><span>{{ __($c.'turn_off') }}</span></button>
            </form>
            <p class="secure">{{ __($c.'turn_off_hint') }}</p>
        @endif
    </section>
</x-billing::pay.layout>
