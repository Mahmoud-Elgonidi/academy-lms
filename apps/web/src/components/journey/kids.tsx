'use client';

import { useCallback, useEffect, useMemo, useRef, useState, type CSSProperties } from 'react';
import { useTranslations } from 'next-intl';
import { Link, usePathname, useRouter } from '@/i18n/navigation';
import { useCourseStateText } from '@/components/family/course-state';
import { openChest } from '@/app/[locale]/family/actions';
import { CreatureArt, EggIcon, IslandArt, LockIcon, SceneArt, StarIcon } from '@/components/games/art';
import { Done, Hatch } from '@/components/games/results';
import { GameRound } from '@/components/games/round';
import { MusicIcon, SeaLife, Tile, Waves } from '@/components/games/scenery';
import { useMuted } from '@/components/games/use-muted';
import { usePlay } from '@/components/games/use-play';
import type { PlayTarget } from '@/lib/games/types';
import { hex } from '@/lib/games/art';
import { sound } from '@/lib/games/sound';
import { residentsOf, TILE_PAINT, tileArt } from '@/lib/games/world';
import {
  courseOf,
  findStation,
  hasHomework,
  hasStars,
  hereIsland,
  homeworkGame,
  islandBefore,
  islandSpots,
  lessonAt,
  markOf,
  missionsOf,
  missionsOn,
  playsOnly,
  SEA_WIDTH,
  seaHeight,
  seaPath,
  wiggle,
} from '@/lib/journey/journey';
import type { HatchedCreature, HomeworkMission, JourneyIsland, JourneyMap, LessonMission, Mission, OpenedChest, Station } from '@/lib/journey/types';
import { ChestOpened, CheersCard, Countdown, EggHatch, LastLessonStars } from './parts';
import '@/components/games/games.css';
import '@/components/games/islands.css';
import './journey.css';

type View = 'map' | 'zooming' | 'island';

const still = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * A child's journey (the kids' look, docs/architecture/02, section 18), as
 * the /my panel and the app draw it: their course's levels as islands on the
 * sea (done ones with a cup, the one they are on with "you're here", the ones
 * ahead under clouds), an island opened by zooming in with its lessons as a
 * path of stops (a flag where homework waits, the lesson's boat at the stop
 * they are on), a stop's sheet with its missions and the games that play its
 * own items, then the missions strip, the egg and the week's chest. A round
 * plays over it; its stars, a creature hatching and the egg come after.
 */
