'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { useRouter } from '@/i18n/navigation';
import { nameCreature } from '@/app/[locale]/family/games/actions';
import { hex } from '@/lib/games/art';
import { sound } from '@/lib/games/sound';
import type { Island } from '@/lib/games/types';
import { CreatureArt, EggIcon } from './art';
import type { Played } from './use-play';

const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Stars of a finished round, one by one, and a fanfare for three. */
export function useStarSounds(stars: number) {
  useEffect(() => {
    if (still()) return;
    const timers = Array.from({ length: stars }, (_, i) => setTimeout(() => sound.play('star'), 250 + i * 280));
    if (stars === 3) timers.push(setTimeout(() => sound.play('win'), 250 + 3 * 280));
    return () => timers.forEach(clearTimeout);
  }, [stars]);
}

/** A round's result over the island (the world's look): stars one by one, how many right first time, homework handed in, the egg filling. */
export function Done({ played, onAgain, onLeave }: { played: Played; onAgain: () => void; onLeave: () => void }) {
  const t = useTranslations('games');
  const r = played.result;
  const again = useRef<HTMLButtonElement>(null);
  useStarSounds(r.stars);
  useEffect(() => again.current?.focus({ preventScroll: true }), []);
  const pct = Math.round((100 * r.egg.have) / Math.max(1, r.egg.need));
  return (
    <div className="gw-done gw-kids" role="dialog" aria-modal="true" aria-labelledby="gw-done-title">
      <div className="gw-done-card">
        <div className="gw-done-stars" role="img" aria-label={t('stars', { n: r.stars })}>
          {[1, 2, 3].map((i) => (
            <svg key={i} data-on={i <= r.stars ? 'yes' : 'no'} style={{ ['--i' as string]: i - 1, marginTop: i === 2 ? -14 : undefined }} width={i === 2 ? 86 : 66} height={i === 2 ? 86 : 66} viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" stroke="#13294B" strokeWidth="1.6" strokeLinejoin="round" />
            </svg>
          ))}
        </div>
        <h2 id="gw-done-title">{t(`result.${Math.min(3, r.stars)}` as 'result.0')}</h2>
        <p>{t('result.score', { correct: r.correct, total: r.total })}</p>
        {r.homework?.handed_in ? <div className="gw-done-hw">✅ {t('homeworkDone', { title: r.homework.title })}</div> : null}
        <div className="gw-eggbar">
          <EggIcon />
          <span>{t('map.eggCard', { have: r.egg.have, need: r.egg.need })}</span>
          <i role="progressbar" aria-valuemin={0} aria-valuemax={r.egg.need} aria-valuenow={r.egg.have}>
            <b style={{ width: `${pct}%` }} />
          </i>
        </div>
        <div className="gw-done-actions">
          <button ref={again} type="button" className="gw-bigbtn gw-3d" data-kind="go" onClick={onAgain}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
              <path d="M3 3v5h5" />
            </svg>
            {t('again')}
          </button>
          <button type="button" className="gw-bigbtn gw-3d" onClick={onLeave}>
            {t('map.toIsland')}
          </button>
        </div>
      </div>
    </div>
  );
}

/**
 * A creature hatched from a round's stars (the approved design, Hatch): the
 * night, a nest, an egg to tap three times, then the creature comes out
 * among feathers and the child names it (its own name or one of two more)
 * and puts it in the album.
 */
