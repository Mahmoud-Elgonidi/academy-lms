'use client';

import { useCallback, useEffect, useRef, useState, type CSSProperties } from 'react';
import { useTranslations } from 'next-intl';
import { Link, useRouter } from '@/i18n/navigation';
import { hex } from '@/lib/games/art';
import { sound } from '@/lib/games/sound';
import type { GameCard, Island, World } from '@/lib/games/types';
import { badgeOf, currentIsland, mapHeight, MAP_WIDTH, residentsOf, SECRET_ISLAND, spotsOf, TILE_PAINT, tileArt, type TileArt } from '@/lib/games/world';
import { CreatureArt, EggIcon, IslandArt, LockIcon, SceneArt, StarIcon } from './art';
import { Done, Hatch } from './results';
import { GameRound } from './round';
import { useMuted } from './use-muted';
import { usePlay } from './use-play';
import './games.css';
import './islands.css';

type View = 'map' | 'zooming' | 'island';

const still = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * A child's islands world (the approved design, Main), as the /my panel and
 * the app draw it: the sea map with its islands (open ones have a game for
 * this look; the current one holds the first quest), the creatures they
 * hatched flying around, and an island opened by zooming in: its scene and
 * a sheet with its games (each says which quest it plays), its creatures and
 * the next egg. A round plays over it; its stars, a creature hatching and
 * the egg come after.
 */
