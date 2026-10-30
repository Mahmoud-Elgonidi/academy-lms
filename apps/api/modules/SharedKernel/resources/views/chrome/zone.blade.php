{{--
    The zone chip in the corner of every panel, for whoever is signed in (ZoneChip),
    and the script that keeps its times ticking: each minute, on the minute, every
    [data-lms-clock] on the page is written again in its zone (Intl, the reader's
    language and clock), and the chip gives a small beat. The browser's zone goes
    to the chip once (the panels never write times on it; the chip only says when
    it keeps another clock than the account's).
--}}
@if (filament()->auth()->check())
    @livewire('shared.zone-chip', [], key('lms-zone'))
    <script>
        window.LmsZone ??= (() => {
            const formats = new Map();
            const write = (zone, locale, cycle) => {
                const key = zone + '|' + locale + '|' + cycle;
                if (! formats.has(key)) formats.set(key, new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: '2-digit', hourCycle: cycle, timeZone: zone }));
                return formats.get(key).format(new Date());
            };

            function tick(beat) {
                document.querySelectorAll('.lms-zone').forEach((chip) => {
                    chip.querySelectorAll('[data-lms-clock]').forEach((el) => {
                        try { el.textContent = write(el.dataset.lmsClock, chip.dataset.locale, chip.dataset.hourCycle); } catch (e) { /* a zone this browser does not know keeps the server's time */ }
                    });
                    const pill = chip.querySelector('.lms-zone-pill');
                    if (beat && pill) {
                        pill.classList.remove('lms-zone-beat');
                        void pill.offsetWidth;
                        pill.classList.add('lms-zone-beat');
                    }
                });
            }

            let timer = null;
            function schedule() {
                clearTimeout(timer);
                const now = new Date();
                timer = setTimeout(() => { tick(true); schedule(); }, 60000 - (now.getSeconds() * 1000 + now.getMilliseconds()) + 40);
            }

            function device() {
                try { return Intl.DateTimeFormat().resolvedOptions().timeZone || null; } catch (e) { return null; }
            }

            function mount(chip, wire) {
                tick(false);
                schedule();
                const zone = device();
                if (zone && chip.dataset.device !== zone) wire.noteDevice(zone);
            }

            // The list of zones, narrowed as the person types: a region with nothing left folds away.
            function filter(list, text) {
                const q = (text || '').trim().toLocaleLowerCase();
                let any = false;
                list.querySelectorAll('[data-region]').forEach((group) => {
                    let shown = 0;
                    group.querySelectorAll('[data-search]').forEach((option) => {
                        const hit = q === '' || option.dataset.search.includes(q);
                        option.hidden = ! hit;
                        if (hit) shown++;
                    });
                    group.hidden = shown === 0;
                    any = any || shown > 0;
                });
                const none = list.querySelector('[data-none]');
                if (none) none.hidden = any;
            }

            // The person hides the chip: it leaves (transform and opacity, only for those who allow motion), then the server keeps the choice.
            function hide(chip, wire) {
                chip.dataset.leaving = '';
                const leaving = chip.getAnimations().filter((a) => a.animationName === 'lms-zone-out');
                return Promise.all(leaving.map((a) => a.finished.catch(() => null))).then(() => wire.hide());
            }

            return { mount, filter, device, hide };
        })();
    </script>
@endif
