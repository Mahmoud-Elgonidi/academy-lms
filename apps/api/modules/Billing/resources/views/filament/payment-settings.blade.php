@php $p = 'billing::payments.'; $s = $p.'settings.'; @endphp
<x-filament-panels::page>
    <style>
        .ps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 22rem), 1fr)); gap: 1rem; }
        .ps-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .7rem; }
        .ps-card[data-off] { opacity: .65; }
        .ps-head { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; }
        .ps-head strong { display: block; font-size: 1rem; }
        .ps-head small, .ps-muted { color: var(--gray-500); font-size: .82rem; }
        .ps-pill { font-size: .72rem; font-weight: 600; padding: .1rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .ps-pill[data-tone="live"] { background: var(--success-50); color: var(--success-700); }
        .ps-pill[data-tone="test"] { background: var(--warning-50); color: var(--warning-800); }
        .ps-methods { display: flex; flex-wrap: wrap; gap: .3rem; }
        .ps-methods span { font-size: .72rem; padding: .05rem .45rem; border-radius: .35rem; border: 1px solid var(--gray-200); color: var(--gray-700); }
        .ps-hook { display: flex; gap: .4rem; align-items: center; background: var(--gray-50); border: 1px dashed var(--gray-300); border-radius: var(--radius-lg, .5rem); padding: .4rem .5rem; }
        .ps-hook code { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .75rem; direction: ltr; text-align: left; }
        .ps-hook button { flex: none; font-size: .75rem; font-weight: 600; color: var(--primary-700); }
        .ps-actions { display: flex; gap: 1rem; flex-wrap: wrap; border-top: 1px solid var(--gray-100); padding-top: .6rem; }
        .ps-empty { padding: 1.5rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
        .ps-h2 { font-size: 1rem; font-weight: 700; margin: 1.5rem 0 .6rem; }
        .ps-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .ps-table th { text-align: start; color: var(--gray-500); font-weight: 500; font-size: .75rem; padding: .4rem .5rem; border-bottom: 1px solid var(--gray-200); }
        .ps-table td { padding: .5rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .ps-table td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ps-status[data-s="paid"] { color: var(--success-700); } .ps-status[data-s="failed"], .ps-status[data-s="refund_due"] { color: var(--danger-700); }
        .ps-status[data-s="open"] { color: var(--warning-700); } .ps-status[data-s="expired"] { color: var(--gray-500); }
        .ps-scroll { overflow-x: auto; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .dark .ps-card, .dark .ps-scroll { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .ps-hook { background: var(--gray-800); border-color: var(--gray-700); }
    </style>

    @if ($this->accounts === [])
        <div class="ps-empty">{{ __($s.'empty') }}</div>
    @else
        <div class="ps-grid lms-stagger">
            @foreach ($this->accounts as ['account' => $account, 'webhook' => $hook])
                <section class="ps-card" @if (! $account->is_active) data-off @endif wire:key="gw-{{ $account->id }}">
                    <div class="ps-head">
                        <div>
                            <strong>{{ $account->label }}</strong>
                            <small>{{ __($p.'gateways.'.$account->gateway) }} · {{ $account->currencies === [] ? (implode(', ', \Modules\Billing\Payments\GatewayManager::currencies($account->gateway) ?? []) ?: __($s.'all_currencies')) : implode(', ', $account->currencies) }}</small>
                        </div>
                        <span class="ps-pill" data-tone="{{ $account->is_active ? $account->mode : 'off' }}">{{ $account->is_active ? __($p.'modes.'.$account->mode) : __($s.'delete') }}</span>
                    </div>
                    <div class="ps-methods">
                        @foreach (\Modules\Billing\Payments\GatewayManager::methods($account->gateway) as $method)
                            <span>{{ __($p.'methods.'.$method) }}</span>
                        @endforeach
                    </div>
                    @if ($account->gateway !== 'demo')
                        <div>
                            <small class="ps-muted">{{ __($s.'webhook') }}</small>
                            <div class="ps-hook" x-data="{ copied: false }">
                                <code title="{{ $hook }}">{{ $hook }}</code>
                                <button type="button" x-on:click="navigator.clipboard.writeText(@js($hook)); copied = true; setTimeout(() => copied = false, 1500)">
                                    <span x-show="! copied">{{ __($s.'copy') }}</span><span x-show="copied" x-cloak>{{ __($s.'copied') }}</span>
                                </button>
                            </div>
                        </div>
                    @endif
                    <small class="ps-muted">{{ __($s.'webhook_hint.'.$account->gateway) }}</small>
                    <div class="ps-actions">
                        {{ ($this->editAction)(['account' => $account->id]) }}
                        {{ ($this->testAction)(['account' => $account->id]) }}
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <h2 class="ps-h2">{{ __($s.'manual_title') }}</h2>
    <form wire:submit="saveManualNote" class="ps-card" style="max-width: 44rem">
        <label for="manual-note" style="font-weight: 600; font-size: .9rem">{{ __($s.'manual_note') }}</label>
        <textarea id="manual-note" wire:model="manualNote" rows="4" maxlength="1000"
                  class="fi-input" style="width: 100%; border: 1px solid var(--gray-300); border-radius: var(--radius-lg, .5rem); padding: .6rem .7rem; font: inherit; background: transparent"></textarea>
        <small class="ps-muted">{{ __($s.'manual_hint') }}</small>
        <div><x-filament::button type="submit" size="sm">{{ __('filament-panels::resources/pages/edit-record.form.actions.save.label') }}</x-filament::button></div>
    </form>

    <h2 class="ps-h2">{{ __($s.'checkouts') }}</h2>
    @if ($this->checkouts === [])
        <div class="ps-empty">{{ __($s.'none_yet') }}</div>
    @else
        <div class="ps-scroll">
            <table class="ps-table">
                <tbody>
                    @foreach ($this->checkouts as $c)
                        <tr wire:key="co-{{ $c->id }}">
                            <td class="num">{{ $c->created_at->setTimezone(app(\Modules\SharedKernel\Time\ViewerTimezone::class)->effective())->format('d/m H:i') }}</td>
                            <td><a href="{{ \Modules\Billing\Filament\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $c->invoice_id]) }}" style="color: var(--primary-700)">{{ $c->number }}</a><br><small class="ps-muted">{{ $c->payer ?? '—' }} · {{ __($p.'channels.'.$c->channel) }}</small></td>
                            <td class="num">{{ $c->money()->format() }}<br><small class="ps-muted">{{ $c->label }}</small></td>
                            <td><span class="ps-status" data-s="{{ $c->status }}">{{ __($p.'statuses.'.$c->status) }}</span>@if ($c->failure)<br><small class="ps-muted">{{ $c->failure }}</small>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
