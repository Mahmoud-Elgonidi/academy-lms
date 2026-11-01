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
        @if ($summary->lines !== [])
            <h2 style="font-size: .95rem; margin: 1rem 0 .4rem">{{ __($p.'lines') }}</h2>
            <dl class="lines">
                @foreach ($summary->lines as $line)
                    <dt>{{ $line['description'] }}</dt>
                    <dd @if ($line['amount_minor'] < 0) class="good" @endif dir="ltr">{{ \Modules\SharedKernel\Money\Money::of($line['amount_minor'], $invoice->currency)->format() }}</dd>
                @endforeach
                @if ($summary->taxMinor > 0 || $summary->taxRateBp > 0)
                    @unless ($summary->pricesIncludeTax)
                        <dt>{{ __($p.'subtotal') }}</dt><dd dir="ltr">{{ \Modules\SharedKernel\Money\Money::of($summary->subtotalMinor, $invoice->currency)->format() }}</dd>
                    @endunless
                    <dt>{{ __($p.($summary->pricesIncludeTax ? 'vat_included' : 'vat'), ['rate' => rtrim(rtrim(number_format($summary->taxRateBp / 100, 2, '.', ''), '0'), '.')]) }}</dt>
                    <dd dir="ltr">{{ \Modules\SharedKernel\Money\Money::of($summary->taxMinor, $invoice->currency)->format() }}</dd>
                @endif
            </dl>
        @endif
        <a class="doc-link" href="{{ $document }}">{{ __('billing::invoices.doc.view') }}</a>
    </section>

    @if (session('coupon_done'))
        <div class="banner" data-tone="good" role="status"><b>✓</b><div>{{ session('coupon_done') }}</div></div>
    @endif
    @if ($summary->coupon !== null && $invoice->status === 'issued')
        <section class="card">
            <p class="note" style="margin: 0">{{ __($p.'coupon.'.($summary->coupon['personal'] ? 'offer' : 'on'), ['code' => $summary->coupon['code']]) }}</p>
            @if ($couponRemoveUrl)
                <form method="post" action="{{ $couponRemoveUrl }}" style="margin-top: .6rem">
                    @csrf
                    <button type="submit" class="pay-btn secondary"><span>{{ __($p.'coupon.remove') }}</span></button>
                </form>
            @endif
        </section>
    @elseif ($couponUrl)
        <section class="card">
            <details @if ($errors->has('coupon') || $errors->has('code')) open @endif>
                <summary class="pay-btn secondary"><span>{{ __($p.'coupon.title') }}<small>{{ __($p.'coupon.hint') }}</small></span></summary>
                <form method="post" action="{{ $couponUrl }}">
                    @csrf
                    <label class="field"><span>{{ __($p.'coupon.label') }}</span>
                        <input type="text" name="code" maxlength="32" dir="ltr" autocomplete="off" autocapitalize="characters" required value="{{ old('code') }}">
                    </label>
                    <button type="submit" class="pay-btn"><span>{{ __($p.'coupon.apply') }}</span></button>
                </form>
            </details>
        </section>
    @endif

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

    @if (session('proof_sent'))
        <div class="banner" data-tone="good" role="status"><b>✓</b><div>{{ __('billing::proofs.send.sent') }}</div></div>
    @endif

    @if ($invoice->status === 'issued' && $manual)
        @php $s = 'billing::proofs.send.'; @endphp
        <section class="card">
            <h2>{{ __($p.'manual') }}</h2>
            @include('billing::pay.transfer', ['text' => $manual])
            @if ($proof && ($proof->status !== 'rejected' || $proofUrl))
                <p class="proof" data-s="{{ $proof->status }}" role="status">{{ __('billing::proofs.state.'.$proof->status, [
                    'amount' => $proof->money()->format(),
                    'date' => $proof->created_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F'),
                    'reason' => $proof->decision_note,
                ]) }}</p>
            @endif
            @if ($proofUrl && $proof?->status !== 'pending')
                <details class="proof-form" @if ($errors->any()) open @endif>
                    <summary class="pay-btn secondary">
                        <span>{{ __($s.'action') }}<small>{{ __($s.'hint') }}</small></span>
                        <span aria-hidden="true">{{ str_starts_with(app()->getLocale(), 'ar') ? '←' : '→' }}</span>
                    </summary>
                    <form method="post" action="{{ $proofUrl }}" enctype="multipart/form-data">
                        @csrf
                        <h3>{{ __($s.'title') }}</h3>
                        <fieldset class="chips">
                            <legend>{{ __($s.'method') }}</legend>
                            @foreach (\Modules\Billing\Models\PaymentProof::METHODS as $method)
                                <label><input type="radio" name="method" value="{{ $method }}" @checked(old('method', 'bank_transfer') === $method) required><span>{{ __('billing::billing.methods.'.$method) }}</span></label>
                            @endforeach
                        </fieldset>
                        <div class="pair">
                            <label class="field"><span>{{ __($s.'amount') }} ({{ $invoice->currency }})</span>
                                <input type="number" name="amount" inputmode="decimal" step="any" min="0" required value="{{ old('amount', $outstanding->decimal()) }}">
                                @error('amount') <small class="bad">{{ $message }}</small> @enderror
                            </label>
                            <label class="field"><span>{{ __($s.'paid_on') }}</span>
                                <input type="date" name="paid_on" required max="{{ $today }}" value="{{ old('paid_on', $today) }}">
                                @error('paid_on') <small class="bad">{{ $message }}</small> @enderror
                            </label>
                        </div>
                        <label class="field"><span>{{ __($s.'reference') }}</span>
                            <input type="text" name="reference" maxlength="120" dir="auto" placeholder="{{ __($s.'reference_hint') }}" value="{{ old('reference') }}">
                        </label>
                        <label class="field"><span>{{ __($s.'file') }}</span>
                            <input type="file" name="file" required accept="image/*,application/pdf">
                            <small @error('file') class="bad" @enderror>@error('file'){{ $message }}@else{{ __($s.'file_hint') }}@enderror</small>
                        </label>
                        <label class="field"><span>{{ __($s.'note') }}</span>
                            <textarea name="note" rows="2" maxlength="500" placeholder="{{ __($s.'note_hint') }}">{{ old('note') }}</textarea>
                        </label>
                        <button type="submit" class="pay-btn"><span>{{ __($s.'submit') }}</span><span aria-hidden="true">✓</span></button>
                    </form>
                </details>
            @endif
        </section>
    @elseif ($invoice->status === 'issued' && $options === [])
        <section class="card"><p class="note" style="margin: 0">{{ __($p.'ask_academy') }}</p></section>
    @endif

    <footer>{{ __($p.'footer') }}</footer>
</x-billing::pay.layout>
