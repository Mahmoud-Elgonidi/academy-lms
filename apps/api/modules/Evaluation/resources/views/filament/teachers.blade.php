@php $l = 'evaluation::kpis.'; $rows = $this->rows; @endphp
<x-filament-panels::page>
    @include('evaluation::filament.styles')
    <div class="kp-base kp-table-wrap">
        <table class="kp-table">
            <thead>
                <tr>
                    <th>{{ __($l.'teacher') }}</th>
                    @foreach ($this->columns() as $key)<th>{{ __($l.'names.'.$key) }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr wire:key="t-{{ $row['id'] }}">
                        <td>
                            <a href="{{ $row['url'] }}" wire:navigate>{{ $row['name'] }}</a>
                            <span class="kp-score">{{ $row['judged'] > 0 ? __($l.'in_target', ['count' => $row['good'], 'total' => $row['judged']]) : __($l.'not_enough') }}</span>
                        </td>
                        @foreach ($row['cards'] as $c)
                            <td class="kp-cell" data-state="{{ $c['state'] }}" title="{{ $c['hint'] }}@if ($c['target']) · {{ $c['target'] }}@endif">{{ $c['value'] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
