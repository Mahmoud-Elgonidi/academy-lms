{{--
    The moving parts of chrome/styles:
    - page changes are View Transitions: the marker below lets Livewire run one on each wire:navigate,
      and this names what moves in it, in the old page and again in the new one: the page itself
      (lms-page, only near the top of the page) and the open page's mark in the side menu, the top bar
      or a student's tab bar (lms-nav-*), which slides from the old item to the new one;
    - the side menu keeps where it was scrolled to (in time for the new picture), and comes in item by item on the first page only
      (html.lms-settled marks the pages after it);
    - a menu item answers as soon as it is clicked, before the page arrives (data-lms-going).
    Browsers without View Transitions, and people who ask for less motion, get the plain change.
--}}
<i hidden wire:transition.navigate="lms-shell"></i>
<script>
    window.LmsChrome ??= (() => {
        const calm = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const wide = () => window.matchMedia('(min-width: 1024px)').matches;
        const marks = [
            ['lms-nav-side', '.fi-sidebar-item-btn[aria-current="page"]', wide],
            ['lms-nav-top', '.fi-topbar-item.fi-active > .fi-topbar-item-btn', () => true],
            ['lms-nav-tab', '.lms-tab[aria-current]', () => true],
        ];
        const going = '.fi-sidebar-item-btn[href], .fi-topbar-item-btn[href], .lms-tab[href]';
        let named = [], turn = 0;

        const inView = (el) => {
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && r.top >= 0 && r.left >= 0 && r.bottom <= window.innerHeight && r.right <= window.innerWidth;
        };

        // One element per name, or the browser drops the whole transition.
        function name(page) {
            named.forEach((el) => { el.style.viewTransitionName = ''; });
            named = [];
            const give = (el, value) => { el.style.viewTransitionName = value; named.push(el); };
            const main = document.querySelector('.fi-main');
            if (page && main) give(main, 'lms-page');
            marks.forEach(([value, selector, when]) => {
                const el = when() ? [...document.querySelectorAll(selector)].find(inView) : null;
                if (el) give(el, value);
            });
        }

        // Before the change: name what moves in the old page (the browser takes its picture next).
        let pending = null;
        document.addEventListener('livewire:navigating', (event) => {
            const moving = ! calm() && typeof document.startViewTransition === 'function';
            pending = {
                moving,
                page: moving && window.scrollY < 160,   // further down, the old page would jump before it fades
                menuTop: document.querySelector('.fi-sidebar-nav')?.scrollTop ?? 0,
                shell: document.querySelector('.fi-layout') !== null,
            };
            if (moving) {
                name(pending.page);
                // Livewire lets go of Alpine on the old page before the browser takes its picture, and Alpine takes
                // back what it bound (the open menu's class, the page's opacity): put it back for the picture.
                const bound = [...document.querySelectorAll('[x-bind\\:class], [x-bind\\:style]')].map((el) => [el, el.getAttribute('class'), el.getAttribute('style')]);
                queueMicrotask(() => bound.forEach(([el, cls, style]) => {
                    if (! el.isConnected) return;
                    if (cls !== null && el.getAttribute('class') !== cls) el.setAttribute('class', cls);
                    if (style !== null && el.getAttribute('style') !== style) el.setAttribute('style', style);
                }));
            }
            // The new page is in: from the second page of the panel on, the menu does not come in again.
            event.detail.onSwap(() => { if (pending?.shell) document.documentElement.classList.add('lms-settled'); });
        });

        // After the change, before the browser takes the new picture: once Alpine has shown the new menu (it
        // lifts x-cloak in a microtask queued before this one), put the menu back where it was scrolled to
        // (Filament does it a frame later, too late for the picture), then name what moves.
        document.addEventListener('livewire:navigated', () => {
            if (! pending) return;
            const { moving, page, menuTop } = pending;
            pending = null;
            queueMicrotask(() => {
                const menu = document.querySelector('.fi-sidebar-nav');
                if (menu && menuTop) menu.scrollTop = menuTop;
                if (! moving) return;
                name(page);
                const mine = ++turn;
                setTimeout(() => { if (mine === turn) { named.forEach((el) => { el.style.viewTransitionName = ''; }); named = []; } }, 1200);
            });
        });

        document.addEventListener('click', (event) => {
            const link = event.target.closest?.(going);
            if (! link || link.hasAttribute('aria-current') || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            document.querySelectorAll('[data-lms-going]').forEach((el) => el.removeAttribute('data-lms-going'));
            link.setAttribute('data-lms-going', '');
        }, true);

        return { name };
    })();
</script>