export function KidsWorld({ world, learnerName, openIsland, autoplay }: {
  world: World;
  learnerName: string;
  /** An island to open on arrival (back from the album). */
  openIsland?: string | null;
  /** A quest to start on arrival (a homework's practice). */
  autoplay?: { game: string; quest: string } | null;
}) {
  const t = useTranslations('games');
  const router = useRouter();
  const root = useRef<HTMLDivElement>(null);
  const stage = useRef<HTMLDivElement>(null);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  // Back from the album, its island is open already (no zoom, no sound until the child taps).
  const arriving = openIsland && world.islands.some((i) => i.key === openIsland && i.open) ? openIsland : null;
  const [view, setView] = useState<View>(arriving ? 'island' : 'map');
  const [id, setId] = useState<string | null>(arriving);
  const [zoom, setZoom] = useState(arriving !== null);
  const [reveal, setReveal] = useState(arriving !== null);
  const [wob, setWob] = useState('');
  const [toast, setToast] = useState('');
  const muted = useMuted();
  const [circle, setCircle] = useState({ x: 195, y: 300 });

  const spots = spotsOf(world.islands);
  const height = mapHeight(spots);
  const current = currentIsland(world);
  const may = world.allowance.may;
  const refusal = world.allowance.reason ? t(`refused.${world.allowance.reason}`) : '';
  const fallback = world.islands[0]?.creatures[0]?.look ?? {};

  const later = useCallback((fn: () => void, ms: number) => {
    timers.current.push(setTimeout(fn, still() ? 0 : ms));
  }, []);

  const say = useCallback(
    (text: string) => {
      setToast('');
      later(() => setToast(text), 30);
      clearTimeout(toastTimer.current);
      toastTimer.current = setTimeout(() => setToast(''), 3200);
    },
    [later],
  );

  const play = usePlay(world.learner_id, world.quests, say);

  // Scale the 390-wide pictures to their boxes: the map to the page (--gw-s), an opened island and the hatching to the screen.
  useEffect(() => {
    const el = root.current;
    if (!el) return;
    const fit = () => {
      const map = el.querySelector<HTMLElement>('.gw-map');
      if (map) map.style.setProperty('--gw-s', String(Math.min(map.clientWidth, 520) / MAP_WIDTH));
      el.style.setProperty('--gw-ps', String(Math.min(window.innerWidth, 480) / MAP_WIDTH));
      el.style.setProperty('--gw-hs', String(Math.min(window.innerWidth / 390, window.innerHeight / 844)));
    };
    fit();
    const observer = new ResizeObserver(fit);
    observer.observe(el);
    window.addEventListener('resize', fit);
    return () => {
      observer.disconnect();
      window.removeEventListener('resize', fit);
    };
  }, []);

  useEffect(() => {
    const pending = timers.current;
    return () => {
      pending.forEach(clearTimeout);
      clearTimeout(toastTimer.current);
      document.documentElement.style.overflow = '';
      sound.stop(true);
    };
  }, []);

  const open = useCallback(
    (key: string, quiet = false) => {
      const island = world.islands.find((i) => i.key === key);
      if (!island) return;
      if (!quiet) {
        sound.start();
        sound.play('zoom');
        later(() => sound.cue(island.music), 650);
      }
      sound.scale(island.music);
      setId(key);
      setZoom(true);
      setView('zooming');
      setToast('');
      later(() => {
        // The circle opens where the island now is on the screen.
        const box = stage.current?.getBoundingClientRect();
        const s = box ? box.width / MAP_WIDTH : 1;
        setCircle(box ? { x: box.left + 195 * s, y: box.top + 300 * s } : { x: window.innerWidth / 2, y: window.innerHeight / 2 });
        setView('island');
        setReveal(false);
        later(() => setReveal(true), 40);
      }, quiet ? 0 : 560);
    },
    [world.islands, later],
  );

  const back = useCallback(() => {
    sound.play('back');
    sound.scale('map');
    setReveal(false);
    later(() => {
      setView('map');
      setZoom(false);
      root.current?.querySelector<HTMLElement>(`[data-island="${id}"]`)?.focus({ preventScroll: true });
    }, 520);
  }, [id, later]);

  // Arriving with a homework's practice to play: it starts, and the address forgets it (a refresh does not start it again).
  const arrived = useRef(false);
  useEffect(() => {
    if (arrived.current || !autoplay) return;
    arrived.current = true;
    play.start(autoplay.game, autoplay.quest);
    router.replace('/family/games');
  }, [autoplay, play, router]);

  useEffect(() => {
    if (view !== 'island') return;
    const html = document.documentElement;
    html.style.overflow = 'hidden';
    root.current?.querySelector<HTMLElement>(`[data-phone="${id}"] .gw-back`)?.focus({ preventScroll: true });
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !play.round && !play.played) back();
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      html.style.overflow = '';
    };
  }, [view, id, back, play.round, play.played]);

  const tap = (island: Island) => {
    if (view !== 'map') return;
    if (island.open) return open(island.key);
    sound.start();
    sound.play('locked');
    setWob('');
    later(() => setWob(island.key), 20);
    later(() => setWob(''), 600);
    say(island.key === SECRET_ISLAND ? t('map.secret') : t('map.locked', { island: island.name }));
  };

  const toggleSound = () => {
    if (sound.muted()) sound.setMuted(false);
    else if (!sound.playing()) sound.start();
    else sound.setMuted(true);
  };

  const startGame = (game: GameCard) => {
    if (!may) {
      sound.play('locked');
      return say(refusal);
    }
    if (!game.playable) {
      sound.play('locked');
      return say(t('locked'));
    }
    sound.play('pop');
    sound.stop(false);
    play.start(game.id, game.quest?.key ?? null);
  };

  const leave = () => {
    play.leave();
    sound.start();
  };

  const opened = world.islands.find((i) => i.key === id);
  const zoomed = zoom && opened ? spots[opened.key] : null;
  const worldStyle: CSSProperties = zoomed
    ? { transformOrigin: `${zoomed.cx}px ${zoomed.cy}px`, transform: `translate(${195 - zoomed.cx}px, ${300 - zoomed.cy}px) scale(2.7)` }
    : { transform: 'translate(0px, 0px) scale(1)' };
  const radius = reveal && typeof window !== 'undefined' ? Math.hypot(window.innerWidth, window.innerHeight) * 1.2 : 0;
  const birds = (world.islands.find((i) => i.key === 'birds')?.creatures ?? []).filter((c) => c.found).slice(0, 3);
  const swimmer = world.islands.find((i) => i.key === 'turtles')?.creatures.find((c) => c.found);
  const result = play.played?.result;

  return (
    <div className="gw-kids" ref={root}>
      <div className="gw-map" style={{ ['--gw-h' as string]: `${height}px` }}>
        <div className="gw-stage" ref={stage}>
          <div className="gw-world" style={worldStyle} aria-hidden={view === 'island'}>
            <Sea height={height} />
            {[...world.islands]
              .sort((a, b) => (spots[a.key]?.y ?? 0) - (spots[b.key]?.y ?? 0))
              .map((island) => {
                const spot = spots[island.key]!;
                const found = island.creatures.filter((c) => c.found).length;
                const kind = !island.open ? 'locked' : island.key === current ? 'current' : 'open';
                const aria = island.homework ? t('map.ariaHomework', { island: island.name }) : island.open ? t('map.ariaOpen', { island: island.name }) : t('map.ariaLocked', { island: island.name });
                return (
                  <button
                    key={island.key}
                    type="button"
                    className="gw-isl"
                    data-island={island.key}
                    aria-label={aria}
                    style={{ left: spot.x, top: spot.y, width: spot.w, height: spot.h }}
                    onClick={() => tap(island)}
                  >
                    <span className={wob === island.key ? 'gw-wobble' : `gw-bob ${spot.delay}`}>
                      <IslandArt islandKey={island.key} locked={!island.open} colors={island.colors} emoji={island.emoji} />
                      {kind === 'locked' ? (
                        <span className="gw-label" data-kind="locked">
                          <LockIcon />
                          {island.key === SECRET_ISLAND ? t('map.secretLabel') : island.name}
                        </span>
                      ) : kind === 'current' ? (
                        <span className="gw-label" data-kind="current">
                          {island.name} · {t('map.of', { found, total: island.creatures.length })}
                        </span>
                      ) : (
                        <span className="gw-label" data-kind="open">
                          {island.name}
                          {island.fresh ? <b>{t('map.new')}</b> : null}
                        </span>
                      )}
                      {island.homework ? (
                        <span className="gw-pin">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <path d="M4 20h4L19 9l-4-4L4 16z" />
                            <path d="M14 6l4 4" />
                          </svg>
                          {t('map.homework')}
                        </span>
                      ) : null}
                    </span>
                  </button>
                );
              })}

            {birds.length > 0 ? (
              // The birds the child hatched circle their island.
              <div aria-hidden="true" className="gw-abs" style={{ left: 106, top: 506, width: 24, height: 24, transform: 'scaleY(.42)' }}>
                {birds.map((bird, i) => {
                  const d = ['', 'gw-d2', 'gw-d3'][i];
                  return (
                    <div key={bird.key} className={`gw-orbit ${d} gw-abs`} style={{ inset: 0 }}>
                      <div className="gw-abs" style={{ left: 86, top: -6 }}>
                        <div className={`gw-counter ${d}`}>
                          <div style={{ transform: 'scaleY(2.38)' }}>
                            <CreatureArt look={bird.look} size={[36, 30, 32][i]} motion />
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : null}
            {swimmer ? (
              <div aria-hidden="true" className="gw-abs" style={{ left: 252, top: 524, width: 60, height: 44 }}>
                <span className="gw-swim gw-abs" style={{ left: 0, top: 0 }}>
                  <CreatureArt look={swimmer.look} size={44} motion />
                </span>
              </div>
            ) : null}
            <SeaLife />
          </div>

          <header className="gw-head">
            <div>
              <Link className="gw-avatar" href="/family/games/album" aria-label={t('map.album')}>
                <CreatureArt look={world.buddy?.look ?? fallback} size={40} />
              </Link>
              <span className="gw-title">{t('map.world', { name: learnerName })}</span>
            </div>
            <div>
              <div className="gw-pill" aria-label={t('stars', { n: world.stars })}>
                <StarIcon />
                <span data-count={world.stars}>{world.stars}</span>
              </div>
              <button type="button" className="gw-round-btn gw-3d" onClick={toggleSound} aria-pressed={!muted} aria-label={muted ? t('map.soundOn') : t('map.soundOff')}>
                <MusicIcon off={muted} />
              </button>
            </div>
          </header>

          {toast && view !== 'island' ? (
            <div className="gw-toast gw-pop-in" role="status">
              {toast}
            </div>
          ) : null}
          {!may ? (
            <div className="gw-note-sea" role="status">
              {refusal}
            </div>
          ) : null}
        </div>
      </div>

      {/* An island opened: the circle reveals its scene, the sheet rises with its games. */}
      {view === 'island' && opened ? (
        <div className="gw-open" style={{ clipPath: `circle(${radius}px at ${circle.x}px ${circle.y}px)` }} role="dialog" aria-modal="true" aria-labelledby={`isl-${opened.key}`}>
          <OpenIsland island={opened} world={world} refusal={may ? null : refusal} onBack={back} onPlay={startGame} />
          {toast ? (
            <div className="gw-toast gw-toast-open gw-pop-in" role="status">
              {toast}
            </div>
          ) : null}
        </div>
      ) : null}

      {play.round ? (
        <GameRound
          round={play.round}
          sky={world.islands.find((i) => i.key === play.round?.game.island)?.colors.sky}
          look="kids"
          muted={muted}
          onFinish={play.finish}
          onClose={leave}
        />
      ) : null}
      {result && play.played ? (
        result.creature ? (
          <Hatch played={play.played} islands={world.islands} onLeave={leave} />
        ) : (
          <Done played={play.played} onAgain={play.again} onLeave={leave} />
        )
      ) : null}
    </div>
  );
}

function OpenIsland({ island, world, refusal, onBack, onPlay }: { island: Island; world: World; refusal: string | null; onBack: () => void; onPlay: (game: GameCard) => void }) {
  const t = useTranslations('games');
  const found = island.creatures.filter((c) => c.found);
  const questGame = island.games.find((g) => g.quest);
  const next = island.next;
  return (
    <div className="gw-phone" data-phone={island.key} style={{ background: hex(island.colors.sky, '#8ED8FF') }}>
      <SceneArt islandKey={island.key} residents={residentsOf(island)} colors={island.colors} />
      <button type="button" className="gw-back gw-3d" onClick={onBack}>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M9 5l7 7-7 7" />
        </svg>
        {t('map.back')}
      </button>
      <section className="gw-sheet gw-sheet-in">
        {refusal ? (
          <div className="gw-sheet-note" role="status">
            {refusal}
          </div>
        ) : null}
        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 }}>
          <div style={{ minWidth: 0 }}>
            <h1 id={`isl-${island.key}`}>{island.name}</h1>
            <div className="gw-sheet-sub">{island.story}</div>
          </div>
          <span className="gw-chip-progress">
            {questGame?.quest ? (
              <>
                <span>{questGame.quest.title || t(`reasons.${questGame.quest.reason}`)}</span>
                <span>{t('items', { n: questGame.quest.items })}</span>
              </>
            ) : (
              <>
                <span>{t('map.creatures')}</span>
                <span>{t('map.of', { found: found.length, total: island.creatures.length })}</span>
              </>
            )}
          </span>
        </div>

        <nav aria-label={t('map.games')} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          {island.games.map((g, i) => {
            const art = tileArt(g);
            const [bg, tile] = TILE_PAINT[art];
            const badge = badgeOf(g);
            const what = g.quest ? `${t(`reasons.${g.quest.reason}`)}: ${g.quest.title || g.summary}` : g.playable ? g.summary : t('locked');
            return (
              <button
                key={g.id}
                type="button"
                className={`gw-gamecard gw-rise${g.playable ? ' gw-card3d' : ''}`}
                style={{ ['--i' as string]: i, background: bg, boxShadow: '0 5px 0 #13294B' }}
                aria-disabled={!g.playable}
                onClick={() => onPlay(g)}
              >
                <span className="gw-tile" style={{ background: tile }}>
                  <Tile art={art} />
                </span>
                <span style={{ flex: 1, minWidth: 0 }}>
                  <span style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                    <strong>{g.title}</strong>
                    <span className="gw-badge" data-kind={badge}>
                      {badge === 'stars' ? t('stars', { n: g.best ?? 0 }) : t(`map.badge.${badge}`)}
                    </span>
                  </span>
                  <span className="gw-what">{what}</span>
                </span>
                {g.playable ? (
                  <span className="gw-playdot" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                      <path d="M7 4v16l13-8z" fill="#13294B" />
                    </svg>
                  </span>
                ) : null}
              </button>
            );
          })}
        </nav>

        <div className="gw-minicards gw-rise" style={{ ['--i' as string]: island.games.length }}>
          <Link href={`/family/games/album?island=${encodeURIComponent(island.key)}`} className="gw-card3d" style={{ flex: 1, minWidth: 0, background: '#F3F0FF' }}>
            <span style={{ display: 'block', fontSize: 13, fontWeight: 800 }}>{t('map.creaturesCard', { found: found.length, total: island.creatures.length })}</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 4, marginTop: 4 }}>
              {found.slice(0, 3).map((c) => (
                <CreatureArt key={c.key} look={c.look} size={34} />
              ))}
              {island.creatures.length > found.length ? <span className="gw-more">+{island.creatures.length - found.length}</span> : null}
            </span>
          </Link>
          <Link href="/family/games/album" className="gw-card3d" style={{ flex: 'none', width: 128, background: '#FFF4D6', display: 'flex', alignItems: 'center', gap: 6 }}>
            {next ? (
              <>
                <EggIcon width={30} height={36} belly={hex(next.look.belly, '#F3E3C3')} body={hex(next.look.body, '#E0C79A')} />
                <span style={{ fontSize: 12.5, fontWeight: 800, lineHeight: 1.2 }}>{t('map.eggCard', { have: world.egg.have, need: world.egg.need })}</span>
              </>
            ) : (
              <span style={{ fontSize: 12.5, fontWeight: 800, lineHeight: 1.2 }}>🏆 {t('map.islandDone')}</span>
            )}
          </Link>
        </div>
      </section>
    </div>
  );
}