export function Hatch({ played, islands, onLeave }: { played: Played; islands: Island[]; onLeave: () => void }) {
  const t = useTranslations('games');
  const locale = useLocale();
  const router = useRouter();
  const c = played.result.creature!;
  const need = played.result.egg.need;
  const [taps, setTaps] = useState(0);
  const [knocking, setKnocking] = useState(false);
  const [hatched, setHatched] = useState(false);
  const [name, setName] = useState(0);
  const [saving, begin] = useTransition();
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const root = useRef<HTMLDivElement>(null);
  const egg = useRef<HTMLButtonElement>(null);
  const pitch = typeof c.look.pitch === 'number' ? c.look.pitch : 1;
  const islandName = islands.find((i) => i.key === c.island)?.name ?? '';
  const feathers = [hex(c.look.body, '#C9A06B'), '#FFD43B', hex(c.look.wing, '#B197FC'), hex(c.look.belly, '#C9A06B'), '#63E6BE'];
  const hints = [t('hatch.tap0'), t('hatch.tap1'), t('hatch.tap2'), t('hatch.tap3')];

  const later = (fn: () => void, ms: number) => timers.current.push(setTimeout(fn, still() ? 0 : ms));
  useEffect(() => {
    const pending = timers.current;
    egg.current?.focus({ preventScroll: true });
    return () => pending.forEach(clearTimeout);
  }, []);
  useEffect(() => {
    if (hatched) root.current?.querySelector<HTMLElement>('[role=radio]')?.focus({ preventScroll: true });
  }, [hatched]);

  const tap = () => {
    if (hatched) return;
    const now = taps + 1;
    setTaps(now);
    sound.play('knock');
    setKnocking(false);
    later(() => setKnocking(true), 20);
    later(() => setKnocking(false), 470);
    if (now >= 3) {
      later(() => {
        sound.play('hatch');
        setHatched(true);
      }, 480);
      later(() => sound.voice(pitch), 1200);
    }
  };

  const keep = () => {
    if (saving) return;
    sound.play('win');
    const chosen = c.names[name] ?? c.name;
    begin(async () => {
      // Its own name needs no nickname: the album shows the name it was given.
      await nameCreature(c.key, chosen === c.name ? '' : chosen);
      onLeave();
      router.push(`/family/games/album?new=${encodeURIComponent(c.key)}`);
    });
  };

  return (
    <div ref={root} className="gw-hatch gw-kids" role="dialog" aria-modal="true" aria-labelledby="gw-hatch-title">
      <div className="gw-hatch-stage">
        <svg aria-hidden="true" className="gw-abs" style={{ inset: 0 }} width="390" height="844" viewBox="0 0 390 844">
          <circle className="gw-twinkle" cx="40" cy="160" r="2.5" fill="#FFFFFF" />
          <circle className="gw-twinkle gw-d2" cx="350" cy="200" r="2" fill="#FFFFFF" />
          <circle className="gw-twinkle gw-d3" cx="300" cy="120" r="3" fill="#FFE8A3" />
          <circle className="gw-twinkle" cx="80" cy="300" r="2" fill="#FFFFFF" />
          <circle className="gw-twinkle gw-d2" cx="360" cy="380" r="2.5" fill="#FFFFFF" />
          <circle className="gw-twinkle gw-d3" cx="20" cy="440" r="2" fill="#FFE8A3" />
          <circle cx="330" cy="70" r="26" fill="#FFE8A3" />
          <circle cx="342" cy="62" r="24" fill="#2B1A66" />
        </svg>

        <header className="gw-abs" style={{ top: 0, left: 0, right: 0, height: 64, padding: '0 16px', display: 'flex', alignItems: 'center', zIndex: 6 }}>
          <button
            type="button"
            className="gw-3d"
            onClick={onLeave}
            aria-label={t('map.toIsland')}
            style={{ width: 44, height: 44, borderRadius: 999, background: '#FFFFFF', border: '3px solid #24124F', boxShadow: '0 3px 0 #120A33', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 0 }}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#24124F" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={locale === 'ar' ? undefined : { transform: 'scaleX(-1)' }}>
              <path d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </header>

        <div aria-live="polite" className="gw-abs" style={{ top: 76, left: 20, right: 20, textAlign: 'center', zIndex: 5 }}>
          <h2 id="gw-hatch-title" style={{ margin: 0, fontSize: 30, fontWeight: 800, lineHeight: 1.15 }}>
            {hatched ? t('hatch.done') : t('hatch.ready')}
          </h2>
          <p style={{ margin: '4px 0 0', fontSize: 16, fontWeight: 600, color: '#D0BFFF' }}>{hatched ? t('hatch.doneSub', { need }) : t('hatch.readySub', { need })}</p>
        </div>

        {hatched ? (
          <div className="gw-rays-in gw-abs" aria-hidden="true" style={{ left: 35, top: 190, width: 320, height: 320 }}>
            <svg className="gw-spin" width="320" height="320" viewBox="-160 -160 320 320">
              {Array.from({ length: 10 }, (_, i) => (
                <path key={i} d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform={`rotate(${i * 36})`} />
              ))}
            </svg>
          </div>
        ) : null}

        <svg aria-hidden="true" className="gw-abs" style={{ left: 65, top: 470, zIndex: 1 }} width="260" height="90" viewBox="0 0 260 90">
          <ellipse cx="130" cy="50" rx="126" ry="36" fill="#6B4423" />
          <path d="M10 44 C60 70 200 70 250 44 M20 56 C80 80 180 80 240 56 M30 36 L70 60 M230 36 L190 60 M90 30 L120 62 M170 30 L140 62" stroke="#9C6B3F" strokeWidth="6" strokeLinecap="round" fill="none" />
          <ellipse cx="130" cy="30" rx="96" ry="18" fill="#3E2614" />
        </svg>

        {!hatched ? (
          <div>
            <button
              ref={egg}
              type="button"
              className={`gw-abs ${knocking ? 'gw-knock' : 'gw-idle'}`}
              onClick={tap}
              aria-label={t('hatch.egg', { left: Math.max(0, 3 - taps) })}
              style={{ left: 115, top: 300, width: 160, height: 200, background: 'none', border: 0, padding: 0, cursor: 'pointer', zIndex: 2 }}
            >
              <svg width="160" height="200" viewBox="0 0 160 200" aria-hidden="true">
                <ellipse cx="80" cy="110" rx="62" ry="80" fill="#F3E3C3" stroke="#24124F" strokeWidth="5" />
                <circle cx="56" cy="70" r="10" fill="#E0C79A" />
                <circle cx="110" cy="140" r="14" fill="#E0C79A" />
                <circle cx="104" cy="58" r="6" fill="#E0C79A" />
                <circle cx="50" cy="150" r="7" fill="#E0C79A" />
                <path d="M30 96 L44 86 L52 100 L64 90" fill="none" stroke="#24124F" strokeWidth="4" strokeLinejoin="round" strokeLinecap="round" opacity={taps >= 1 ? 1 : 0} />
                <path d="M64 90 L76 104 L90 90 L102 102" fill="none" stroke="#24124F" strokeWidth="4" strokeLinejoin="round" strokeLinecap="round" opacity={taps >= 2 ? 1 : 0} />
                <path d="M102 102 L114 88 L130 100 M90 90 L92 74" fill="none" stroke="#24124F" strokeWidth="4" strokeLinejoin="round" strokeLinecap="round" opacity={taps >= 3 ? 1 : 0} />
              </svg>
            </button>
            <div className="gw-tap-hint gw-abs" style={{ top: 580, left: 0, right: 0, display: 'flex', justifyContent: 'center', zIndex: 3 }}>
              <span style={{ padding: '6px 18px', borderRadius: 999, background: '#FFD43B', color: '#24124F', border: '3px solid #24124F', fontSize: 17, fontWeight: 800 }}>{hints[Math.min(taps, 3)]}</span>
            </div>
          </div>
        ) : (
          <div>
            <svg aria-hidden="true" className="gw-abs" style={{ left: 115, top: 300, zIndex: 3, overflow: 'visible' }} width="160" height="200" viewBox="0 0 160 200">
              <path d="M18 112 L32 116 L46 102 L60 116 L74 102 L88 116 L102 100 L116 116 L130 102 L142 112 C142 162 112 190 80 190 C48 190 18 162 18 112 Z" fill="#F3E3C3" stroke="#24124F" strokeWidth="5" strokeLinejoin="round" />
              <circle cx="110" cy="150" r="12" fill="#E0C79A" />
              <g className="gw-shell-top">
                <path d="M18 112 C18 52 50 30 80 30 C110 30 142 52 142 112 L130 102 L116 116 L102 100 L88 116 L74 102 L60 116 L46 102 L32 116 Z" fill="#F3E3C3" stroke="#24124F" strokeWidth="5" strokeLinejoin="round" />
                <circle cx="56" cy="70" r="10" fill="#E0C79A" />
              </g>
            </svg>
            <button
              type="button"
              className="gw-emerge gw-abs"
              onClick={() => sound.voice(pitch)}
              aria-label={c.name}
              style={{ left: 95, top: 210, width: 200, height: 176, background: 'none', border: 0, padding: 0, cursor: 'pointer', zIndex: 4 }}
            >
              <span className="gw-hover" style={{ display: 'block' }}>
                <CreatureArt look={c.look} size={200} motion line="#24124F" />
              </span>
            </button>
            <div aria-hidden="true" className="gw-abs" style={{ inset: 0, zIndex: 5, pointerEvents: 'none' }}>
              {(
                [
                  [60, 18, 34, 0],
                  [160, 14, 28, 0.4],
                  [260, 18, 34, 0.2],
                  [330, 14, 28, 0.7],
                  [20, 16, 30, 0.9],
                ] as const
              ).map(([x, fw, fh, delay], i) => (
                <svg key={x} className="gw-feather gw-abs" style={{ left: x, top: 0, animationDelay: `${delay}s` }} width={fw} height={fh} viewBox="0 0 18 34">
                  <path d="M9 2 C18 10 16 24 9 32 C2 24 0 10 9 2 Z" fill={feathers[i]} />
                  <path d="M9 4 V32" stroke="#24124F" strokeOpacity=".35" strokeWidth="1.5" />
                </svg>
              ))}
            </div>

            <section className="gw-hatch-card" aria-labelledby="gw-new-name">
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontSize: 13, fontWeight: 700, color: '#5C4A99' }}>{t('hatch.new', { found: c.found, total: c.total })}</div>
                  <h3 id="gw-new-name" style={{ margin: 0, fontSize: 24, fontWeight: 800, lineHeight: 1.2 }}>
                    {c.name}
                  </h3>
                </div>
                <span style={{ flex: 'none', padding: '2px 10px', borderRadius: 999, background: '#FFF4D6', border: '2px solid #24124F', fontSize: 13, fontWeight: 800 }}>{islandName}</span>
              </div>
              <div style={{ marginTop: 10, fontSize: 15, fontWeight: 700 }}>{t('hatch.ask')}</div>
              <div role="radiogroup" aria-label={t('hatch.namesLabel')} style={{ marginTop: 6, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                {c.names.map((n, i) => (
                  <button
                    key={n}
                    type="button"
                    role="radio"
                    className="gw-name gw-3d"
                    aria-checked={name === i}
                    onClick={() => {
                      sound.play('pop');
                      setName(i);
                    }}
                  >
                    {n}
                  </button>
                ))}
              </div>
              <button
                type="button"
                className="gw-bigbtn gw-3d"
                data-kind="go"
                style={{ marginTop: 12, borderColor: '#24124F', boxShadow: '0 4px 0 #24124F', color: '#24124F' }}
                onClick={keep}
                disabled={saving}
              >
                {t('hatch.keep')}
              </button>
            </section>
          </div>
        )}
      </div>
    </div>
  );
}
