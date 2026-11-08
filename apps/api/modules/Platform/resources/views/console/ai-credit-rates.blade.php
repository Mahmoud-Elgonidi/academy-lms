@php $l = 'platform::metering.rates.'; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    @include('platform::plans.usage-styles')
    <section class="pc-base pc-box lms-stagger">
        <div class="mu-table-wrap">
            <table class="mu-table">
                <thead><tr><th>{{ __($l.'capability') }}</th><th>{{ __($l.'rate') }}</th><th>{{ __($l.'default') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($this->rows as $row)
                        <tr wire:key="rate-{{ $row['key'] }}">
                            <td><strong>{{ $row['label'] }}</strong> <small dir="ltr">· {{ $row['key'] }}</small></td>
                            <td>{{ $row['rate'] }}@if ($row['own']) <span class="pp-tag" data-tone="brand">{{ __($l.'own') }}</span>@endif</td>
                            <td><small>{{ $row['default'] }}</small></td>
                            <td class="mu-num">{{ ($this->editAction)(['capability' => $row['key']]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="pp-sub">{{ __($l.'note') }}</p>
    </section>
</x-filament-panels::page>
