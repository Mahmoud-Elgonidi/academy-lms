@php
    $board = $this->board;
@endphp

<x-filament-panels::page>
    @include('scheduling::filament.live-board.styles')

    <div class="lb" wire:poll.30s>
        <header class="lb-head">
            <div>
                <h1 class="lb-title lms-heading">{{ __('scheduling::board.title') }}</h1>
                <p class="lb-sub">{{ __('scheduling::board.subtitle', ['time' => $board['now']]) }}</p>
            </div>

            <div class="lb-head-tools">
                <div class="lb-seg" role="group" aria-label="{{ __('scheduling::board.layout.label') }}" title="{{ __('scheduling::board.layout.hint') }}">
                    <span class="lb-seg-label">{{ __('scheduling::board.layout.label') }}</span>
                    @foreach (\Modules\Scheduling\Filament\Pages\LiveBoard::LAYOUTS as $layout)
                        <button type="button" wire:click="setLayout('{{ $layout }}')" aria-pressed="{{ $this->boardLayout === $layout ? 'true' : 'false' }}">
                            {{ __('scheduling::board.layout.'.$layout) }}
                        </button>
                    @endforeach
                </div>

                <label class="lb-tz">
                    <x-filament::icon icon="heroicon-o-clock" class="lb-icon" />
                    <span>{{ $board['today'] }} · <strong>{{ $board['now'] }}</strong> · {{ __('scheduling::board.in_zone') }}</span>
                    <select wire:change="setViewTimezone($event.target.value)" aria-label="{{ __('scheduling::board.zone_label') }}">
                        @foreach ($board['zones'] as $zone)
                            <option value="{{ $zone['id'] }}" @selected($zone['id'] === $this->viewTz)>{{ $zone['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </header>

        <section class="lb-kpis" aria-label="{{ __('scheduling::board.kpis.label') }}">
            @foreach ($board['kpis'] as $kpi)
                <div class="lb-kpi" data-tone="{{ $kpi['tone'] }}">
                    <span class="lb-kpi-label">{{ $kpi['label'] }}</span>
                    <strong class="lb-kpi-value lms-heading">{{ $kpi['value'] }}</strong>
                    <span class="lb-kpi-hint">{{ $kpi['hint'] }}</span>
                </div>
            @endforeach
        </section>

        @if ($board['all'] === [])
            <div class="lb-empty">
                <x-filament::icon icon="heroicon-o-moon" class="lb-empty-icon" />
                <strong>{{ __('scheduling::board.empty.title') }}</strong>
                <span>{{ __('scheduling::board.empty.hint') }}</span>
            </div>
        @elseif ($this->boardLayout === 'focus')
            @include('scheduling::filament.live-board.focus', ['board' => $board])
        @else
            @include('scheduling::filament.live-board.table', ['board' => $board])
        @endif
    </div>
</x-filament-panels::page>
