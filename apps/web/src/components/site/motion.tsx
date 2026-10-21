'use client';

import { useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react';

/*
 * The public pages' motion (site.css): what moves only moves when the person
 * allows motion, and everything is there to read without it. Reveal arms the
 * page (data-armed) once its script runs, then marks each part as it reaches
 * the screen (data-shown); the rest follow the same watch.
 */

const still = () => typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Before the first paint on the client, so a part below the fold never shows and then hides.
const useBeforePaint = typeof window === 'undefined' ? useEffect : useLayoutEffect;

/** The page's parts ([data-reveal]) come in as they reach the screen, each once. */
export function Reveal({ children, className }: { children: ReactNode; className?: string }) {
  const root = useRef<HTMLDivElement>(null);
  useBeforePaint(() => {
    const el = root.current;
    if (!el || still() || !('IntersectionObserver' in window)) return;
    el.setAttribute('data-armed', '');
    const seen = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.setAttribute('data-shown', '');
            seen.unobserve(entry.target);
          }
        }
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.12 },
    );
    const watch = () => el.querySelectorAll('[data-reveal]:not([data-shown])').forEach((part) => seen.observe(part));
    watch();
    // Parts that come later (a tab switched, a form's answer) are watched too.
    const later = new MutationObserver(watch);
    later.observe(el, { childList: true, subtree: true });
    return () => {
      seen.disconnect();
      later.disconnect();
    };
  }, []);
  return (
    <div ref={root} className={className}>
      {children}
    </div>
  );
}

/** A number that counts up from 0 the first time it is seen ("+50", "24/7" and "1:1" are left as they are). */
export function CountUp({ value }: { value: string }) {
  const [shown, setShown] = useState(value);
  const ref = useRef<HTMLElement>(null);
  useEffect(() => {
    const parts = /^(\D*)(\d{1,7})(\D*)$/.exec(value.trim());
    const el = ref.current;
    if (!parts || !el || still() || !('IntersectionObserver' in window)) return;
    const [, before = '', digits = '0', after = ''] = parts;
    const target = Number(digits);
    let frame = 0;
    const seen = new IntersectionObserver(([entry]) => {
      if (!entry?.isIntersecting) return;
      seen.disconnect();
      const start = performance.now();
      const step = (now: number) => {
        const t = Math.min(1, (now - start) / 1400);
        const eased = 1 - Math.pow(1 - t, 3);
        setShown(`${before}${Math.round(target * eased).toLocaleString('en-US')}${after}`);
        if (t < 1) frame = requestAnimationFrame(step);
      };
      setShown(`${before}0${after}`);
      frame = requestAnimationFrame(step);
    }, { threshold: 0.6 });
    seen.observe(el);
    return () => {
      seen.disconnect();
      cancelAnimationFrame(frame);
    };
  }, [value]);
  return <b ref={ref}>{shown}</b>;
}

/** Tilts what it holds toward the pointer (a few degrees), back to rest when it leaves. */
export function Tilt({ children, className, max = 8 }: { children: ReactNode; className?: string; max?: number }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = ref.current;
    const area = el?.parentElement;
    if (!el || !area || still() || window.matchMedia('(pointer: coarse)').matches) return;
    let frame = 0;
    const move = (event: PointerEvent) => {
      const box = area.getBoundingClientRect();
      const x = (event.clientX - box.left) / box.width - 0.5;
      const y = (event.clientY - box.top) / box.height - 0.5;
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        el.style.setProperty('--ry', `${(x * max).toFixed(2)}deg`);
        el.style.setProperty('--rx', `${(-y * max).toFixed(2)}deg`);
      });
    };
    const leave = () => {
      cancelAnimationFrame(frame);
      el.style.setProperty('--ry', '0deg');
      el.style.setProperty('--rx', '0deg');
    };
    area.addEventListener('pointermove', move);
    area.addEventListener('pointerleave', leave);
    return () => {
      cancelAnimationFrame(frame);
      area.removeEventListener('pointermove', move);
      area.removeEventListener('pointerleave', leave);
    };
  }, [max]);
  return (
    <div ref={ref} className={className}>
      {children}
    </div>
  );
}

/** The header turns solid once the page has scrolled past its top. */
export function SolidOnScroll({ children, className }: { children: ReactNode; className?: string }) {
  const [solid, setSolid] = useState(false);
  useEffect(() => {
    const check = () => setSolid(window.scrollY > 40);
    check();
    window.addEventListener('scroll', check, { passive: true });
    return () => window.removeEventListener('scroll', check);
  }, []);
  return (
    <header className={className} data-solid={solid ? '' : undefined}>
      {children}
    </header>
  );
}

/**
 * Which of a list of parts is in the middle of the screen (the app's steps,
 * while the phone beside them shows that step's screen).
 */
export function useActiveStep(count: number): [number, (index: number) => (el: HTMLElement | null) => void] {
  const [active, setActive] = useState(0);
  const parts = useRef<(HTMLElement | null)[]>([]);
  useEffect(() => {
    if (!('IntersectionObserver' in window)) return;
    const seen = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) setActive(Number((entry.target as HTMLElement).dataset.step ?? 0));
        }
      },
      { rootMargin: '-45% 0px -45% 0px' },
    );
    parts.current.slice(0, count).forEach((el) => el && seen.observe(el));
    return () => seen.disconnect();
  }, [count]);
  return [active, (index: number) => (el: HTMLElement | null) => { parts.current[index] = el; }];
}
