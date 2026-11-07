@php $l = 'platform::plans.academy.'; $t = 'platform::topups.'; $plan = $this->plan; $requests = $plan ? $this->requests : null; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    @if ($plan)
        <div class="pc-stack lms-stagger">
            @include('platform::plans.summary', ['plan' => $plan, 'console' => false])

            {{-- The ways on: an extra pack (on each monthly meter above) or a bigger plan, and what was asked this month. --}}
            <section class="pc-base pc-box pp-requests">
                <h3>
                    <span>{{ __($t.'list.title') }}</span>
                    @if ($requests['upgrade'] === null){{ $this->upgradeAction }}@endif
                </h3>
                <p class="pp-sub">{{ __($t.'list.subtitle') }}</p>
                @if ($requests['upgrade'])
                    <div class="pp-request" data-status="requested">
                        <span><strong>{{ __($t.'list.upgrade') }}</strong>@if ($requests['upgrade']['plan']) · {{ $requests['upgrade']['plan'] }}@endif <small>· {{ $requests['upgrade']['when'] }}</small></span>
                        <span class="pp-tag" data-tone="warn">{{ __($t.'status.requested') }}</span>
                        {{ ($this->cancelUpgradeAction)(['request' => $requests['upgrade']['id']]) }}
                    </div>
                @endif
                @forelse ($requests['topups'] as $r)
                    <div class="pp-request" data-status="{{ $r['status'] }}" wire:key="topup-{{ $r['id'] }}">
                        <span><strong>{{ $r['amount'] }}</strong> · {{ $r['what'] }} <small>· {{ $r['when'] }}</small></span>
                        <span class="pp-tag" data-tone="{{ ['requested' => 'warn', 'active' => 'brand', 'declined' => 'danger', 'cancelled' => ''][$r['status']] }}">{{ $r['status_text'] }}</span>
                        @if ($r['status'] === 'requested'){{ ($this->cancelTopUpAction)(['topup' => $r['id']]) }}@endif
                        @if ($r['reason'])<small class="pp-reason">{{ __($t.'list.reason', ['reason' => $r['reason']]) }}</small>@endif
                    </div>
                @empty
                    @if (! $requests['upgrade'])<p class="pp-sub">{{ __($t.'list.empty') }}</p>@endif
                @endforelse
            </section>
            <p class="pp-ask">{{ __($l.'ask') }}</p>
        </div>
    @endif
</x-filament-panels::page>
