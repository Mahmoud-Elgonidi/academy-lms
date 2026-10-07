@php
    $p = 'billing::payments.page.';
    $outstanding = $invoice->outstanding();
    $status = $invoice->status === 'issued' && $invoice->isOverdue() ? 'overdue' : $invoice->status;
    $tone = ['paid' => 'good', 'void' => 'bad', 'overdue' => 'bad'][$status] ?? 'wait';
@endphp
<x-billing::pay.layout :branding="$branding" :title="__($p.'title', ['number' => $invoice->number])">
    @if ($checkout)
        @php
            $result = match ($checkout->status) { 'paid' => 'good', 'open' => 'wait', 'refund_due' => 'wait', default => 'bad' };
        @endphp
        <div class="banner" data-tone="{{ $result }}" role="status">
            <b>{{ ['good' => '✓', 'wait' => '…', 'bad' => '!'][$result] }}</b>
            <div>
                {{ __($p.'result.'.$checkout->status) }}
                <small>{{ __($p.'result_hint.'.$checkout->status) }}</small>
            </div>
        </div>
        @if ($checkout->status === 'open')
            <script>
                // Confirming with the gateway: look again a few times, then stop.
                (() => { const k = 'pay-wait-{{ $checkout->id }}', n = Number(sessionStorage.getItem(k) || 0); if (n < 12) { sessionStorage.setItem(k, n + 1); setTimeout(() => location.reload(), 5000); } })();
            </script>
        @endif
    @endif

    <section class="card">
        <div class="row">
            <div>
                <span class="muted">{{ __($p.'invoice') }}</span>
                <h1 style="font-size: 1.15rem">{{ $invoice->number }}</h1>
            </div>
            <span class="pill" data-tone="{{ $tone }}">{{ __($p.'statuses.'.$status) }}</span>
        </div>
        <div class="amount">{{ $invoice->status === 'issued' ? $outstanding->format() : $invoice->total()->format() }}</div>
        <dl>
            @if ($student) <dt>{{ __($p.'student') }}</dt><dd>{{ $student }}</dd> @endif
            <dt>{{ __($p.'total') }}</dt><dd>{{ $invoice->total()->format() }}</dd>
            @if ($invoice->paid_minor > 0) <dt>{{ __($p.'paid') }}</dt><dd>{{ \Modules\SharedKernel\Money\Money::of($invoice->paid_minor, $invoice->currency)->format() }}</dd> @endif
            @if ($invoice->status === 'issued' && $invoice->due_at)
                <dt>{{ __($p.'due') }}</dt><dd>{{ $invoice->due_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F Y') }}</dd>
            @endif
        </dl>
        <a class="doc-link" href="{{ $document }}">{{ __('billing::invoices.doc.view') }}</a>
    </section>

    @if ($errors->any())
        <p class="errors" role="alert">{{ $errors->first() }}</p>
    @endif

    @if ($options !== [])
        <section class="card">
            <h2>{{ __($p.'pay_online') }}</h2>
            @foreach ($options as $i => $option)
                <form method="post" action="{{ $option['url'] }}">
                    @csrf
                    <button type="submit" class="pay-btn {{ $i > 0 ? 'secondary' : '' }}">
                        <span>
                            {{ __($p.'pay', ['amount' => $outstanding->format()]) }}
                            <small>{{ $option['account']->label }}</small>
                            <span class="methods">
                                @foreach (\Modules\Billing\Payments\GatewayManager::methods($option['account']->gateway) as $method)
                                    <span>{{ __('billing::payments.methods.'.$method) }}</span>
                                @endforeach
                            </span>
                        </span>
                        <span aria-hidden="true">{{ str_starts_with(app()->getLocale(), 'ar') ? '←' : '→' }}</span>
                    </button>
                </form>
            @endforeach
            <p class="secure"><span aria-hidden="true">🔒</span>{{ __($p.'secure') }}</p>
        </section>
    @endif

    @if ($invoice->status === 'issued' && $manual)
        <section class="card">
            <h2>{{ __($p.'manual') }}</h2>
            <p class="note">{{ $manual }}</p>
        </section>
    @elseif ($invoice->status === 'issued' && $options === [])
        <section class="card"><p class="note" style="margin: 0">{{ __($p.'ask_academy') }}</p></section>
    @endif

    <footer>{{ __($p.'footer') }}</footer>
</x-billing::pay.layout>