export function KidsJourney({ map, learnerName, autoplay, openIsland }: {
  map: JourneyMap;
  learnerName: string;
  /** A homework's practice to start on arrival (?homework=). */
  autoplay: PlayTarget | null;
  /** An island to open on arrival (back from the album: its art's key). */
  openIsland: string | null;
}) {
  const t = useTranslations('journey');
  const games = useTranslations('games');
  const router = useRouter();
  const path = usePathname();
  const root = useRef<HTMLDivElement>(null);
  const stage = useRef<HTMLDivElement>(null);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const stars = hasStars(map);
  const layer = stars ? map.games : null;
  const [chosen, setChosen] = useState<string | null>(null);
  const course = courseOf(map.courses, chosen);
  // The course on a break (paused until a day), or ending: said on the sea, in a child's words.
  const courseNote = useCourseStateText(course, 'kids');
  const islands = useMemo(() => course?.islands ?? [], [course]);
  const arriving = openIsland ? (islands.find((i) => i.art?.key === openIsland && i.state !== 'ahead') ?? null) : null;
  const [view, setView] = useState<View>(arriving ? 'island' : 'map');
  const [id, setId] = useState<string | null>(arriving?.id ?? null);
  const [zoom, setZoom] = useState<{ y: number } | null>(arriving ? { y: 300 } : null);
  const [reveal, setReveal] = useState(arriving !== null);
  const [circle, setCircle] = useState({ x: 195, y: 300 });
  const [wob, setWob] = useState('');
  const [toast, setToast] = useState('');
  const [sheet, setSheet] = useState<string | null>(null);
  const [hatching, setHatching] = useState(false);
  const [opened, setOpened] = useState<OpenedChest | null>(null);
  const muted = useMuted();

  const chips = map.courses.length > 1;
  const spots = useMemo(() => islandSpots(islands, chips ? 120 : 76), [islands, chips]);
  const height = seaHeight(spots);
  const here = hereIsland(course);
  const may = layer?.allowance.may ?? false;
  const refusal = layer && !layer.allowance.may ? (layer.allowance.reason_label ?? (layer.allowance.reason ? games(`refused.${layer.allowance.reason}`) : '')) : '';
  const missions = missionsOf(map.missions, course?.enrollment_id ?? null);
  const fallbackLook = here?.creatures[0]?.look ?? islands[0]?.creatures[0]?.look ?? {};

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

  const play = usePlay(map.learner_id, say);

  // Scale the 390-wide pictures to their boxes: the map to the page (--gw-s), an opened island and the hatching to the screen.
  useEffect(() => {
    const el = root.current;
    if (!el) return;
    const fit = () => {
      const sea = el.querySelector<HTMLElement>('.gw-map');
      if (sea) sea.style.setProperty('--gw-s', String(Math.min(sea.clientWidth, 520) / SEA_WIDTH));
      el.style.setProperty('--gw-ps', String(Math.min(window.innerWidth, 480) / SEA_WIDTH));
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
    (island: JourneyIsland) => {
      const music = island.art?.music ?? 'major';
      sound.start();
      sound.play('zoom');
      later(() => sound.cue(music), 650);
      sound.scale(music);
      // Zoom towards the island where the screen shows it, then the circle opens there.
      const box = stage.current?.getBoundingClientRect();
      const s = box ? box.width / SEA_WIDTH : 1;
      const y = box ? Math.min(height - 120, Math.max(160, (window.innerHeight / 2 - box.top) / s)) : 300;
      setId(island.id);
      setZoom({ y });
      setView('zooming');
      setToast('');
      later(() => {
        setCircle(box ? { x: box.left + 195 * s, y: box.top + y * s } : { x: window.innerWidth / 2, y: window.innerHeight / 2 });
        setView('island');
        setReveal(false);
        later(() => setReveal(true), 40);
      }, 560);
    },
    [later, height],
  );

  const back = useCallback(() => {
    sound.play('back');
    sound.scale('map');
    setReveal(false);
    later(() => {
      setView('map');
      setZoom(null);
      root.current?.querySelector<HTMLElement>(`[data-island="${id}"]`)?.focus({ preventScroll: true });
    }, 520);
  }, [id, later]);

  // Arriving with a homework's practice to play: it starts, and the address forgets it (a refresh does not start it again).
  const arrived = useRef(false);
  useEffect(() => {
    if (arrived.current || !autoplay) return;
    arrived.current = true;
    play.start(autoplay);
    router.replace(path);
  }, [autoplay, play, router, path]);

  useEffect(() => {
    if (view !== 'island') return;
    const html = document.documentElement;
    html.style.overflow = 'hidden';
    root.current?.querySelector<HTMLElement>('.gw-open .gw-back')?.focus({ preventScroll: true });
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !play.round && !play.played && !sheet && !hatching) back();
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      html.style.overflow = '';
    };
  }, [view, back, play.round, play.played, sheet, hatching]);

  const tap = (island: JourneyIsland) => {
    if (view !== 'map') return;
    if (island.state !== 'ahead') return open(island);
    sound.start();
    sound.play('locked');
    setWob('');
    later(() => setWob(island.id), 20);
    later(() => setWob(''), 600);
    const before = course ? islandBefore(course, island.id) : null;
    say(before ? t('kids.opensAfter', { island: before.title }) : t('kids.ahead'));
  };

  const toggleSound = () => {
    if (sound.muted()) sound.setMuted(false);
    else if (!sound.playing()) sound.start();
    else sound.setMuted(true);
  };

  const start = (target: PlayTarget) => {
    if (!may) {
      sound.play('locked');
      return say(refusal);
    }
    sound.play('pop');
    sound.stop(false);
    play.start(target);
  };

  const leave = () => {
    play.leave();
    sound.start();
  };

  const opened_ = islands.find((i) => i.id === id) ?? null;
  const zoomed = zoom && opened_ ? spots[opened_.id] : null;
  const worldStyle: CSSProperties = zoomed
    ? { transformOrigin: `${zoomed.cx}px ${zoomed.cy}px`, transform: `translate(${195 - zoomed.cx}px, ${zoom!.y - zoomed.cy}px) scale(2.7)` }
    : { transform: 'translate(0px, 0px) scale(1)' };
  const radius = reveal && typeof window !== 'undefined' ? Math.hypot(window.innerWidth, window.innerHeight) * 1.2 : 0;
  const result = play.played?.result;
  const sheetAt = findStation(map, sheet);
  const playingIsland = play.round ? (findStation(map, play.round.target.station ?? null)?.island ?? here) : null;
  const ordered = islands.map((i) => spots[i.id]!).filter(Boolean);
  const buddy = layer?.buddy ?? null;
  const hereSpot = here ? spots[here.id] : null;
  const islandName = (key: string) => map.courses.flatMap((c) => c.islands).find((i) => i.art?.key === key)?.art?.name ?? '';

  return (
    <div className="gw-kids jr-kids" ref={root}>
      <div className="gw-map" style={{ ['--gw-h' as string]: `${height}px` }}>
        <div className="gw-stage" ref={stage}>
          <div className="gw-world" style={worldStyle} aria-hidden={view === 'island'}>
            <svg aria-hidden="true" className="gw-abs" style={{ inset: 0 }} width={SEA_WIDTH} height={height} viewBox={`0 0 ${SEA_WIDTH} ${height}`}>
              {ordered.map((s) => (
                <ellipse key={`${s.x}-${s.y}`} cx={s.cx} cy={s.cy + 18} rx={s.w * 0.62} ry={s.h * 0.36} fill="#45ADEE" />
              ))}
              <path d={seaPath(ordered)} fill="none" stroke="#FFFFFF" strokeWidth="5" strokeLinecap="round" strokeDasharray="1 14" opacity=".85" />
            </svg>
            <Waves height={height} />
            {islands.map((island) => {
              const spot = spots[island.id]!;
              const kind = island.state === 'ahead' ? 'locked' : island.state === 'here' ? 'current' : 'open';
              const homework = island.stations.some(hasHomework);
              const aria = island.state === 'ahead' ? t('kids.ariaAhead', { island: island.title }) : island.state === 'here' ? t('kids.ariaHere', { island: island.title }) : t('kids.ariaDone', { island: island.title });
              return (
                <button
                  key={island.id}
                  type="button"
                  className="gw-isl"
                  data-island={island.id}
                  data-state={island.state}
                  aria-label={aria}
                  style={{ left: spot.x, top: spot.y, width: spot.w, height: spot.h }}
                  onClick={() => tap(island)}
                >
                  <span className={wob === island.id ? 'gw-wobble' : `gw-bob ${spot.delay}`}>
                    <IslandArt islandKey={island.art?.key ?? 'plain'} locked={island.state === 'ahead'} colors={island.art?.colors} emoji={island.art?.emoji} />
                    <span className="gw-label jr-label" data-kind={kind}>
                      {kind === 'locked' ? <LockIcon /> : kind === 'open' ? <span aria-hidden="true">🏆</span> : null}
                      <span className="jr-label-text">{island.title}</span>
                    </span>
                    {island.state === 'here' ? <span className="jr-here">📍 {t('kids.here')}</span> : null}
                    {homework ? (
                      <span className="gw-pin">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                          <path d="M4 20h4L19 9l-4-4L4 16z" />
                          <path d="M14 6l4 4" />
                        </svg>
                        {t('kids.homework')}
                      </span>
                    ) : null}
                  </span>
                </button>
              );
            })}
            {buddy && hereSpot ? (
              // The buddy waits by the island the child is on.
              <span aria-hidden="true" className="gw-bob gw-d2 gw-abs" style={{ left: hereSpot.x < 100 ? hereSpot.x + hereSpot.w - 26 : hereSpot.x - 18, top: hereSpot.y + hereSpot.h - 58 }}>
                <CreatureArt look={buddy.look} size={46} motion />
              </span>
            ) : null}
            <SeaLife height={height} />
          </div>

          <header className="gw-head">
            <div>
              <Link className="gw-avatar" href="/family/games/album" aria-label={t('kids.album')}>
                <CreatureArt look={buddy?.look ?? fallbackLook} size={40} />
              </Link>
              <h1 className="gw-title jr-title">{t('kids.title', { name: learnerName })}</h1>
            </div>
            <div>
              {stars ? (
                <div className="gw-pill" aria-label={t('kids.starsRank', { n: map.stars.total, rank: map.stars.rank.label })}>
                  <StarIcon />
                  <span>{map.stars.total}</span>
                  <span className="jr-rank">{map.stars.rank.label}</span>
                </div>
              ) : null}
              <button type="button" className="gw-round-btn gw-3d" onClick={toggleSound} aria-pressed={!muted} aria-label={muted ? games('map.soundOn') : games('map.soundOff')}>
                <MusicIcon off={muted} />
              </button>
            </div>
          </header>

          {chips ? (
            <div className="jr-chips" role="group" aria-label={t('courses')}>
              {map.courses.map((c) => (
                <button key={c.enrollment_id} type="button" aria-pressed={c.enrollment_id === course?.enrollment_id} onClick={() => setChosen(c.enrollment_id)}>
                  {c.program ?? c.subject}
                </button>
              ))}
            </div>
          ) : null}

          {islands.length === 0 ? (
            <div className="gw-note-sea jr-nomap" role="status">
              {t('noCurriculum')}
            </div>
          ) : null}
          {toast && view !== 'island' ? (
            <div className="gw-toast gw-pop-in" role="status" style={chips ? { top: 118 } : undefined}>
              {toast}
            </div>
          ) : null}
          {layer && !may && refusal ? (
            <div className="gw-note-sea" role="status">
              {refusal}
            </div>
          ) : null}
          {courseNote ? (
            <div className="gw-note-sea" role="status" style={layer && !may && refusal ? { bottom: 64 } : undefined}>
              {courseNote}
            </div>
          ) : null}
        </div>
      </div>

      <div className="jr-below">
        {map.last_lesson ? <LastLessonStars last={map.last_lesson} stars={stars} /> : null}
        <CheersCard cheers={map.cheers} />

        <section aria-labelledby="jr-missions">
          <h2 id="jr-missions" className="jr-h2">
            {t('kids.missions')}
          </h2>
          {missions.length === 0 ? (
            <p className="jr-empty">{t('noMissions')}</p>
          ) : (
            <ul className="jr-strip">
              {missions.map((m, i) => (
                <li key={m.key} style={{ ['--i' as string]: i }}>
                  <KidsMission mission={m} map={map} stars={stars} canPlay={!!layer} onPlay={start} onStation={setSheet} />
                </li>
              ))}
            </ul>
          )}
        </section>

        {stars ? (
          <div className="jr-row">
            {layer ? <Egg egg={layer.egg} nextLook={here?.next_creature?.look ?? null} onHatch={() => setHatching(true)} /> : null}
            {map.chest ? <KidsChest chest={map.chest} onOpened={setOpened} /> : null}
          </div>
        ) : null}
      </div>

      {/* An island opened: the circle reveals its scene, the sheet rises with its stops. */}
      {view === 'island' && opened_ ? (
        <div className="gw-open" style={{ clipPath: `circle(${radius}px at ${circle.x}px ${circle.y}px)` }} role="dialog" aria-modal="true" aria-labelledby={`isl-${opened_.id}`}>
          <OpenIsland island={opened_} missions={map.missions} hasLayer={!!layer} onBack={back} onStation={setSheet} />
          {toast ? (
            <div className="gw-toast gw-toast-open gw-pop-in" role="status">
              {toast}
            </div>
          ) : null}
        </div>
      ) : null}

      {sheetAt ? (
        <KidsStationSheet
          station={sheetAt.station}
          island={sheetAt.island}
          map={map}
          stars={stars}
          canPlay={!!layer}
          refusal={layer && !may ? refusal : null}
          onPlay={start}
          onClose={() => setSheet(null)}
        />
      ) : null}

      {play.round ? (
        <GameRound round={play.round} sky={playingIsland?.art?.colors.sky} look="kids" muted={muted} onFinish={play.finish} onClose={leave} />
      ) : null}
      {result && play.played ? (
        result.creature ? (
          <Hatch creature={result.creature as HatchedCreature} need={result.egg.need} islandName={islandName} onLeave={leave} />
        ) : (
          <Done played={play.played} leave={view === 'island' ? undefined : t('kids.toMap')} onAgain={play.again} onLeave={leave} />
        )
      ) : null}
      {hatching && layer ? <EggHatch map={map} island={here?.art?.key ?? null} onLeave={() => setHatching(false)} /> : null}
      {opened ? <ChestOpened opened={opened} onClose={() => setOpened(null)} /> : null}
    </div>
  );
}

