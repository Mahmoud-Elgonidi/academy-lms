@php
    $board = $this->board;
    // data-* attributes the browser uses to count minutes on between updates.
    $tick = fn (array $attributes) => new \Illuminate\View\ComponentAttributeBag($attributes);
    $l = 'scheduling::board.';
@endphp

<x-filament-panels::page>
    @include('scheduling::filament.live-board.styles')

    {{-- No timer here: the board refreshes when a change is signalled, or when the clock alone changes it (data-live-next); see the script below. --}}
    <div class="lb" data-live-now="{{ $board['serverNow'] }}" data-live-next="{{ $board['nextChangeAt'] }}" data-lb-tz="{{ $this->viewTz }}" data-live-channel="{{ $board['channel'] }}">
        <header class="lb-head">
            <div>
                <h1 class="lb-title lms-heading">{{ __('scheduling::board.title') }}</h1>
                <p class="lb-sub">
                    <span class="lb-live" data-lb-live data-state="{{ $this->live ? 'connecting' : 'polling' }}" wire:ignore
                          data-on="{{ __($l.'live.on') }}" data-connecting="{{ __($l.'live.connecting') }}" data-polling="{{ __($l.'subtitle', ['time' => ':time']) }}">{{ $this->live ? __($l.'live.connecting') : __($l.'subtitle', ['time' => $board['now']]) }}</span>
                </p>
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
                    <span>{{ $board['today'] }} · <strong data-tick="clock">{{ $board['now'] }}</strong> · {{ __('scheduling::board.in_zone') }}</span>
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

@script
<script>
    // The board asks the server only when there is something new (LmsLive, shared/live.blade.php):
    // a change signalled on the academy's channel, or the moment the clock alone changes it (a start, a teacher late).
    // Minutes counting on are kept here, in the browser. With no live connection it refreshes every 30 seconds, and says so.
    const el = $wire.$el.querySelector('.lb');
    const badge = $wire.$el.querySelector('[data-lb-live]');
    const hm = (ms, tz) => new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: tz }).format(ms);
    const live = window.LmsLive
        ? window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 30000 })
        : { now: () => Date.now(), state: () => 'polling', poll: setInterval(() => $wire.$refresh(), 30000) };

    function tick() {
        if (! document.contains(el)) { clearInterval(clock); return; }
        const t = live.now(), tz = el.dataset.lbTz;
        el.querySelectorAll('[data-tick]').forEach((n) => {
            const d = n.dataset, at = Number(d.at) * 1000;
            const done = () => Math.max(0, Math.floor((Math.min(t, Number(d.to) * 1000) - Number(d.from) * 1000) / 60000));
            let text = null;
            switch (d.tick) {
                case 'until': text = d.tpl.replace(':min', Math.max(0, Math.ceil((at - t) / 60000))); break;
                case 'since': text = d.tpl.replace(':min', Math.max(0, Math.floor((t - at) / 60000))); break;
                case 'done': text = d.tpl.replace(':done', done()); break;
                case 'bar': n.style.width = Math.min(100, Math.round(done() * 100 / Number(d.total))) + '%'; break;
                case 'clock': text = hm(t, tz); break;
                case 'window': text = d.tpl.replace(':from', hm(t - 3600000, tz)).replace(':to', hm(t + 3600000, tz)); break;
            }
            if (text !== null && n.textContent !== text) n.textContent = text;
        });
        const state = live.state();
        const text = state === 'polling' ? badge.dataset.polling.replace(':time', hm(Number(el.dataset.liveNow), tz)) : badge.dataset[state];
        if (badge.dataset.state !== state) badge.dataset.state = state;
        if (badge.textContent !== text) badge.textContent = text;
    }
    const clock = setInterval(tick, 1000);
    tick();
</script>
@endscript
