@php $l = 'platform::plans.academy.'; $plan = $this->plan; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    @if ($plan)
        <div class="pc-stack">
            @include('platform::plans.summary', ['plan' => $plan, 'console' => false])
            <p class="pp-ask">{{ __($l.'ask') }}</p>
        </div>
    @endif
</x-filament-panels::page>
