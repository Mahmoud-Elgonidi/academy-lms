{{-- One live connection for every live part of the page (the live board, the bell, today's lessons). --}}
<script>
    window.LmsLive ??= (() => {
        const QUIET_MS = 150000;   // no "alive" from the relay for this long: treat the connection as down
        const watchers = new Set();
        let connected = false, everConnected = false, lastPulse = Date.now(), started = false;

        const connection = () => {
            if (! window.Echo) return 'polling';
            if (connected && Date.now() - lastPulse < QUIET_MS) return 'on';
            return everConnected ? 'polling' : 'connecting';
        };

        function start() {
            if (started || ! window.Echo) return;
            started = true;
            const socket = window.Echo.connector.pusher.connection;
            socket.bind('state_change', ({ current }) => {
                connected = current === 'connected';
                if (connected) {
                    lastPulse = Date.now();
                    if (everConnected) watchers.forEach((w) => w.changed());   // back after a break: catch up once
                    everConnected = true;
                }
            });
            connected = everConnected = socket.state === 'connected';
            window.Echo.channel('lms.live')
                .listen('.alive', () => { lastPulse = Date.now(); })
                .listen('.resync', () => { lastPulse = Date.now(); watchers.forEach((w) => w.changed()); });
            watchers.forEach((w) => w.subscribe());
        }

        /**
         * Keeps one part of the page current, asking the server only when there is something new:
         * - refresh() when `event` arrives on the private `channel` (a burst folds into one, after `settle` ms);
         * - refresh() when the clock reaches el's data-live-next (server time; data-live-now is the server's clock at render);
         * - a hidden tab waits, and refreshes once when it is shown again;
         * - every `every` ms instead while there is no live connection, as before.
         */
        function watch({ el, channel, event, refresh, every = 30000, settle = 300, onState = () => {} }) {
            let busy = false, again = false, dirty = false, settleTimer = null, poll = null, refused = false, listening = false;
            let seen, offset = 0, next = null, shown = null;
            const w = {
                now: () => Date.now() + offset,
                state: () => (refused ? 'polling' : connection()),
                run() {
                    if (document.hidden) { dirty = true; return; }
                    if (busy) { again = true; return; }
                    busy = true; dirty = false;
                    Promise.resolve(refresh()).finally(() => { busy = false; if (again) { again = false; w.run(); } });
                },
                changed() { clearTimeout(settleTimer); settleTimer = setTimeout(w.run, settle); },
                subscribe() {
                    if (listening || ! window.Echo || ! channel) return;
                    listening = true;
                    window.Echo.private(channel).listen(event, w.changed).error(() => { refused = true; });
                },
                tick() {
                    if (! document.contains(el)) return w.stop();
                    if (el.dataset.liveNow !== seen) {   // a fresh render
                        seen = el.dataset.liveNow;
                        offset = seen ? Number(seen) - Date.now() : 0;
                        next = el.dataset.liveNext ? Number(el.dataset.liveNext) : null;
                    }
                    if (next !== null && w.now() >= next + 300) { next = null; w.run(); }
                    const state = w.state();
                    if (state !== shown) { shown = state; onState(state); }
                    if (state === 'on' && poll) { clearInterval(poll); poll = null; }
                    if (state !== 'on' && ! poll) poll = setInterval(w.run, every);
                },
                stop() {
                    clearInterval(clock); clearInterval(poll); clearTimeout(settleTimer);
                    document.removeEventListener('visibilitychange', onVisible);
                    if (listening && window.Echo) window.Echo.private(channel).stopListening(event, w.changed);
                    watchers.delete(w);
                },
            };
            const onVisible = () => { if (! document.hidden && dirty) w.run(); };
            document.addEventListener('visibilitychange', onVisible);
            const clock = setInterval(w.tick, 1000);
            watchers.add(w);
            w.subscribe();
            w.tick();

            return w;
        }

        if (window.Echo) start(); else window.addEventListener('EchoLoaded', start, { once: true });

        return { watch };
    })();
</script>
