@php $l = 'payroll::payroll.'; $rows = $this->rows; $tiles = $this->tiles; $state = $this->state; $unpriced = array_sum($this->unpriced); @endphp
<x-filament-panels::page>
    @include('payroll::filament.styles')
    <div class="py-stack">
        @include('payroll::filament.month-bar', ['showWho' => true])

        <div class="py-tiles lms-stagger">
            <div class="py-base py-tile" data-main>
                <small>{{ __($l.'tiles.total') }}</small>
                <span class="py-totals">@forelse ($tiles['totals'] as $total)<strong>{{ $total }}</strong>@empty<strong class="py-muted">—</strong>@endforelse</span>
            </div>
            <div class="py-base py-tile"><small>{{ __($l.'tiles.teachers') }}</small><strong data-count="{{ $tiles['teachers'] }}">{{ $tiles['teachers'] }}</strong></div>
            <div class="py-base py-tile"><small>{{ __($l.'tiles.lessons') }}</small><strong data-count="{{ $tiles['lessons'] }}">{{ $tiles['lessons'] }}</strong></div>
            <div class="py-base py-tile"><small>{{ __($l.'tiles.hours') }}</small><strong>{{ $tiles['hours'] }}</strong></div>
        </div>

        @if ($unpriced > 0)
            <div class="py-note" data-tone="warning">
                <span>{{ trans_choice($l.'unpriced.text', $unpriced, ['count' => $unpriced]) }}</span>
                <span class="py-actions">
                    <x-filament::button size="sm" color="warning" wire:click="priceMissing">{{ __($l.'unpriced.price') }}</x-filament::button>
                    <x-filament::button size="sm" color="gray" tag="a" :href="\Modules\Payroll\Filament\Resources\RateCards\RateCardResource::getUrl()">{{ __($l.'unpriced.rates') }}</x-filament::button>
                </span>
            </div>
        @endif
        @if ($state['pending'] > 0 && ! $state['closed'])
            <div class="py-note" data-tone="info"><span>{{ trans_choice($l.'pending', $state['pending'], ['count' => $state['pending']]) }}</span></div>
        @endif

        <div class="py-base py-table-wrap">
            @if ($rows === [])
                <div class="py-empty-big">
                    <strong>{{ __($l.'empty.title') }}</strong>
                    <p>{{ __($l.'empty.hint') }}</p>
                </div>
            @else
                <table class="py-table">
                    <thead>
                        <tr>
                            @foreach (['teacher', 'lessons', 'hours', 'lesson_pay', 'bonuses', 'deductions', 'total'] as $col)<th>{{ __($l.'columns.'.$col) }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="t-{{ $row['teacher_id'] }}-{{ $row['currency'] }}">
                                <td>
                                    <a href="{{ $row['url'] }}" wire:navigate>{{ $row['name'] }}</a>
                                    @if ($row['unpriced'] > 0)<span class="py-flag">{{ __($l.'unpriced.badge', ['count' => $row['unpriced']]) }}</span>@endif
                                </td>
                                <td>{{ $row['lessons'] }}</td>
                                <td>{{ $row['hours'] }}</td>
                                <td>{{ $row['lesson_pay']?->format() ?? '—' }}</td>
                                <td @class(['py-pos' => $row['bonuses'] && ! $row['bonuses']->isZero(), 'py-muted' => ! $row['bonuses'] || $row['bonuses']->isZero()])>{{ $row['bonuses']?->format() ?? '—' }}</td>
                                <td @class(['py-neg' => $row['deductions'] && ! $row['deductions']->isZero(), 'py-muted' => ! $row['deductions'] || $row['deductions']->isZero()])>{{ $row['deductions']?->format() ?? '—' }}</td>
                                <td class="py-total">{{ $row['total']?->format() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