/** An island opened: its scene with the creatures living there, and its lessons as a winding path of stops. */
function OpenIsland({ island, missions, hasLayer, onBack, onStation }: { island: JourneyIsland; missions: Mission[]; hasLayer: boolean; onBack: () => void; onStation: (id: string) => void }) {
  const t = useTranslations('journey');
  const games = useTranslations('games');
  const found = island.creatures.filter((c) => c.found);
  const next = island.next_creature;
  return (
    <div className="gw-phone" style={{ background: hex(island.art?.colors.sky, '#8ED8FF') }}>
      <SceneArt islandKey={island.art?.key ?? 'plain'} residents={residentsOf(island)} colors={island.art?.colors} />
      <button type="button" className="gw-back gw-3d" onClick={onBack}>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M9 5l7 7-7 7" />
        </svg>
        {games('map.back')}
      </button>
      <section className="gw-sheet gw-sheet-in">
        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 }}>
          <div style={{ minWidth: 0 }}>
            <h2 id={`isl-${island.id}`} className="jr-island-title">
              {island.title}
            </h2>
            <div className="gw-sheet-sub">{t('kids.stops', { done: island.done, total: island.lessons })}</div>
          </div>
          <span className="gw-chip-progress">
            <span>{island.art?.name ?? t('kids.level', { n: island.index })}</span>
            <span>{t('percent', { p: island.percent })}</span>
          </span>
        </div>

        <ol className="jr-path" aria-label={t('kids.path')}>
          {island.stations.map((s, i) => {
            const mark = markOf(s);
            const sign = s.unit && s.unit !== island.stations[i - 1]?.unit ? s.unit : null;
            const lesson = mark === 'here' ? lessonAt(missions, s.id) : null;
            return (
              <li key={s.id} style={{ ['--i' as string]: i }}>
                {sign ? <span className="jr-sign">🪧 {sign}</span> : null}
                <span className="jr-stop-row" style={{ transform: `translateX(${wiggle(i)}px)` }}>
                  <button type="button" className="jr-stop gw-3d" data-mark={mark} onClick={() => onStation(s.id)} aria-label={`${s.title}، ${mark === 'here' ? t('kids.here') : t(`mark.${mark}`)}`}>
                    <span aria-hidden="true">{mark === 'done' ? '✓' : mark === 'review' ? '↺' : mark === 'here' ? '📍' : <LockIcon size={20} color="#13294B" strokeWidth={2.6} />}</span>
                    {hasHomework(s) ? (
                      <span className="jr-flag" aria-hidden="true">
                        🚩
                      </span>
                    ) : null}
                  </button>
                  <span className="jr-stop-title" data-mark={mark}>
                    {s.title}
                    {mark === 'here' ? <b>{t('kids.here')}</b> : null}
                  </span>
                </span>
                {lesson ? <Boat lesson={lesson} /> : null}
              </li>
            );
          })}
        </ol>

        {hasLayer ? (
          <div className="gw-minicards gw-rise" style={{ ['--i' as string]: island.stations.length }}>
            <Link href={island.art ? `/family/games/album?island=${encodeURIComponent(island.art.key)}` : '/family/games/album'} className="gw-card3d" style={{ flex: 1, minWidth: 0, background: '#F3F0FF' }}>
              <span style={{ display: 'block', fontSize: 13, fontWeight: 800 }}>{games('map.creaturesCard', { found: found.length, total: island.creatures.length })}</span>
              <span style={{ display: 'flex', alignItems: 'center', gap: 4, marginTop: 4 }}>
                {found.slice(0, 3).map((c) => (
                  <CreatureArt key={c.key} look={c.look} size={34} />
                ))}
                {island.creatures.length > found.length ? <span className="gw-more">+{island.creatures.length - found.length}</span> : null}
              </span>
            </Link>
            {next ? (
              <span className="gw-card3d" style={{ flex: 'none', width: 128, background: '#FFF4D6', display: 'flex', alignItems: 'center', gap: 6, padding: '8px 12px', borderRadius: 20, border: '3px solid #13294B', boxShadow: '0 4px 0 #13294B' }}>
                <EggIcon width={30} height={36} belly={hex(next.look.belly, '#F3E3C3')} body={hex(next.look.body, '#E0C79A')} />
                <span style={{ fontSize: 12.5, fontWeight: 800, lineHeight: 1.2 }}>{t('kids.nextHere')}</span>
              </span>
            ) : null}
          </div>
        ) : null}
      </section>
    </div>
  );
}

