{{--
    The games world's sound (the approved design): a calm sea with notes on each island's own
    scale (its catalog `music`), and short effects (zoom, back, locked, chirp, bubble, knock,
    hatch, cheep, pop, star, voice). Made in the browser with Web Audio, nothing downloaded.
    Off until the learner's first tap (the browser's rule); the choice to mute is kept on this
    device only (lms-games-muted) and handed to the games as data-muted.
--}}
<script>
    window.LmsIslandsSound ??= (() => {
        const KEY = 'lms-games-muted';
        const SCALES = {
            map: [392, 440, 523.25, 587.33, 659.25, 783.99, 880],
            major: [523.25, 587.33, 659.25, 783.99, 880, 1046.5, 1174.66],
            pentatonic: [261.63, 293.66, 329.63, 392, 440, 523.25, 587.33],
            dorian: [293.66, 329.63, 349.23, 392, 440, 493.88, 523.25],
            lydian: [349.23, 392, 440, 493.88, 523.25, 587.33, 659.25],
            phrygian: [164.81, 174.61, 196, 220, 246.94, 261.63, 293.66],
        };
        let ac = null, music = null, timer = null, mode = 'map';
        let muted = false;
        try { muted = window.localStorage.getItem(KEY) === '1'; } catch (e) {}

        function ctx() {
            try {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (! AC) return null;
                ac = ac || new AC();
                if (ac.state === 'suspended') ac.resume();
                return ac;
            } catch (e) { return null; }
        }

        function tone(f1, f2, at, dur, type, vol, dest) {
            const t = ac.currentTime + 0.02 + at;
            const o = ac.createOscillator(), g = ac.createGain();
            o.type = type || 'sine';
            o.frequency.setValueAtTime(f1, t);
            if (f2) o.frequency.exponentialRampToValueAtTime(f2, t + dur);
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(vol || 0.2, t + 0.015);
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            o.connect(g); g.connect(dest || ac.destination);
            o.start(t); o.stop(t + dur + 0.05);
        }

        function noise(at, dur, f1, f2, vol, dest) {
            const t = ac.currentTime + 0.02 + at;
            const len = Math.floor(ac.sampleRate * dur), buf = ac.createBuffer(1, len, ac.sampleRate), d = buf.getChannelData(0);
            for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
            const s = ac.createBufferSource(), bp = ac.createBiquadFilter(), g = ac.createGain();
            s.buffer = buf; bp.type = 'bandpass'; bp.Q.value = 1.2;
            bp.frequency.setValueAtTime(f1, t); bp.frequency.exponentialRampToValueAtTime(f2, t + dur);
            g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + dur * 0.4); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            s.connect(bp); bp.connect(g); g.connect(dest || ac.destination); s.start(t); s.stop(t + dur);
        }

        const EFFECTS = {
            zoom: () => { noise(0, 0.7, 300, 3000, 0.25); [523, 659, 784, 1047, 1319].forEach((f, i) => tone(f, 0, 0.2 + i * 0.07, 0.4, 'triangle', 0.12)); },
            back: () => { noise(0, 0.5, 2500, 300, 0.2); [1047, 784, 659].forEach((f, i) => tone(f, 0, i * 0.07, 0.3, 'triangle', 0.09)); },
            locked: () => { tone(330, 0, 0, 0.12, 'triangle', 0.16); tone(262, 0, 0.12, 0.25, 'triangle', 0.14); noise(0.05, 0.2, 4000, 6000, 0.05); },
            chirp: () => { tone(1800, 2700, 0, 0.09, 'sine', 0.14); tone(2100, 3100, 0.12, 0.08, 'sine', 0.12); },
            bubble: () => [0, 0.1, 0.22].forEach((d, i) => tone(300 + i * 120, 900 + i * 200, d, 0.12, 'sine', 0.14)),
            jungle: () => { tone(700, 1400, 0, 0.12, 'square', 0.05); tone(900, 500, 0.14, 0.14, 'triangle', 0.1); },
            ice: () => [1568, 2093, 2637].forEach((f, i) => tone(f, 0, i * 0.06, 0.5, 'sine', 0.07)),
            rumble: () => { noise(0, 0.9, 120, 60, 0.35); tone(80, 50, 0, 0.8, 'sawtooth', 0.04); },
            knock: () => { tone(320, 180, 0, 0.08, 'triangle', 0.3); noise(0.02, 0.1, 2500, 1200, 0.25); },
            hatch: () => { noise(0, 0.25, 3000, 600, 0.5); [523, 659, 784, 1047, 1319, 1568].forEach((f, i) => tone(f, 0, 0.15 + i * 0.08, 0.35, 'triangle', 0.16)); },
            cheep: () => { tone(2200, 3000, 0, 0.07, 'sine', 0.16); tone(2400, 3200, 0.1, 0.07, 'sine', 0.14); tone(2600, 2000, 0.2, 0.09, 'sine', 0.12); },
            pop: () => tone(520, 980, 0, 0.09, 'sine', 0.22),
            star: () => { tone(880, 1760, 0, 0.12, 'triangle', 0.16); tone(1320, 0, 0.08, 0.25, 'sine', 0.1); },
            win: () => [523, 659, 784, 1047].forEach((f, i) => tone(f, 0, i * 0.11, 0.4, 'triangle', 0.14)),
        };
        // Each island's own little cue when it opens.
        const CUES = { major: 'chirp', pentatonic: 'bubble', dorian: 'jungle', lydian: 'ice', phrygian: 'rumble' };

        function play(name) {
            if (muted || ! ctx()) return;
            try { (EFFECTS[name] || EFFECTS.pop)(); } catch (e) {}
        }

        /** A creature's voice: a chirp at its own pitch (the catalog's look.pitch). */
        function voice(pitch) {
            if (muted || ! ctx()) return;
            const p = Math.max(0.3, Math.min(2, Number(pitch) || 1));
            try {
                tone(1200 * p, 1800 * p, 0, 0.1, 'sine', 0.16);
                tone(1400 * p, 2000 * p, 0.13, 0.09, 'sine', 0.14);
                tone(1600 * p, 1100 * p, 0.26, 0.14, 'triangle', 0.1);
            } catch (e) {}
        }

        function beat() {
            const m = music;
            if (! m) return;
            try {
                const sc = SCALES[mode] || SCALES.map;
                m.step += 1;
                const bar = m.step % 16;
                if (bar === 0 || bar === 8) tone(sc[0] / 2, 0, 0, 0.9, 'sine', 0.05, m.master);
                if (bar === 4 || bar === 12) tone(sc[2] / 2, 0, 0, 0.7, 'sine', 0.04, m.master);
                if (bar % 4 === 3 && Math.random() < 0.5) return;
                m.idx = Math.max(0, Math.min(sc.length - 1, m.idx + [-2, -1, 1, 2, 0][Math.floor(Math.random() * 5)]));
                const f = sc[m.idx];
                if (mode === 'major') { tone(f, f * 1.01, 0, 0.35, 'sine', 0.035, m.master); if (Math.random() < 0.05) { tone(2200, 3000, 0.1, 0.07, 'sine', 0.04, m.master); tone(2400, 3200, 0.2, 0.07, 'sine', 0.035, m.master); } }
                else if (mode === 'pentatonic') { tone(f, 0, 0, 0.22, 'triangle', 0.05, m.master); if (Math.random() < 0.06) tone(400, 1200, 0.05, 0.12, 'sine', 0.04, m.master); }
                else if (mode === 'dorian') { tone(f, 0, 0, 0.16, 'triangle', 0.055, m.master); if (bar % 2 === 0) tone(f * 2, 0, 0.08, 0.06, 'square', 0.012, m.master); }
                else if (mode === 'lydian') { tone(f * 2, 0, 0, 0.6, 'sine', 0.03, m.master); }
                else if (mode === 'phrygian') { tone(f, 0, 0, 0.5, 'triangle', 0.05, m.master); }
                else { tone(f, 0, 0, 0.45, 'triangle', 0.035, m.master); if (Math.random() < 0.03) tone(1300, 900, 0, 0.25, 'sine', 0.03, m.master); }
            } catch (e) {}
        }

        function start() {
            if (music || muted || ! ctx()) return;
            try {
                const master = ac.createGain();
                master.gain.setValueAtTime(0.0001, ac.currentTime);
                master.gain.exponentialRampToValueAtTime(1, ac.currentTime + 1.6);
                master.connect(ac.destination);
                const len = ac.sampleRate * 2, buf = ac.createBuffer(1, len, ac.sampleRate), d = buf.getChannelData(0);
                for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
                const sea = ac.createBufferSource(); sea.buffer = buf; sea.loop = true;
                const lp = ac.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 420;
                const sg = ac.createGain(); sg.gain.value = 0.035;
                const lfo = ac.createOscillator(); lfo.frequency.value = 0.13;
                const lg = ac.createGain(); lg.gain.value = 0.03;
                lfo.connect(lg); lg.connect(sg.gain);
                sea.connect(lp); lp.connect(sg); sg.connect(master);
                sea.start(); lfo.start();
                music = { master, sea, lfo, step: 0, idx: 3 };
                timer = setInterval(beat, 280);
            } catch (e) {}
        }

        function stop(now) {
            clearInterval(timer);
            const m = music;
            music = null;
            if (! m) return;
            try {
                const t = ac.currentTime;
                m.master.gain.cancelScheduledValues(t);
                m.master.gain.setValueAtTime(Math.max(m.master.gain.value, 0.0001), t);
                m.master.gain.exponentialRampToValueAtTime(0.0001, t + (now ? 0.05 : 0.6));
                m.sea.stop(t + (now ? 0.1 : 0.7)); m.lfo.stop(t + (now ? 0.1 : 0.7));
            } catch (e) {}
        }

        function setMuted(on) {
            muted = !! on;
            try { window.localStorage.setItem(KEY, muted ? '1' : '0'); } catch (e) {}
            if (muted) stop(false); else start();
            document.dispatchEvent(new CustomEvent('lms-games:muted', { detail: { muted } }));
        }

        // Leaving the page (or navigating away inside the panel) stops the music.
        document.addEventListener('livewire:navigating', () => stop(true));
        document.addEventListener('visibilitychange', () => { if (document.hidden) stop(true); });

        return {
            play, voice, start, stop, cue: (music) => play(CUES[music] || 'chirp'),
            scale: (m) => { mode = SCALES[m] ? m : 'map'; },
            muted: () => muted, setMuted, playing: () => music !== null,
        };
    })();
</script>
