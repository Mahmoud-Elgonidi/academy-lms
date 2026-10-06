{{--
    The moving parts of motion/styles, on every page and whatever Livewire adds later:
    - data-count="580": the number counts up when it comes into view (from data-count-from,
      or from what this browser saw last time under data-count-key);
    - data-celebrate="key" data-celebrate-value="5" data-celebrate-text="…": confetti and the
      word when the value grew since this browser last saw it (or the first time it is above 0).
    Nothing runs for people who ask for less motion; nothing runs off screen.
--}}
<script>
    window.LmsMotion ??= (() => {
        const calm = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const number = new Intl.NumberFormat('en-US');
        const done = new WeakSet();
        let cheered = 0;

        const remember = (key, value) => {
            let before = null;
            try { before = window.localStorage.getItem('lms:' + key); window.localStorage.setItem('lms:' + key, String(value)); } catch (e) {}
            return before === null ? null : Number(before);
        };

        function count(el) {
            const to = Number(el.dataset.count);
            if (! Number.isFinite(to)) return;
            const seen = el.dataset.countKey ? remember('count:' + el.dataset.countKey, to) : null;
            const from = Number(el.dataset.countFrom ?? (seen !== null && seen <= to ? seen : 0));
            if (calm() || from === to) { el.textContent = number.format(to); return; }
            const duration = Math.min(1400, 600 + Math.abs(to - from) * 8);
            el.textContent = number.format(from);
            setTimeout(() => {
                const start = performance.now();
                const step = (now) => {
                    const t = Math.min(1, (now - start) / duration);
                    el.textContent = number.format(Math.round(from + (to - from) * (1 - Math.pow(1 - t, 3))));
                    if (t < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            }, Number(el.dataset.countDelay ?? 250));
        }

        function celebrate(el) {
            const value = Number(el.dataset.celebrateValue);
            const before = remember('cheer:' + el.dataset.celebrate, value);
            if (calm() || ! (value > 0) || (before !== null && before >= value) || cheered > 0) return;
            cheered++;
            setTimeout(() => burst(el, el.dataset.celebrateText), Number(el.dataset.celebrateDelay ?? 900));
        }

        function burst(el, text) {
            const r = el.getBoundingClientRect();
            const box = document.createElement('div');
            box.className = 'lms-confetti';
            box.style.left = (r.left + r.width / 2) + 'px';
            box.style.top = (r.top + r.height / 2) + 'px';
            const colours = ['--primary-400', '--warning-400', '--success-400', '--info-400', '--danger-400', '--warning-300'];
            for (let i = 0; i < 42; i++) {
                const piece = document.createElement('i');
                const angle = Math.random() * Math.PI * 2, reach = 70 + Math.random() * 150;
                piece.style.setProperty('--dx', Math.round(Math.cos(angle) * reach) + 'px');
                piece.style.setProperty('--dy', Math.round(Math.sin(angle) * reach * .8 - 70) + 'px');
                piece.style.setProperty('--rot', Math.round(Math.random() * 720 - 360) + 'deg');
                piece.style.background = `var(${colours[i % colours.length]})`;
                piece.style.animationDelay = Math.round(Math.random() * 120) + 'ms';
                box.append(piece);
            }
            if (text) {
                const word = document.createElement('b');
                word.textContent = text;
                box.append(word);
            }
            document.body.append(box);
            const word = box.querySelector('b');
            if (word) {   // the word stays on screen, even from a corner
                const centre = r.left + r.width / 2, half = word.offsetWidth / 2, margin = 12;
                word.style.left = (Math.max(0, margin - (centre - half)) - Math.max(0, centre + half - (window.innerWidth - margin))) + 'px';
            }
            setTimeout(() => box.remove(), 2400);
        }

        // Act once each thing is on screen.
        const view = new IntersectionObserver((entries) => entries.forEach(({ isIntersecting, target }) => {
            if (! isIntersecting) return;
            view.unobserve(target);
            if (target.dataset.count !== undefined) count(target);
            if (target.dataset.celebrate !== undefined) celebrate(target);
        }), { threshold: .4 });

        function scan(root) {
            if (! (root instanceof Element || root instanceof Document)) return;
            const found = [...root.querySelectorAll('[data-count], [data-celebrate]')];
            if (root instanceof Element && root.matches('[data-count], [data-celebrate]')) found.push(root);
            found.forEach((el) => { if (! done.has(el)) { done.add(el); view.observe(el); } });
        }

        // Livewire adds parts later (lazy widgets, live refreshes): look at what it adds.
        let watching = null, queued = [];
        const added = new MutationObserver((records) => {
            records.forEach((r) => r.addedNodes.forEach((n) => n.nodeType === 1 && queued.push(n)));
            if (queued.length) requestAnimationFrame(() => { const nodes = queued; queued = []; nodes.forEach(scan); });
        });

        return {
            start() {
                cheered = 0;
                if (watching !== document.body) {
                    added.disconnect();
                    added.observe(document.body, { childList: true, subtree: true });
                    watching = document.body;
                }
                scan(document);
            },
            burst,
        };
    })();
    window.LmsMotion.start();
</script>
