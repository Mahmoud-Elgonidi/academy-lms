{{-- A Quran lesson's shared mushaf on the class page (Learning's, through LearningApi): the teacher turns the pages and marks words; the student (or a guardian beside them) follows. $mushaf: first (the page as the apps read it), urls, echo, channel --}}
@php
    $m = 'classroom::classroom.mushaf.';
    $teaches = $mushaf['first']['role'] === 'teacher';
    $cfg = [
        'first' => $mushaf['first'],
        'urls' => $mushaf['urls'],
        'echo' => $mushaf['echo'],
        'channel' => $mushaf['channel'],
        'words' => ['unmark' => __($m.'unmark'), 'failed' => __($m.'failed'), 'earlier' => __($m.'earlier'), 'page' => __($m.'page')],
    ];
@endphp
<style>
    @font-face { font-family: 'Amiri Quran'; src: url('{{ asset('fonts/quran/AmiriQuran-Regular.ttf') }}') format('truetype'); font-display: swap; }
    .mushaf { display: flex; flex-direction: column; gap: 8px; }
    .mushaf-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .mushaf-head small { color: var(--muted); font-size: 12px; }
    .mushaf-nav { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .mushaf-nav button { padding: 6px 10px; font-size: 13px; }
    .mushaf-nav label { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; color: var(--muted); }
    .mushaf-nav input { width: 4.2em; font: inherit; padding: 5px 6px; border-radius: 8px; border: 1px solid var(--line); background: var(--bg); color: var(--text); }
    .mushaf-hint { margin: 0; font-size: 12px; color: var(--muted); }
    .mushaf-text { background: #fffdf6; color: #1c1917; border-radius: 12px; padding: 14px 16px; font-family: 'Amiri Quran', 'Amiri', serif; font-size: 25px; line-height: 2.3; text-align: justify; min-height: 200px; }
    .mushaf-text .surah { display: block; text-align: center; font-size: 17px; margin: 6px 0 2px; padding: 2px 10px; border-radius: 999px; background: color-mix(in oklab, var(--brand) 18%, #fff); font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif; line-height: 1.8; }
    .mushaf-text .basmala { display: block; text-align: center; }
    .mushaf-text .w { font: inherit; color: inherit; background: none; border: 0; padding: 0 1px; margin: 0; border-radius: 6px; cursor: default; line-height: inherit; }
    .mushaf-text button.w { cursor: pointer; }
    .mushaf-text button.w:hover, .mushaf-text button.w:focus-visible { background: color-mix(in oklab, var(--brand) 16%, transparent); outline: none; }
    .mushaf-text .w[data-mark="wrong"] { background: color-mix(in oklab, var(--danger) 22%, #fff); color: color-mix(in oklab, var(--danger) 80%, #000); }
    .mushaf-text .w[data-mark="hesitation"] { background: #fde68a; color: #78350f; }
    .mushaf-text .w[data-mark="tajweed"] { background: #bfdbfe; color: #1e3a8a; }
    .mushaf-text .w[data-earlier] { text-decoration: underline dotted color-mix(in oklab, var(--danger) 60%, #fff); text-underline-offset: 8px; }
    .mushaf-text .end { color: color-mix(in oklab, var(--brand) 70%, #000); font-size: .8em; }
    .mushaf-legend { display: flex; flex-wrap: wrap; gap: 4px 10px; font-size: 12px; color: var(--muted); }
    .mushaf-legend span::before { content: ''; display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-inline-end: 4px; vertical-align: -1px; }
    .mushaf-legend [data-kind="wrong"]::before { background: color-mix(in oklab, var(--danger) 45%, #fff); }
    .mushaf-legend [data-kind="hesitation"]::before { background: #fde68a; }
    .mushaf-legend [data-kind="tajweed"]::before { background: #bfdbfe; }
    .mushaf-legend [data-kind="earlier"]::before { border-bottom: 2px dotted var(--danger); border-radius: 0; height: 6px; }
    .mushaf-menu { position: fixed; z-index: 20; display: flex; gap: 4px; flex-wrap: wrap; max-width: 280px; padding: 6px; border-radius: 12px; background: var(--panel); border: 1px solid var(--line); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
    .mushaf-menu button { padding: 6px 10px; font-size: 13px; }
    .mushaf-menu button[data-kind="wrong"] { background: color-mix(in oklab, var(--danger) 70%, #000); color: #fff; }
    .mushaf-menu button[data-kind="hesitation"] { background: #b45309; color: #fff; }
    .mushaf-menu button[data-kind="tajweed"] { background: #1d4ed8; color: #fff; }
    .mushaf-source { color: var(--muted); font-size: 11px; }
</style>
<section class="mushaf" id="mushaf" aria-labelledby="mushaf-title">
    <div class="mushaf-head">
        <h2 id="mushaf-title">{{ __($m.'title') }}</h2>
        <small id="mushaf-where"></small>
    </div>
    <div class="mushaf-nav">
        <button type="button" id="mushaf-prev">{{ __($m.'prev') }}</button>
        <label><span>{{ __($m.'page') }}</span><input id="mushaf-page" type="number" min="1" inputmode="numeric"></label>
        <button type="button" id="mushaf-next">{{ __($m.'next') }}</button>
        <button type="button" class="primary" id="mushaf-follow" hidden>{{ __($m.'follow') }}</button>
    </div>
    <p class="mushaf-hint">{{ $teaches ? __($m.'hint_teacher') : __($m.'hint_follow') }}</p>
    <div class="mushaf-text" id="mushaf-text" dir="rtl" lang="ar" aria-live="polite"></div>
    <div class="mushaf-legend" id="mushaf-legend"></div>
    <small class="mushaf-source" id="mushaf-source"></small>
    <p class="err" id="mushaf-error" role="alert" hidden></p>
    <div class="mushaf-menu" id="mushaf-menu" role="menu" hidden></div>
</section>
@if ($mushaf['echo'])
    <script src="{{ asset('js/filament/filament/echo.js') }}"></script>
@endif
<script>
(() => {
    const cfg = {{ Illuminate\Support\Js::from($cfg) }};
    const $ = (id) => document.getElementById(id);
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const digits = new Intl.NumberFormat('ar-EG', { useGrouping: false });
    let state = cfg.first, following = true, room = null, word = null;

    const key = (s, v, w) => s + ':' + v + ':' + w;

    function render() {
        const s = state;
        $('mushaf-page').value = s.page;
        $('mushaf-page').max = s.pages_total;
        $('mushaf-where').textContent = s.title;
        $('mushaf-prev').disabled = s.page <= 1;
        $('mushaf-next').disabled = s.page >= s.pages_total;
        $('mushaf-follow').hidden = s.role === 'teacher' || s.teacher_page === null || s.page === s.teacher_page;
        const marks = new Map(s.marks.map((m) => [key(m.surah, m.verse, m.word), m.kind]));
        const earlier = new Map(s.earlier.map((m) => [key(m.surah, m.verse, m.word), m]));
        const names = new Map(s.surahs.map((x) => [x.number, x.name_ar]));
        const box = $('mushaf-text');
        box.textContent = '';
        for (const v of s.verses) {
            if (v.verse === 1) {
                const head = document.createElement('span');
                head.className = 'surah';
                head.textContent = names.get(v.surah) || '';
                box.append(head);
                if (s.basmala && v.surah !== 1 && v.surah !== 9) {
                    const b = document.createElement('span');
                    b.className = 'basmala';
                    b.textContent = s.basmala.join(' ');
                    box.append(b);
                }
            }
            v.words.forEach((text, i) => {
                const k = key(v.surah, v.verse, i + 1);
                const el = document.createElement(s.can_mark ? 'button' : 'span');
                el.className = 'w';
                el.textContent = text;
                el.dataset.k = k;
                if (s.can_mark) el.type = 'button';
                if (marks.has(k)) {
                    el.dataset.mark = marks.get(k);
                    el.title = s.kinds[marks.get(k)] || '';
                } else if (earlier.has(k)) {
                    el.dataset.earlier = earlier.get(k).kind;
                    el.title = cfg.words.earlier + ' · ' + (s.kinds[earlier.get(k).kind] || '');
                }
                box.append(el, ' ');
            });
            const end = document.createElement('span');
            end.className = 'end';
            end.textContent = '﴿' + digits.format(v.verse) + '﴾';
            box.append(end, ' ');
        }
        const legend = $('mushaf-legend');
        legend.textContent = '';
        for (const [kind, label] of [...Object.entries(s.kinds), ['earlier', cfg.words.earlier]]) {
            const item = document.createElement('span');
            item.dataset.kind = kind;
            item.textContent = label;
            legend.append(item);
        }
        $('mushaf-source').textContent = s.attribution;
    }

    function failed(body) {
        const first = body && body.errors ? Object.values(body.errors)[0] : null;
        $('mushaf-error').textContent = (first && first[0]) || (body && body.message) || cfg.words.failed;
        $('mushaf-error').hidden = false;
    }

    async function load(page) {
        const url = new URL(cfg.urls.show, location.href);
        if (page) url.searchParams.set('page', page);
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (res.ok) { state = (await res.json()).data; render(); }
        } catch (e) {}
    }

    async function change(body) {
        $('mushaf-error').hidden = true;
        try {
            const res = await fetch(cfg.urls.change, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) return failed(json);
            state = json.data;
            render();
            tell();
        } catch (e) { failed(null); }
    }

    // In the room, the others hear at once; the lesson's channel follows a moment later for everyone.
    function tell() {
        if (!room || state.role !== 'teacher') return;
        try { room.localParticipant.publishData(new TextEncoder().encode(JSON.stringify({ v: 1 })), { reliable: true, topic: 'mushaf' }); } catch (e) {}
    }

    function heard() {
        if (state.role === 'teacher') return load(state.page);
        return load(following ? null : state.page);
    }

    function go(page) {
        page = Math.max(1, Math.min(state.pages_total, page | 0));
        if (state.role === 'teacher') return change({ change: 'turn', page });
        following = state.teacher_page !== null && page === state.teacher_page;
        return load(page);
    }

    $('mushaf-prev').addEventListener('click', () => go(state.page - 1));
    $('mushaf-next').addEventListener('click', () => go(state.page + 1));
    $('mushaf-page').addEventListener('change', () => go(parseInt($('mushaf-page').value, 10) || state.page));
    $('mushaf-follow').addEventListener('click', () => { following = true; load(null); });

    // The teacher marks a word: what it was, or the mark off.
    const menu = $('mushaf-menu');
    function closeMenu() { menu.hidden = true; word = null; }
    $('mushaf-text').addEventListener('click', (event) => {
        const el = event.target.closest('button.w');
        if (!el || !state.can_mark) return;
        const [surah, verse, w] = el.dataset.k.split(':').map(Number);
        word = { surah, verse, word: w };
        menu.textContent = '';
        for (const [kind, label] of Object.entries(state.kinds)) {
            const b = document.createElement('button');
            b.type = 'button'; b.setAttribute('role', 'menuitem'); b.dataset.kind = kind; b.textContent = label;
            menu.append(b);
        }
        if (el.dataset.mark) {
            const b = document.createElement('button');
            b.type = 'button'; b.setAttribute('role', 'menuitem'); b.dataset.kind = ''; b.textContent = cfg.words.unmark;
            menu.append(b);
        }
        const r = el.getBoundingClientRect();
        menu.hidden = false;
        menu.style.top = Math.min(window.innerHeight - menu.offsetHeight - 8, r.bottom + 6) + 'px';
        menu.style.left = Math.max(8, Math.min(window.innerWidth - menu.offsetWidth - 8, r.left + r.width / 2 - menu.offsetWidth / 2)) + 'px';
        menu.querySelector('button').focus();
    });
    menu.addEventListener('click', (event) => {
        const b = event.target.closest('button');
        if (!b || !word) return;
        const body = { ...word, page: state.page, change: b.dataset.kind ? 'mark' : 'unmark' };
        if (b.dataset.kind) body.kind = b.dataset.kind;
        closeMenu();
        change(body);
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
    document.addEventListener('click', (event) => { if (!menu.hidden && !menu.contains(event.target) && !event.target.closest('button.w')) closeMenu(); });

    // Inside the room: a word from the teacher (and the teacher alone: the server signs each one's role) says "look again".
    document.addEventListener('lms:room', (event) => {
        room = event.detail;
        if (!room || !window.LivekitClient) return;
        room.on(window.LivekitClient.RoomEvent.DataReceived, (payload, participant, kind, topic) => {
            if (topic !== 'mushaf' || !participant) return;
            let role = null;
            try { role = JSON.parse(participant.metadata || '{}').role; } catch (e) {}
            if (role === 'teacher') heard();
        });
    });

    // Everywhere (also beside Zoom or Meet): the lesson's own channel, signed for whoever has a seat.
    if (cfg.echo && window.EchoFactory) {
        const echo = new window.EchoFactory({
            ...cfg.echo,
            authorizer: (channel) => ({
                authorize: (socketId, callback) => {
                    fetch(cfg.urls.auth, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
                    }).then((r) => r.json()).then((data) => callback(null, data)).catch((e) => callback(e, null));
                },
            }),
        });
        echo.private(cfg.channel).listen('.mushaf.changed', heard);
    }

    render();
})();
</script>