/** A game's tile picture. */
function Tile({ art }: { art: TileArt }) {
  switch (art) {
    case 'gates':
      return (
        <svg width="50" height="44" viewBox="0 0 50 44" aria-hidden="true">
          <ellipse cx="34" cy="22" rx="10" ry="16" fill="none" stroke="#FFD43B" strokeWidth="5" />
          <g transform="translate(2 12) scale(0.3)">
            <ellipse cx="50" cy="44" rx="30" ry="25" fill="#FFFFFF" stroke="#13294B" strokeWidth="7" />
            <path d="M22 40 L6 46 L22 52 Z" fill="#FF922B" />
            <path d="M48 40 C50 14 80 8 84 24 C78 34 66 42 56 46 Z" fill="#4DABF7" stroke="#13294B" strokeWidth="7" />
          </g>
        </svg>
      );
    case 'balloons':
      return (
        <svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true">
          <ellipse cx="16" cy="18" rx="11" ry="13" fill="#FF6B6B" stroke="#13294B" strokeWidth="2.5" />
          <ellipse cx="32" cy="16" rx="10" ry="12" fill="#38D9A9" stroke="#13294B" strokeWidth="2.5" />
          <path d="M16 31 Q18 38 15 44 M32 28 Q34 36 31 44" stroke="#13294B" strokeWidth="1.6" fill="none" />
        </svg>
      );
    case 'cards':
      return (
        <svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true">
          <g transform="rotate(-12 18 24)">
            <rect x="6" y="8" width="22" height="30" rx="4" fill="#6236E0" stroke="#13294B" strokeWidth="2.5" />
          </g>
          <g transform="rotate(10 30 24)">
            <rect x="20" y="6" width="22" height="30" rx="4" fill="#FFFFFF" stroke="#13294B" strokeWidth="2.5" />
            <path d="M24 16 h14 M24 22 h10" stroke="#13294B" strokeWidth="2.5" strokeLinecap="round" />
          </g>
        </svg>
      );
    case 'bridge':
      return (
        <svg width="50" height="40" viewBox="0 0 90 60" aria-hidden="true">
          <ellipse cx="18" cy="34" rx="12" ry="10" fill="#8CE99A" stroke="#13294B" strokeWidth="3" />
          <ellipse cx="48" cy="34" rx="28" ry="20" fill="#2F9E44" stroke="#13294B" strokeWidth="3" />
          <text x="48" y="41" textAnchor="middle" fontSize="20" fontWeight="800" fill="#FFFFFF">
            م
          </text>
        </svg>
      );
    default:
      return <LockIcon size={26} color="#13294B" strokeWidth={2.6} />;
  }
}

