'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { chooseBuddy, nameCreature } from '@/app/[locale]/family/games/actions';
import { Link } from '@/i18n/navigation';
import { hex } from '@/lib/games/art';
import { sound } from '@/lib/games/sound';
import type { AlbumCreature } from '@/lib/games/types';
import { CreatureArt } from './art';
import './games.css';
import './islands.css';

const RING: Record<string, string> = { common: '#24124F', rare: '#1C7ED6', legendary: '#F08C00' };
const RARITY_BG: Record<string, string> = { common: '#F1F3F5', rare: '#D0EBFF', legendary: '#FFE8A3' };
const TABS = ['all', 'common', 'rare', 'legendary'] as const;

/**
 * The album (the approved design, Album), as the /my panel and the app show
 * it: all the world's creatures, hatched ones in colour (their name or the
 * one the child gave, where they live), the rest as a shadow with "?"; tabs
 * by rarity and chips by island; a creature opens in a sheet with its voice
 * and story, to make it the buddy or name it. A guardian sees the same
 * without changing anything.
 */
export function Album({ creatures, need, guardian, learnerName, island: firstIsland, open: firstOpen }: {
  creatures: AlbumCreature[];
  need: number;
  guardian: boolean;
  learnerName: string;
  /** An island to show (from an opened island). */
  island: string | null;
  /** A creature to open (one just named). */
  open: string | null;
}) {
  const t = useTranslations('games');
  const locale = useLocale();
  const [tab, setTab] = useState<(typeof TABS)[number]>('all');
  const [island, setIsland] = useState<string | null>(firstIsland);
  const [open, setOpen] = useState<string | null>(firstOpen && creatures.some((c) => c.key === firstOpen) ? firstOpen : null);
  const [toast, setToast] = useState('');
  const [singing, setSinging] = useState(false);
  const [pending, begin] = useTransition();
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const found = creatures.filter((c) => c.found).length;
  const islands = [...new Map(creatures.map((c) => [c.island, c.island_name])).entries()];
  const chosen = creatures.find((c) => c.key === open) ?? null;

  useEffect(() => {
    const pending = timers.current;
    return () => pending.forEach(clearTimeout);
  }, []);

  useEffect(() => {
    if (!open) return;
    document.querySelector<HTMLElement>('.gw-album-sheet [data-first]')?.focus({ preventScroll: true });
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(null);
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open]);

  const pitchOf = (c: AlbumCreature) => (typeof c.look.pitch === 'number' ? c.look.pitch : 1);
  const say = (text: string) => {
    setToast(text);
    timers.current.push(setTimeout(() => setToast(''), 2600));
  };
  const show = (c: AlbumCreature) => {
    setOpen(c.key);
    if (c.found) sound.voice(pitchOf(c));
    else sound.play('locked');
  };
  const sing = (c: AlbumCreature) => {
    sound.voice(pitchOf(c));
    setSinging(false);
    timers.current.push(setTimeout(() => setSinging(true), 20));
    timers.current.push(setTimeout(() => setSinging(false), 520));
  };
  const buddy = (c: AlbumCreature) => {
    sound.play('win');
    begin(async () => {
      const done = await chooseBuddy(c.key);
      setOpen(null);
      say(done.ok ? t('album.buddyDone', { name: c.nickname || c.name }) : (done.message ?? t('failed')));
    });
  };
  const rename = (c: AlbumCreature, name: string) => {
    sound.play('pop');
    begin(async () => {
      const done = await nameCreature(c.key, name === c.name ? '' : name);
      say(done.ok ? t('album.named', { name }) : (done.message ?? t('failed')));
    });
  };

  return (
    <div className="gw-album">
      <header className="gw-album-head">
        <div>
          <Link className="gw-circle gw-3d" href={island ? `/family/games?island=${encodeURIComponent(island)}` : '/family/games'} aria-label={t('album.back')}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#24124F" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={locale === 'ar' ? undefined : { transform: 'scaleX(-1)' }}>
              <path d="M9 5l7 7-7 7" />
            </svg>
          </Link>
          <h1>{t('album.title')}</h1>
        </div>
        <span className="gw-count">{t('album.count', { found, total: creatures.length })}</span>
      </header>
      <div style={{ height: 12, borderRadius: 999, background: '#F1E3C0', border: '2px solid #24124F', overflow: 'hidden' }} role="progressbar" aria-valuemin={0} aria-valuemax={creatures.length} aria-valuenow={found}>
        <div className="bar-fill" style={{ width: `${creatures.length ? Math.round((100 * found) / creatures.length) : 0}%`, height: '100%', background: '#6236E0' }} />
      </div>
      <div style={{ marginTop: 4, fontSize: 13, fontWeight: 700, color: '#5C4A99' }}>{guardian ? t('album.guardian', { name: learnerName }) : t('album.hint', { need })}</div>

      <div className="gw-tabs" role="tablist" aria-label={t('album.tabs')}>
        {TABS.map((key) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => {
              setTab(key);
              sound.play('pop');
            }}
          >
            {key === 'all' ? t('album.all') : t(`album.rarity.${key}`)}
          </button>
        ))}
      </div>
      <div className="gw-chips" role="group" aria-label={t('album.islands')}>
        <button type="button" aria-pressed={!island} onClick={() => setIsland(null)}>
          {t('album.allIslands')}
        </button>
        {islands.map(([key, name]) => (
          <button key={key} type="button" aria-pressed={island === key} onClick={() => setIsland(key)}>
            {name}
          </button>
        ))}
      </div>

      <div className="gw-grid">
        {creatures.map((c, n) => {
          if ((tab !== 'all' && tab !== c.rarity) || (island && island !== c.island)) return null;
          const ring = RING[c.rarity] ?? '#24124F';
          return (
            <button
              key={c.key}
              type="button"
              className="gw-ctile"
              data-found={c.found ? 'yes' : 'no'}
              onClick={() => show(c)}
              aria-label={c.found ? t('album.ariaFound', { name: c.nickname || c.name, island: c.island_name }) : t('album.ariaLocked', { island: c.island_name })}
              style={c.found ? { ['--i' as string]: n, background: hex(c.look.belly, '#FFFFFF'), border: `3px solid ${ring}`, boxShadow: `0 4px 0 ${ring}` } : { ['--i' as string]: n }}
            >
              {c.rarity !== 'common' ? (
                <svg className="gw-shine gw-abs" aria-hidden="true" style={{ top: 6, left: 6 }} width="16" height="16" viewBox="0 0 14 14">
                  <path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill={ring} />
                </svg>
              ) : null}
              {c.buddy ? (
                <span className="gw-buddy-mark" aria-hidden="true">
                  💛
                </span>
              ) : null}
              <span style={{ position: 'relative', display: 'block', width: 84, height: 74 }}>
                <CreatureArt look={c.look} size={84} shadow={!c.found} line="#24124F" />
                {!c.found ? <span className="gw-q">؟</span> : null}
              </span>
              <strong>{c.found ? c.nickname || c.name : t('album.notYet')}</strong>
              <small>{c.island_name}</small>
            </button>
          );
        })}
      </div>

      {toast ? (
        <div className="gw-album-toast gw-pop-in" role="status">
          {toast}
        </div>
      ) : null}

      {chosen ? (
        <>
          <button type="button" className="gw-album-sheet-bg" onClick={() => setOpen(null)} aria-label={t('album.close')} />
          <section className="gw-album-sheet" role="dialog" aria-modal="true" aria-labelledby={`sheet-${chosen.key}`}>
            <div style={{ width: 48, height: 5, borderRadius: 999, background: '#D0C7EE', margin: '0 auto 6px' }} />
            <div style={{ height: 170, borderRadius: 24, display: 'flex', alignItems: 'center', justifyContent: 'center', background: chosen.found ? hex(chosen.look.belly, '#F3F0FF') : '#F8F5FF' }}>
              {chosen.found ? (
                <button type="button" data-first className={singing ? 'gw-sing' : undefined} onClick={() => sing(chosen)} aria-label={t('album.voice')} style={{ background: 'none', border: 0, padding: 0, cursor: 'pointer' }}>
                  <span className="gw-hover" style={{ display: 'block' }}>
                    <CreatureArt look={chosen.look} size={170} motion line="#24124F" />
                  </span>
                </button>
              ) : (
                <span data-first tabIndex={-1} style={{ fontSize: 72, fontWeight: 800, color: '#6236E0' }}>
                  ؟
                </span>
              )}
            </div>
            <div style={{ marginTop: 10, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
              <h2 id={`sheet-${chosen.key}`}>{chosen.found ? chosen.nickname || chosen.name : t('album.notYet')}</h2>
              <span style={{ flex: 'none', padding: '2px 12px', borderRadius: 999, fontSize: 14, fontWeight: 800, border: '2px solid #24124F', background: RARITY_BG[chosen.rarity] ?? '#F1F3F5' }}>
                {t(`album.rarity.${chosen.rarity in RARITY_BG ? chosen.rarity : 'common'}` as 'album.rarity.common')}
              </span>
            </div>
            {chosen.found ? (
              <>
                <p>
                  {chosen.story}
                  {chosen.nickname ? <span style={{ color: '#5C4A99' }}> ({chosen.name})</span> : null}
                  <br />
                  <small style={{ color: '#5C4A99', fontWeight: 700 }}>
                    {chosen.island_name} · {t(`album.from.${chosen.source === 'mastery' ? 'mastery' : 'stars'}`)}
                  </small>
                </p>
                {!guardian ? (
                  <div role="radiogroup" aria-label={t('album.nameIt')} style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
                    <span style={{ fontSize: 14, fontWeight: 800 }} aria-hidden="true">
                      {t('album.nameIt')}
                    </span>
                    {chosen.names.map((name) => (
                      <button
                        key={name}
                        type="button"
                        role="radio"
                        className="gw-name gw-3d"
                        style={{ height: 36, fontSize: 15, padding: '0 12px' }}
                        aria-checked={(chosen.nickname || chosen.name) === name}
                        disabled={pending}
                        onClick={() => rename(chosen, name)}
                      >
                        {name}
                      </button>
                    ))}
                  </div>
                ) : null}
                <div style={{ display: 'flex', gap: 10 }}>
                  <button type="button" className="gw-bigbtn gw-3d" style={{ flex: 1, borderColor: '#24124F', boxShadow: '0 4px 0 #24124F', fontSize: 17 }} onClick={() => sing(chosen)}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24124F" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                      <path d="M9 18V5l12-2v13" />
                      <circle cx="6" cy="18" r="3" />
                      <circle cx="18" cy="16" r="3" />
                    </svg>
                    {t('album.voice')}
                  </button>
                  {!guardian ? (
                    <button
                      type="button"
                      className="gw-bigbtn gw-3d"
                      data-kind="go"
                      style={{ flex: 1, borderColor: '#24124F', boxShadow: '0 4px 0 #24124F', fontSize: 17, color: '#24124F' }}
                      disabled={chosen.buddy || pending}
                      onClick={() => buddy(chosen)}
                    >
                      {chosen.buddy ? t('album.isBuddy') : t('album.buddy')}
                    </button>
                  ) : null}
                </div>
              </>
            ) : (
              <>
                <p>{t('album.lockedStory')}</p>
                <div style={{ padding: '12px 14px', borderRadius: 18, background: '#F3F0FF', border: '2px dashed #9775FA', fontSize: 16, fontWeight: 700 }}>
                  {t('album.how', {
                    how:
                      chosen.rarity === 'legendary'
                        ? t('album.howLegendary', { island: chosen.island_name })
                        : chosen.rarity === 'rare'
                          ? t('album.howRare', { island: chosen.island_name })
                          : t('album.howCommon', { island: chosen.island_name, need }),
                  })}
                </div>
              </>
            )}
          </section>
        </>
      ) : null}
    </div>
  );
}
