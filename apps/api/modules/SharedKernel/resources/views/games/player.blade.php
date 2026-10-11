{{--
    The screen's side of a game (docs/architecture/02, section 18): opens the game's page in a
    sandboxed frame (allow-scripts only: a unique origin, no cookies, no way to this page) and
    talks to it through postMessage, as resources/games/bridge.js expects. The round comes from
    the element's data (data-items, data-stage, data-locale, data-dir, data-muted), never from
    the game; what the game reports comes back as DOM events on the element:
      lms-game:ready, lms-game:answer {item, correct}, lms-game:finish, lms-game:error {message, line}
    Only items handed to this round are accepted; anything from another window is ignored.
--}}
<script>
    window.LmsGame ??= (() => {
        const mounted = new WeakMap();

        function mount(root) {
            if (! root || mounted.has(root)) return mounted.get(root);
            const items = JSON.parse(root.dataset.items || '[]');
            const ids = new Set(items.map((item) => String(item.id)));
            let frame = null, started = false, muted = root.dataset.muted === '1';

            const emit = (name, detail = {}) => root.dispatchEvent(new CustomEvent('lms-game:' + name, { detail, bubbles: true }));

            function round() {
                return { items, stage: root.dataset.stage || 'kids', locale: root.dataset.locale || 'ar', dir: root.dataset.dir || 'rtl', muted, title: root.dataset.title || '' };
            }

            function load() {
                started = false;
                frame?.remove();
                frame = document.createElement('iframe');
                frame.setAttribute('sandbox', 'allow-scripts');
                frame.setAttribute('referrerpolicy', 'no-referrer');
                frame.setAttribute('title', root.dataset.title || '');
                frame.className = 'lms-game-frame';
                frame.src = root.dataset.src;
                root.appendChild(frame);
            }

            function onMessage(event) {
                if (! frame || event.source !== frame.contentWindow || typeof event.data !== 'string') return;
                let message;
                try { message = JSON.parse(event.data); } catch (e) { return; }
                if (! message || message.lms !== 1) return;
                if (message.type === 'ready' && ! started) {
                    started = true;
                    frame.contentWindow.postMessage(JSON.stringify({ lms: 1, type: 'start', round: round() }), '*');
                    emit('ready');
                } else if (message.type === 'answer' && ids.has(String(message.item))) {
                    emit('answer', { item: String(message.item), correct: message.correct === true });
                } else if (message.type === 'finish') {
                    emit('finish');
                } else if (message.type === 'error') {
                    emit('error', { message: String(message.message || '').slice(0, 300), line: Number(message.line) || 0 });
                }
            }

            window.addEventListener('message', onMessage);
            const api = {
                restart() { load(); },
                mute(on) {
                    muted = !! on;
                    frame?.contentWindow?.postMessage(JSON.stringify({ lms: 1, type: 'mute', muted }), '*');
                },
                destroy() { window.removeEventListener('message', onMessage); frame?.remove(); mounted.delete(root); },
            };
            mounted.set(root, api);
            load();
            return api;
        }

        return { mount, of: (root) => mounted.get(root) };
    })();
</script>
