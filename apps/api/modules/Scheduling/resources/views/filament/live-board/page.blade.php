@php
    $board = $this->board;
    // data-* attributes the browser uses to count minutes on between updates.
    $tick = fn (array $attributes) => new \Illuminate\View\ComponentAttributeBag($attributes);
    $l = 'scheduling::board.';
@endphp

<x-filament-panels::page>
    @include('scheduling::filament.live-board.styles')

    {{-- No timer here: the board refreshes when a change is signalled, or when the clock alone changes it (data-lb-next); see the script below. --}}
    <div class="lb" data-lb-now="{{ $board['serverNow'] }}" data-lb-next="{{ $board['nextChangeAt'] }}" data-lb-tz="{{ $this->viewTz }}" data-lb-channel="{{ $board['channel'] }}">
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
    // Keeps the board current without a timer:
    // - a change on the server (a join, a cancel, a sweep) arrives as a signal on the academy's channel, and the board refreshes once (bursts fold together);
    // - when the clock alone changes the board (a start, a teacher late), it refreshes at that moment (data-lb-next);
    // - minutes counting on are updated here, in the browser;
    // - a hidden tab refreshes when it is shown again, once;
    // - with no live connection (or the relay silent for 2½ minutes) it falls back to every 30 seconds, as before.
    const root = $wire.$el;
    const POLL_MS = 30000, QUIET_MS = 150000, SETTLE_MS = 300;
    let offset = 0, seen = null, next = null, connected = false, everConnected = false, refused = false, lastPulse = Date.now();
    let busy = false, again = false, dirty = false, settle = null, poll = null;
    const now = () => Date.now() + offset;
    const board = () => root.querySelector('.lb');
    const hm = (ms, tz) => new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: tz }).format(ms);

    function refresh() {
        if (document.hidden) { dirty = true; return; }
        if (busy) { again = true; return; }
        busy = true; dirty = false;
        Promise.resolve($wire.$refresh()).finally(() => { busy = false; if (again) { again = false; refresh(); } });
    }
    function changed() { clearTimeout(settle); settle = setTimeout(refresh, SETTLE_MS); }

    function status(el) {
        const state = connected && ! refused && Date.now() - lastPulse < QUIET_MS ? 'on' : ($wire.live && ! refused && ! everConnected ? 'connecting' : 'polling');
        const badge = root.querySelector('[data-lb-live]');
        if (badge) {
            const text = state === 'polling' ? badge.dataset.polling.replace(':time', hm(Number(el.dataset.lbNow), el.dataset.lbTz)) : badge.dataset[state];
            if (badge.dataset.state !== state) badge.dataset.state = state;
            if (badge.textContent !== text) badge.textContent = text;
        }
        if (state === 'on' && poll) { clearInterval(poll); poll = null; }
        if (state !== 'on' && ! poll) poll = setInterval(refresh, POLL_MS);
    }

    function tick() {
        const el = board();
        if (! el) return;
        if (el.dataset.lbNow !== seen) {   // a fresh render: the server's clock, and when the clock next changes the board
            seen = el.dataset.lbNow;
            offset = Number(seen) - Date.now();
            next = el.dataset.lbNext ? Number(el.dataset.lbNext) : null;
        }
        const t = now(), tz = el.dataset.lbTz;
        if (next !== null && t >= next + 300) { next = null; refresh(); }

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
        status(el);
    }

    function listen() {
        if (! $wire.live || ! window.Echo) return;
        const connection = window.Echo.connector.pusher.connection;
        connection.bind('state_change', ({ current }) => {
            connected = current === 'connected';
            if (connected) {
                if (everConnected) changed();   // back after a break: catch up on what was missed
                everConnected = true;
                lastPulse = Date.now();
            }
        });
        connected = everConnected = connection.state === 'connected';
        window.Echo.private(board().dataset.lbChannel).listen('.changed', changed).error(() => { refused = true; });
        window.Echo.channel('lms.live')
            .listen('.alive', () => { lastPulse = Date.now(); })
            .listen('.resync', () => { lastPulse = Date.now(); changed(); });
    }

    if (window.Echo) listen(); else window.addEventListener('EchoLoaded', listen, { once: true });
    document.addEventListener('visibilitychange', () => { if (! document.hidden && dirty) refresh(); });
    const clock = setInterval(() => { if (! document.contains(root)) { clearInterval(clock); clearInterval(poll); return; } tick(); }, 1000);
    tick();
</script>
@endscript