/** The lesson's boat at the stop the child is on: the way in once the room opens, else when. */
function Boat({ lesson }: { lesson: LessonMission }) {
  const t = useTranslations('journey');
  return (
    <span className="jr-boat">
      <span aria-hidden="true">⛵</span>
      <span className="jr-boat-text">
        <b>{t('kids.nextLesson')}</b>
        <small>{lesson.starts_at.label}</small>
        {!lesson.join.open ? <Countdown utc={lesson.starts_at.utc} className="jr-count" /> : null}
      </span>
      {lesson.join.open ? (
        <Link href={`/family/lessons/${lesson.lesson_id}`} className="jr-go gw-3d">
          {t('join')}
        </Link>
      ) : null}
    </span>
  );
}

/** A mission card on the strip under the map (the world's look). */
function KidsMission({ mission: m, map, stars, canPlay, onPlay, onStation }: {
  mission: Mission;
  map: JourneyMap;
  stars: boolean;
  canPlay: boolean;
  onPlay: (target: PlayTarget) => void;
  onStation: (id: string) => void;
}) {
  const t = useTranslations('journey');
  if (m.kind === 'lesson') {
    return (
      <article className="jr-mission" data-kind="lesson">
        <span className="jr-mission-icon" aria-hidden="true">
          ⛵
        </span>
        <b>{t('kids.nextLesson')}</b>
        <small>{m.starts_at.label}</small>
        {stars ? <span className="jr-brings">{t('kids.brings', { n: m.stars })}</span> : null}
        {m.join.open ? (
          <Link href={`/family/lessons/${m.lesson_id}`} className="jr-go gw-3d">
            {t('join')}
          </Link>
        ) : (
          <Countdown utc={m.starts_at.utc} className="jr-count" />
        )}
      </article>
    );
  }
  if (m.kind === 'homework') return <KidsHomework mission={m} map={map} stars={stars} canPlay={canPlay} onPlay={onPlay} />;
  return (
    <article className="jr-mission" data-kind="review">
      <span className="jr-mission-icon" aria-hidden="true">
        ↺
      </span>
      <b>{t('reviewOf', { title: m.title ?? '' })}</b>
      <small>{m.kind_label}</small>
      {m.station_id ? (
        <button type="button" className="jr-go gw-3d" onClick={() => onStation(m.station_id!)}>
          {t('review')}
        </button>
      ) : null}
    </article>
  );
}

