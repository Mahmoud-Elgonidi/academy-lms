@php
    $l = 'scheduling::calendar.';
    $board = $this->board;
    $counts = $this->counts;
    $range = $this->range;
    $filter = $this->filter();
    $narrowed = ! $filter->isEmpty();
@endphp

<x-filament-panels::page>
    @include('scheduling::filament.calendar.styles')

    {{-- No timer: the calendar refreshes when a lesson changes (the live board's signal) and at midnight on the viewer's clock (data-live-next). --}}
    <div class="cal" data-live-now="{{ $board['serverNow'] }}" data-live-next="{{ $board['nextChangeAt'] }}" data-live-channel="{{ $board['channel'] }}" data-cal-tz="{{ $range->timezone }}">
        <div class="cal-card cal-bar">
            <div class="cal-nav">
                <button type="button" class="cal-icon-btn" wire:click="go(-1)" aria-label="{{ __($l.'previous') }}" title="{{ __($l.'previous') }}">
                    <x-filament::icon icon="heroicon-m-chevron-right" class="cal-icon cal-flip" />
                </button>
                <button type="button" class="cal-today" wire:click="today">{{ __($l.'today') }}</button>
                <button type="button" class="cal-icon-btn" wire:click="go(1)" aria-label="{{ __($l.'next') }}" title="{{ __($l.'next') }}">
                    <x-filament::icon icon="heroicon-m-chevron-left" class="cal-icon cal-flip" />
                </button>
                <h2 class="cal-range lms-heading">{{ $board['label'] }}</h2>
            </div>

            <div class="cal-tools">
                <div class="cal-seg" role="group" aria-label="{{ __($l.'units.week') }}">
                    @foreach (\Modules\Scheduling\Queries\Calendar\CalendarRange::UNITS as $unit)
                        <button type="button" wire:click="setUnit('{{ $unit }}')" aria-pressed="{{ $range->unit === $unit ? 'true' : 'false' }}">{{ __($l.'units.'.$unit) }}</button>
                    @endforeach
                </div>
                <div class="cal-seg" role="group" aria-label="{{ __($l.'displays.calendar') }}">
                    @foreach (\Modules\Scheduling\Filament\Pages\AcademyCalendar::DISPLAYS as $display)
                        <button type="button" wire:click="setDisplay('{{ $display }}')" aria-pressed="{{ $this->display === $display ? 'true' : 'false' }}">{{ __($l.'displays.'.$display) }}</button>
                    @endforeach
                </div>
                <label class="cal-zone">
                    <x-filament::icon icon="heroicon-o-globe-alt" class="cal-icon" />
                    <span class="cal-sr">{{ __($l.'zone') }}</span>
                    <select wire:change="setZone($event.target.value)" aria-label="{{ __($l.'zone') }}">
                        @foreach ($board['zones'] as $zone)
                            <option value="{{ $zone['id'] }}" @selected($zone['id'] === $range->timezone)>{{ $zone['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        <div class="cal-card cal-filters" aria-label="{{ __($l.'filters.label') }}">
            <div class="cal-filters-form">{{ $this->filtersForm }}</div>
            @if ($narrowed)
                <button type="button" class="cal-clear" wire:click="clearFilters">
                    <x-filament::icon icon="heroicon-m-x-mark" class="cal-icon" />{{ __($l.'filters.clear') }}
                </button>
            @endif
        </div>

        {{-- The range's lessons by status: each chip narrows to it (more than one may be on). --}}
        <div class="cal-sum">
            <div class="cal-totals">
                <strong class="lms-heading">{{ trans_choice($l.'totals.lessons', $counts['lessons'], ['count' => $counts['lessons']]) }}</strong>
                <span>{{ __($l.'totals.hours', ['hours' => $counts['hours']]) }}</span>
                @if ($counts['cancelled'] > 0)
                    <span class="cal-muted">{{ __($l.'totals.cancelled', ['count' => $counts['cancelled']]) }}</span>
                @endif
            </div>
            <div class="cal-chips" role="group" aria-label="{{ __($l.'totals.hint') }}" title="{{ __($l.'totals.hint') }}">
                <button type="button" class="cal-chip" data-all wire:click="clearStatuses" aria-pressed="{{ $this->statuses === [] ? 'true' : 'false' }}">{{ __($l.'totals.all') }}</button>
                @foreach ($counts['statuses'] as $chip)
                    <button type="button" class="cal-chip" data-status="{{ $chip['status'] }}" wire:click="toggleStatus('{{ $chip['status'] }}')"
                            aria-pressed="{{ $chip['on'] ? 'true' : 'false' }}" @if ($chip['count'] === 0 && ! $chip['on']) data-zero @endif>
                        <span class="cal-dot" data-status="{{ $chip['status'] }}"></span>{{ $chip['label'] }} <b>{{ $chip['count'] }}</b>
                    </button>
                @endforeach
            </div>
        </div>

        @switch ($board['kind'])
            @case('week')
                @include('scheduling::filament.calendar.week', ['board' => $board, 'filter' => $filter])
                @break
            @case('day')
                @include('scheduling::filament.calendar.day', ['board' => $board, 'filter' => $filter])
                @break
            @case('month')
                @include('scheduling::filament.calendar.month', ['board' => $board])
                @break
            @case('list')
                @include('scheduling::filament.calendar.agenda', ['days' => $board['days'], 'filter' => $filter, 'phoneOnly' => false])
                @if ($board['more'])
                    <p class="cal-note">{{ __($l.'list.more', ['limit' => $board['limit']]) }}</p>
                @endif
                @break
            @default
                @include('scheduling::filament.calendar.summary', ['board' => $board])
        @endswitch
    </div>

</x-filament-panels::page>

@script
<script>
    // New lessons, moves and statuses arrive on the academy's lesson signal (LmsLive, shared/live.blade.php);
    // midnight on the viewer's clock moves "today". The now line moves here, in the browser, every half minute.
    const el = $wire.$el.querySelector('.cal');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 60000 });
    }
    const minuteOfDay = (tz) => {
        const [h, m] = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: tz }).format(Date.now()).split(':').map(Number);
        return h * 60 + m;
    };
    const moveNow = () => {
        if (! document.contains(el)) { clearInterval(timer); return; }
        const minute = minuteOfDay(el.dataset.calTz);
        el.querySelectorAll('[data-cal-now]').forEach((n) => n.style.setProperty('--m', minute));
    };
    const timer = setInterval(moveNow, 30000);
</script>
@endscript
