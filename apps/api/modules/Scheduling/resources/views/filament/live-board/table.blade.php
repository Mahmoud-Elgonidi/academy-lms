{{-- Layout "table": what needs you on top, then every session in one list with filters. --}}
@php
    $attention = $board['groups']['attention'];
    $filters = [
        'all' => count($board['all']),
        'live' => count($board['groups']['fine']),
        'problems' => count($attention),
        'upcoming' => count($board['groups']['upcoming']),
        'done' => count($board['groups']['done']),
    ];
    $rows = match ($this->filter) {
        'live' => $board['groups']['fine'],
        'problems' => $attention,
        'upcoming' => $board['groups']['upcoming'],
        'done' => $board['groups']['done'],
        default => $board['all'],
    };
@endphp

@if ($attention !== [])
    <section class="lb-alerts" aria-label="{{ __('scheduling::board.groups.attention') }}">
        <h2 class="lb-h2 lms-heading">{{ __('scheduling::board.needs_you_now') }}</h2>
        <div class="lb-alert-grid">
            @foreach (array_slice($attention, 0, 3) as $item)
                <article class="lb-alert" data-severity="{{ $item['severity'] }}" wire:key="alert-{{ $item['id'] }}">
                    <div class="lb-alert-top">
                        <strong>{{ $item['problem'] }}</strong>
                        @if ($item['minutesLeft'] !== null)
                            <span class="lb-alert-left" {{ $tick($item['leftTick']) }}>{{ __('scheduling::board.left_short', ['min' => $item['minutesLeft']]) }}</span>
                        @endif
                    </div>
                    <p>{{ $item['context'] }}</p>
                    <p class="lb-rule">{{ $item['rule'] }}</p>
                    @if ($item['handled'])
                        <p class="lb-handled">✓ {{ $item['handled'] }}</p>
                    @endif
                    @include('scheduling::filament.live-board.actions', ['item' => $item, 'buttons' => 2])
                </article>
            @endforeach
        </div>
    </section>
@endif

<section class="lb-card">
    <div class="lb-card-head">
        <h2 class="lb-h2 lms-heading">{{ __('scheduling::board.now_and_next') }}</h2>
        <span class="lb-muted" data-tick="window" data-tpl="{{ __('scheduling::board.window', ['from' => ':from', 'to' => ':to', 'city' => $board['city']]) }}">{{ __('scheduling::board.window', ['from' => $board['windowFrom'], 'to' => $board['windowTo'], 'city' => $board['city']]) }}</span>
        <div class="lb-chips" role="group" aria-label="{{ __('scheduling::board.filter') }}">
            @foreach ($filters as $key => $count)
                <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $this->filter === $key ? 'true' : 'false' }}">
                    {{ __('scheduling::board.filters.'.$key) }} <span>{{ $count }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="lb-table" role="table">
        <div class="lb-tr lb-th" role="row">
            <span role="columnheader">{{ __('scheduling::board.cols.time') }}</span>
            <span role="columnheader">{{ __('scheduling::board.cols.teacher') }}</span>
            <span role="columnheader">{{ __('scheduling::board.cols.student') }}</span>
            <span role="columnheader">{{ __('scheduling::board.cols.subject') }}</span>
            <span role="columnheader">{{ __('scheduling::board.cols.progress') }}</span>
            <span role="columnheader">{{ __('scheduling::board.cols.status') }}</span>
            <span role="columnheader">{{ __('scheduling::board.cols.actions') }}</span>
        </div>

        @forelse ($rows as $item)
            @php $open = $this->selected === $item['id']; @endphp
            <div class="lb-tr" role="row" data-issue="{{ $item['issue'] ?? '' }}" wire:key="row-{{ $item['id'] }}">
                <span role="cell">
                    <strong class="lb-num">{{ $item['time'] }}</strong>
                    <small @class(['lb-late' => in_array($item['status']['key'], ['late', 'waiting'])]) {{ $tick($item['timingTick']) }}>{{ $item['timing'] }}</small>
                </span>
                <span role="cell">
                    <strong>{{ $item['teacher'] }}</strong>
                    <small><i class="lb-dot" data-state="{{ $item['teacherDot'] }}"></i>{{ $item['teacherLine'] }}</small>
                </span>
                <span role="cell">
                    <strong>{{ $item['student'] }}</strong>
                    <small><i class="lb-dot" data-state="{{ $item['studentDot'] }}"></i>{{ $item['studentLine'] }}</small>
                </span>
                <span role="cell">
                    {{ $item['subject'] }}
                    @if ($item['typeLabel'])
                        <span class="lb-tag" data-type="{{ $item['type'] }}">{{ $item['typeLabel'] }}</span>
                    @endif
                </span>
                <span role="cell" class="lb-progress-cell">
                    <span class="lb-bar"><span style="width: {{ $item['progress'] }}%" {{ $tick($item['barTick']) }}></span></span>
                    <small {{ $tick($item['result'] ? [] : $item['progressTick']) }}>{{ $item['result'] ?? $item['progressText'] }}</small>
                </span>
                <span role="cell"><span class="lb-pill" data-status="{{ $item['status']['key'] }}">{{ $item['status']['label'] }}</span></span>
                <span role="cell" class="lb-row-actions">
                    @include('scheduling::filament.live-board.actions', ['item' => $item, 'buttons' => 1])
                    <button type="button" class="lb-expand" wire:click="select('{{ $item['id'] }}')" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-label="{{ __('scheduling::board.details') }}">
                        <x-filament::icon :icon="$open ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down'" class="lb-icon" />
                    </button>
                </span>
            </div>
            @if ($open)
                <div class="lb-details" role="row" wire:key="details-{{ $item['id'] }}">
                    @include('scheduling::filament.live-board.details', ['item' => $item])
                </div>
            @endif
        @empty
            <p class="lb-muted lb-pad">{{ __('scheduling::board.filter_empty') }}</p>
        @endforelse
    </div>
</section>