function KidsHomework({ mission: m, map, stars, canPlay, onPlay }: { mission: HomeworkMission; map: JourneyMap; stars: boolean; canPlay: boolean; onPlay: (target: PlayTarget) => void }) {
  const t = useTranslations('journey');
  const game = canPlay ? homeworkGame(m, map) : null;
  return (
    <article className="jr-mission" data-kind="homework" data-state={m.state}>
      <span className="jr-mission-icon" aria-hidden="true">
        {m.state === 'done' ? '✅' : '📝'}
      </span>
      <b>{m.title}</b>
      <small>{m.state === 'done' ? t('handedIn') : m.due_at ? t('dueBy', { when: m.due_at.label }) : m.kind_label}</small>
      {m.result ? <small className="jr-result">{t('practiceResult', { correct: m.result.correct, total: m.result.total, stars: m.result.stars })}</small> : null}
      {stars && m.state !== 'done' ? <span className="jr-brings">{t('kids.brings', { n: m.stars })}</span> : null}
      {m.state !== 'done' ? (
        <span className="jr-actions">
          {game ? (
            <button type="button" className="jr-go gw-3d" onClick={() => onPlay({ game, homework: m.homework_id })}>
              {t('play')}
            </button>
          ) : null}
          {!playsOnly(m) ? (
            <Link href={`/family/homework#hw-${m.homework_id}`} className="jr-go gw-3d" data-kind={game ? 'quiet' : undefined}>
              {t('handIn')}
            </Link>
          ) : null}
        </span>
      ) : null}
    </article>
  );
}