export function MusicIcon({ off }: { off: boolean }) {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M9 18V5l12-2v13" />
      <circle cx="6" cy="18" r="3" fill={off ? 'none' : '#FFD43B'} />
      <circle cx="18" cy="16" r="3" fill={off ? 'none' : '#FFD43B'} />
      {off ? <path d="M3 3l18 18" /> : null}
    </svg>
  );
}

/** The sea under the islands: its shallows, the dotted path from island to island, waves and twinkles. */
function Sea({ height }: { height: number }) {
  return (
    <>
      <svg aria-hidden="true" className="gw-abs" style={{ inset: 0 }} width="390" height={height} viewBox={`0 0 390 ${height}`}>
        <ellipse cx="118" cy="618" rx="128" ry="70" fill="#45ADEE" />
        <ellipse cx="282" cy="478" rx="118" ry="56" fill="#45ADEE" />
        <ellipse cx="108" cy="340" rx="118" ry="62" fill="#45ADEE" />
        <ellipse cx="284" cy="226" rx="110" ry="54" fill="#45ADEE" />
        <ellipse cx="120" cy="164" rx="104" ry="56" fill="#3EA4EA" />
        <path
          d="M150 650 C220 620 270 560 282 506 C270 450 170 420 120 376 C150 300 250 290 282 250 C250 210 180 200 132 186"
          fill="none"
          stroke="#FFFFFF"
          strokeWidth="5"
          strokeLinecap="round"
          strokeDasharray="1 14"
          opacity=".85"
        />
      </svg>
      <div aria-hidden="true">
        {(
          [
            [260, 610, '', 0.6],
            [30, 470, 'gw-d2', 0.6],
            [320, 360, 'gw-d3', 0.6],
            [200, 300, '', 0.5],
            [40, 720, 'gw-d2', 0.55],
            [330, 700, 'gw-d3', 0.55],
            [20, 250, '', 0.5],
          ] as const
        ).map(([x, y, d, o]) => (
          <svg key={`${x}-${y}`} className={`gw-wave ${d} gw-abs`} style={{ left: x, top: y }} width="40" height="12" viewBox="0 0 40 12">
            <path d="M2 8 Q10 0 18 8 T34 8" stroke="#FFFFFF" strokeWidth="3" fill="none" strokeLinecap="round" opacity={o} />
          </svg>
        ))}
        {(
          [
            [230, 410, 12, ''],
            [60, 690, 10, 'gw-d2'],
            [350, 560, 12, 'gw-d3'],
          ] as const
        ).map(([x, y, s, d]) => (
          <svg key={`${x}-${y}`} className={`gw-twinkle ${d} gw-abs`} style={{ left: x, top: y }} width={s} height={s} viewBox="0 0 14 14">
            <path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill="#FFFFFF" />
          </svg>
        ))}
      </div>
    </>
  );
}

