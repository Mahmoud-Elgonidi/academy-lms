{{--
    The islands world's behaviour (the approved design), for Alpine: LmsIslands.world() is
    the map (zoom into an island, its circle reveal, the sheet, locked islands wobbling with a
    toast, music by island), LmsIslands.hatch(config) an egg that hatches after three taps. The
    rounds themselves go to Livewire ($wire.play), which checks everything again.
--}}
<script>
    window.LmsIslands ??= (() => {
        const sound = () => window.LmsIslandsSound;
        const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /** Scale a 390-wide picture to its box (--gw-s) and the opened island to the screen (--gw-ps). */
        function fit(root) {
            const map = root.querySelector('.gw-map');
            if (map) map.style.setProperty('--gw-s', String(Math.min(map.clientWidth, 520) / 390));
            const ps = Math.min(window.innerWidth, 480) / 390;
            root.style.setProperty('--gw-ps', String(ps));
            root.style.setProperty('--gw-hs', String(Math.min(window.innerWidth / 390, window.innerHeight / 844)));
        }

        /** The world's live settings (they change with each render), from the page itself. */
        function settings(root) {
            try { return JSON.parse(root.querySelector('[data-gw-config]')?.dataset.gwConfig || '{}'); } catch (e) { return {}; }
        }

        function world() {
            let config = { islands: {} };
            let root = null;   // the component's element, kept outside Alpine's reactive data
            return {
                view: 'map', id: null, zoom: false, reveal: false, wob: '', toast: '', muted: false,
                rx: 195, ry: 300, timers: [], tt: null,

                init() {
                    this.muted = sound()?.muted() ?? false;
                    root = this.$el;
                    config = settings(root);
                    // Livewire renders the page again after every round: read the settings again, keep where the child is.
                    this.mo = new MutationObserver(() => { config = settings(root); });
                    this.mo.observe(root, { attributes: true, subtree: true, attributeFilter: ['data-gw-config'] });
                    fit(root);
                    this.ro = new ResizeObserver(() => fit(root));
                    this.ro.observe(root);
                    window.addEventListener('resize', this.onResize = () => fit(root));
                    document.addEventListener('lms-games:muted', this.onMuted = (e) => { this.muted = e.detail.muted; });
                    document.addEventListener('keydown', this.onKey = (e) => { if (e.key === 'Escape' && this.view === 'island' && ! document.querySelector('.gw-play, .gw-done, .gw-hatch')) this.back(); });
                    if (config.open) this.$nextTick(() => this.open(config.open, true));
                },
                destroy() {
                    this.timers.forEach(clearTimeout);
                    this.ro?.disconnect();
                    this.mo?.disconnect();
                    window.removeEventListener('resize', this.onResize);
                    document.removeEventListener('lms-games:muted', this.onMuted);
                    document.removeEventListener('keydown', this.onKey);
                    document.documentElement.style.overflow = '';
                    sound()?.stop(true);
                },
                later(fn, ms) { this.timers.push(setTimeout(fn, still() ? 0 : ms)); },
                island() { return config.islands[this.id] || null; },

                worldStyle() {
                    const i = this.island();
                    if (! this.zoom || ! i) return 'transform: translate(0px, 0px) scale(1)';
                    return `transform-origin: ${i.cx}px ${i.cy}px; transform: translate(${195 - i.cx}px, ${300 - i.cy}px) scale(2.7)`;
                },
                openStyle() {
                    const r = this.reveal ? Math.hypot(window.innerWidth, window.innerHeight) * 1.2 : 0;
                    return `clip-path: circle(${r}px at ${this.rx}px ${this.ry}px)`;
                },

                tap(key) {
                    const i = config.islands[key];
                    if (! i) return;
                    if (! i.open) return this.locked(key, i.why);
                    this.open(key);
                },
                open(key, quiet) {
                    if (this.view !== 'map') return;
                    const i = config.islands[key];
                    if (! quiet) {
                        sound()?.start();
                        sound()?.play('zoom');
                        this.later(() => sound()?.cue(i.music), 650);
                    }
                    sound()?.scale(i.music);
                    this.id = key; this.zoom = true; this.view = 'zooming'; this.toast = '';
                    this.later(() => {
                        // The circle opens where the island now is on the screen.
                        const stage = root.querySelector('.gw-stage');
                        const box = stage?.getBoundingClientRect();
                        const s = box ? box.width / 390 : 1;
                        this.rx = box ? box.left + 195 * s : window.innerWidth / 2;
                        this.ry = box ? box.top + 300 * s : window.innerHeight / 2;
                        this.view = 'island'; this.reveal = false;
                        document.documentElement.style.overflow = 'hidden';
                        this.later(() => { this.reveal = true; this.$nextTick(() => root.querySelector(`[data-phone="${this.id}"] .gw-back`)?.focus({ preventScroll: true })); }, 40);
                    }, quiet ? 0 : 560);
                },
                back() {
                    if (this.view !== 'island') return;
                    sound()?.play('back');
                    sound()?.scale('map');
                    this.reveal = false;
                    this.later(() => {
                        this.view = 'map'; this.zoom = false;
                        document.documentElement.style.overflow = '';
                        this.$nextTick(() => root.querySelector(`[data-island="${this.id}"]`)?.focus({ preventScroll: true }));
                    }, 520);
                },
                locked(key, text) {
                    sound()?.start();
                    sound()?.play('locked');
                    this.wob = '';
                    this.later(() => { this.wob = key; }, 20);
                    this.later(() => { this.wob = ''; }, 600);
                    this.say(text);
                },
                say(text) {
                    this.toast = '';
                    this.later(() => { this.toast = text; }, 30);
                    clearTimeout(this.tt);
                    this.tt = setTimeout(() => { this.toast = ''; }, 3200);
                    this.timers.push(this.tt);
                },
                bob(key, d) { return this.wob === key ? 'gw-wobble' : 'gw-bob ' + (d || ''); },
                toggleSound() {
                    const s = sound();
                    if (! s) return;
                    if (s.muted()) s.setMuted(false);
                    else if (! s.playing()) s.start();
                    else s.setMuted(true);
                    this.muted = s.muted();
                },
                play(game, quest, playable, why) {
                    if (! config.may) { sound()?.play('locked'); return this.say(config.refusal); }
                    if (! playable) { sound()?.play('locked'); return this.say(why); }
                    sound()?.play('pop');
                    sound()?.stop(false);
                    this.$wire.play(game, quest);
                },
            };
        }

        function hatch(config) {
            let root = null;
            return {
                taps: 0, knocking: false, hatched: false, name: 0, saving: false, timers: [],
                init() { root = this.$el; fit(root); window.addEventListener('resize', this.onResize = () => fit(root)); },
                destroy() { this.timers.forEach(clearTimeout); window.removeEventListener('resize', this.onResize); },
                later(fn, ms) { this.timers.push(setTimeout(fn, still() ? 0 : ms)); },
                tap() {
                    if (this.hatched) return;
                    this.taps += 1;
                    sound()?.play('knock');
                    this.knocking = false;
                    this.later(() => { this.knocking = true; }, 20);
                    this.later(() => { this.knocking = false; }, 470);
                    if (this.taps >= 3) {
                        this.later(() => { sound()?.play('hatch'); this.hatched = true; this.$nextTick(() => root.querySelector('[role=radio]')?.focus({ preventScroll: true })); }, 480);
                        this.later(() => sound()?.voice(config.pitch), 1200);
                    }
                },
                pick(i) { sound()?.play('pop'); this.name = i; },
                hint() { return config.hints[Math.min(this.taps, 3)]; },
                keep() {
                    if (this.saving) return;
                    this.saving = true;
                    sound()?.play('win');
                    this.$wire.nameCreature(config.key, config.names[this.name]);
                },
            };
        }

        /** Stars of a finished round, one by one. */
        function stars(count) {
            if (still()) return;
            for (let i = 0; i < count; i++) setTimeout(() => sound()?.play('star'), 250 + i * 280);
            if (count === 3) setTimeout(() => sound()?.play('win'), 250 + 3 * 280);
        }

        return { world, hatch, stars, fit };
    })();
</script>