/** A stop's sheet in the world's look: the lesson, its state, its missions, the race and its games. */
function KidsStationSheet({ station, island, map, stars, canPlay, refusal, onPlay, onClose }: {
  station: Station;
  island: JourneyIsland;
  map: JourneyMap;
  stars: boolean;
  canPlay: boolean;
  refusal: string | null;
  onPlay: (target: PlayTarget) => void;
  onClose: () => void;
}) {
  const t = useTranslations('journey');
  const common = useTranslations('common');
  const close = useRef<HTMLButtonElement>(null);
  const mark = markOf(station);
  const missions = missionsOn(map.missions, station.id);
  const games = canPlay && station.playable ? station.games : [];
  useEffect(() => {
    close.current?.focus({ preventScroll: true });
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);
  return (
    <>
      <button type="button" className="jr-sheet-bg" onClick={onClose} aria-label={common('close')} tabIndex={-1} />
      <section className="jr-sheet gw-kids" role="dialog" aria-modal="true" aria-labelledby="jr-stop">
        <div className="jr-grab" aria-hidden="true" />
        <header className="jr-sheet-head">
          <div style={{ minWidth: 0 }}>
            <small>{station.unit ?? island.title}</small>
            <h2 id="jr-stop">{station.title}</h2>
            <span className="jr-mark" data-mark={mark}>
              {mark === 'done' ? '✓ ' : mark === 'review' ? '↺ ' : mark === 'here' ? '📍 ' : '🔒 '}
              {mark === 'here' ? t('kids.here') : t(`mark.${mark}`)}
            </span>
          </div>
          <button ref={close} type="button" className="gw-round-btn gw-3d" onClick={onClose} aria-label={common('close')}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="3.2" strokeLinecap="round" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </header>

        {missions.length > 0 ? (
          <ul className="jr-sheet-missions">
            {missions.map((m) => (
              <li key={m.key}>
                {m.kind === 'homework' ? (
                  <KidsHomework mission={m} map={map} stars={stars} canPlay={canPlay} onPlay={onPlay} />
                ) : m.kind === 'lesson' ? (
                  <article className="jr-mission" data-kind="lesson">
                    <span className="jr-mission-icon" aria-hidden="true">
                      ⛵
                    </span>
                    <b>{t('kids.nextLesson')}</b>
                    <small>{m.starts_at.label}</small>
                    {m.join.open ? (
                      <Link href={`/family/lessons/${m.lesson_id}`} className="jr-go gw-3d">
                        {t('join')}
                      </Link>
                    ) : (
                      <Countdown utc={m.starts_at.utc} className="jr-count" />
                    )}
                  </article>
                ) : null}
              </li>
            ))}
          </ul>
        ) : null}

        {station.race && stars ? (
          <p className="jr-race">
            🏁 {t('kids.race', { rounds: station.race.rounds })}
            {station.race.best_seconds !== null ? ` · ${t('kids.raceBest', { s: station.race.best_seconds })}` : ''}
          </p>
        ) : null}

        {refusal && games.length > 0 ? (
          <div className="gw-sheet-note" role="status">
            {refusal}
          </div>
        ) : null}
        {games.length > 0 ? (
          <nav aria-label={t('games')} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {games.map((g, i) => {
              const art = tileArt({ key: g.key, kinds: g.kinds, playable: true });
              const [bg, tile] = TILE_PAINT[art];
              return (
                <button
                  key={g.id}
                  type="button"
                  className="gw-gamecard gw-rise gw-card3d"
                  style={{ ['--i' as string]: i, background: bg, boxShadow: '0 5px 0 #13294B' }}
                  aria-disabled={!!refusal}
                  onClick={() => onPlay({ game: g.id, station: station.id, bestBefore: station.race?.best_seconds ?? null })}
                >
                  <span className="gw-tile" style={{ background: tile }}>
                    <Tile art={art} />
                  </span>
                  <span style={{ flex: 1, minWidth: 0 }}>
                    <span style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                      <strong>{g.title}</strong>
                      {g.chosen ? (
                        <span className="gw-badge" data-kind="homework">
                          {t('kids.homework')}
                        </span>
                      ) : null}
                    </span>
                    <span className="gw-what">{g.summary}</span>
                  </span>
                  <span className="gw-playdot" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24">
                      <path d="M7 4v16l13-8z" fill="#13294B" />
                    </svg>
                  </span>
                </button>
              );
            })}
          </nav>
        ) : null}
        {mark === 'todo' && !station.playable ? <p className="jr-note">🔒 {t('stationAhead')}</p> : null}
        {canPlay && station.playable && games.length === 0 ? <p className="jr-note">{t('noGames')}</p> : null}
      </section>
    </>
  );
}

/** The egg the journey's stars fill: wobbling when ready (tap to hatch), else how full. */
function Egg({ egg, nextLook, onHatch }: { egg: { have: number; need: number; ready: boolean }; nextLook: JourneyIsland['creatures'][number]['look'] | null; onHatch: () => void }) {
  const t = useTranslations('journey');
  const games = useTranslations('games');
  if (egg.ready) {
    return (
      <button type="button" className="jr-egg gw-3d" data-ready="yes" onClick={onHatch}>
        <span className="gw-egg-wob" aria-hidden="true">
          <EggIcon width={44} height={54} belly={hex(nextLook?.belly, '#F3E3C3')} body={hex(nextLook?.body, '#E0C79A')} />
        </span>
        <span>
          <b>{t('kids.eggReady')}</b>
          <small>{t('kids.eggTap')}</small>
        </span>
      </button>
    );
  }
  const pct = Math.round((100 * egg.have) / Math.max(1, egg.need));
  return (
    <div className="jr-egg" data-ready="no">
      <EggIcon width={34} height={42} />
      <span style={{ flex: 1, minWidth: 0 }}>
        <b>{games('map.eggCard', { have: egg.have, need: egg.need })}</b>
        <i role="progressbar" aria-valuemin={0} aria-valuemax={egg.need} aria-valuenow={egg.have} aria-label={games('map.eggCard', { have: egg.have, need: egg.need })}>
          <b style={{ width: `${pct}%` }} />
        </i>
      </span>
    </div>
  );
}

/** The week's chest in the world's look: the week's missions done, then wobbling to open (its stars and a sticker). */
function KidsChest({ chest, onOpened }: { chest: NonNullable<JourneyMap['chest']>; onOpened: (opened: OpenedChest) => void }) {
  const t = useTranslations('journey');
  const common = useTranslations('common');
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState('');
  const key = useRef<string | null>(null);
  const title = t('kids.chest');
  const ready = chest.state === 'done' && chest.can_open;
  const open = async () => {
    if (pending) return;
    setPending(true);
    setMessage('');
    key.current ??= crypto.randomUUID();
    const done = await openChest(chest.period, key.current);
    setPending(false);
    if (done.ok && done.opened) {
      sound.play('win');
      onOpened(done.opened);
    } else setMessage(done.message ?? common('problem'));
  };
  const pct = chest.target > 0 ? Math.round((100 * chest.progress) / chest.target) : 0;
  const text =
    chest.state === 'opened'
      ? chest.sticker
        ? t('chestOpenedSticker', { name: chest.sticker.name })
        : t('chestOpened')
      : chest.state === 'missed'
        ? t('chestMissed')
        : chest.state === 'done'
          ? t('chestReady', { n: chest.stars })
          : t('chestProgress', { done: chest.progress, total: chest.target });
  const body = (
    <>
      <span className={ready ? 'jr-chest-icon gw-egg-wob' : 'jr-chest-icon'} aria-hidden="true">
        {chest.state === 'opened' ? '🎁' : '🧰'}
      </span>
      <span style={{ flex: 1, minWidth: 0 }}>
        <b>{title}</b>
        <small>{text}</small>
        {chest.state === 'active' ? (
          <i role="progressbar" aria-valuemin={0} aria-valuemax={chest.target} aria-valuenow={chest.progress} aria-label={text}>
            <b style={{ width: `${pct}%` }} />
          </i>
        ) : null}
        {message ? <small role="alert">{message}</small> : null}
      </span>
    </>
  );
  return ready ? (
    <button type="button" className="jr-chest gw-3d" data-state={chest.state} onClick={open} disabled={pending} aria-label={`${title}: ${t('openChest')}`}>
      {body}
    </button>
  ) : (
    <div className="jr-chest" data-state={chest.state}>
      {body}
    </div>
  );
}