/** A fish that jumps, a boat that rocks, and clouds drifting over. */
function SeaLife() {
  const cloud = 'M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z';
  return (
    <>
      <div aria-hidden="true" className="gw-fish gw-abs" style={{ left: 340, top: 620 }}>
        <svg width="24" height="14" viewBox="0 0 24 14">
          <path d="M2 7 C6 1 14 1 18 7 C14 13 6 13 2 7 Z" fill="#FF922B" />
          <path d="M18 7 L24 2 L24 12 Z" fill="#FF922B" />
          <circle cx="6" cy="6" r="1.4" fill="#13294B" />
        </svg>
      </div>
      <div aria-hidden="true" className="gw-boat gw-abs" style={{ left: 236, top: 664 }}>
        <svg width="58" height="54" viewBox="0 0 58 54">
          <path d="M28 4 V40" stroke="#7A4A22" strokeWidth="3" />
          <path d="M30 6 L52 36 L30 36 Z" fill="#FFFFFF" />
          <path d="M26 10 L10 34 L26 34 Z" fill="#FFE066" />
          <path d="M28 2 L38 6 L28 10 Z" fill="#FF6B6B" />
          <path d="M4 40 H54 L46 52 H12 Z" fill="#C9622B" />
          <path d="M4 40 H54" stroke="#8C3F16" strokeWidth="2" />
        </svg>
      </div>
      <div aria-hidden="true">
        <svg className="gw-cloud gw-abs" style={{ left: 0, top: 96, opacity: 0.85 }} width="120" height="48" viewBox="0 0 110 44">
          <path d={cloud} fill="#FFFFFF" />
        </svg>
        <svg className="gw-cloud gw-d2 gw-abs" style={{ left: 0, top: 400, opacity: 0.6 }} width="90" height="36" viewBox="0 0 110 44">
          <path d={cloud} fill="#FFFFFF" />
        </svg>
      </div>
    </>
  );
}
