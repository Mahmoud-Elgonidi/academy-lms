'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { useRouter } from '@/i18n/navigation';
import { sound } from '@/lib/games/sound';
import type { Quest, World } from '@/lib/games/types';
import { CONSTELLATION, litStars, raceAt, raceLength } from '@/lib/games/world';
import { CreatureArt } from './art';
import { useStarSounds } from './results';
import { GameRound } from './round';
import { useMuted } from './use-muted';
import { usePlay, type Played } from './use-play';
import './games.css';
import './islands.css';

/**
 * The games for middle school, secondary school and adult students (the
 * approved designs, Middle and Teen), as the /my panel and the app show
 * them: one dark screen in their look. Middle school races itself on the
 * first quest (the last time against the best, the buddy as the racer);
 * teens and adults review on a constellation that the week's stars light
 * up. Then the other quests, every island's games, and the last rounds.
 */
export function Hub({ world, look, autoplay }: { world: World; look: 'middle' | 'teen' | 'classic'; autoplay?: { game: string; quest: string } | null }) {
  const t = useTranslations('games');
  const router = useRouter();
  const [note, setNote] = useState('');
  const say = useCallback((text: string) => setNote(text), []);
  const play = usePlay(world.learner_id, world.quests, say);
  const muted = useMuted();
  const race = look === 'middle';
  const quest = world.quests[0] ?? null;
  const may = world.allowance.may;
  const buddy = world.buddy?.look ?? world.islands[0]?.creatures[0]?.look ?? {};
  const secs = (s: number | null) => (s === null ? '—' : t('hub.seconds', { s }));
  const questTitle = (q: Quest) => q.title || t(`reasons.${q.reason}`);
  // The week's stars, counted when the page was drawn (it refreshes after every round).
  const [now] = useState(() => Date.now());
  const lit = litStars(world.history, now);

  const arrived = useRef(false);
  useEffect(() => {
    if (arrived.current || !autoplay) return;
    arrived.current = true;
    play.start(autoplay.game, autoplay.quest);
    router.replace('/family/games');
  }, [autoplay, play, router]);

  const start = (game: string, questKey: string | null) => {
    setNote('');
    sound.play('pop');
    play.start(game, questKey);
  };

  return (
    <div className="gw-hub" data-look={look}>
      <header className="gw-hub-head">
        <div style={{ minWidth: 0 }}>
          <div className="gw-hub-kicker">{race ? (quest ? t('hub.from', { what: questTitle(quest) }) : t(`title.${look}`)) : t('hub.review', { n: 5 })}</div>
          <h1>{race ? t('hub.race') : quest ? questTitle(quest) : t(`title.${look}`)}</h1>
        </div>
        {race ? (
          <div className="gw-hub-pill" aria-label={t('stars', { n: world.stars })} dir="ltr">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" />
            </svg>
            <span data-count={world.stars}>{world.stars}</span>
          </div>
        ) : (
          <div className="gw-hub-pill" aria-label={t('hub.streakLabel', { n: world.streak })}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={{ color: 'var(--hb-accent)' }}>
              <path d="M13 2L4 14h7l-1 8 9-12h-7z" />
            </svg>
            {t('hub.streak', { n: world.streak })}
          </div>
        )}
      </header>

      {!may && world.allowance.reason ? (
        <p className="gw-hub-note" role="status">
          {t(`refused.${world.allowance.reason}`)}
        </p>
      ) : null}
      {note ? (
        <p className="gw-hub-note" role="status">
          {note}
        </p>
      ) : null}

      {play.played ? <Over played={play.played} race={race} onAgain={play.again} onLeave={play.leave} /> : null}

      {quest === null ? (
        <p className="gw-hub-card gw-hub-sub">{t('hub.none')}</p>
      ) : race ? (
        <Race quest={quest} buddy={buddy} may={may} onGo={() => start(quest.game.id, quest.key)} />
      ) : (
        <>
          <section className="gw-hub-card" aria-labelledby="gw-const">
            <div className="gw-hub-ends" style={{ alignItems: 'baseline' }}>
              <h2 id="gw-const">{t('hub.constellation')}</h2>
              <b style={{ color: 'var(--hb-accent)' }}>{t('hub.lit', { lit, max: CONSTELLATION.length })}</b>
            </div>
            <div role="img" aria-label={t('hub.lit', { lit, max: CONSTELLATION.length })} className="gw-const">
              <svg viewBox="0 0 334 160" width="100%" height="160" aria-hidden="true" preserveAspectRatio="xMidYMid meet">
                {CONSTELLATION.slice(0, -1).map(([x, y], i) => (
                  <line key={`l${i}`} x1={x} y1={y} x2={CONSTELLATION[i + 1]![0]} y2={CONSTELLATION[i + 1]![1]} stroke={i + 1 < lit ? 'var(--hb-accent)' : '#263248'} strokeWidth="2" />
                ))}
                {CONSTELLATION.map(([x, y], i) => (
                  <circle key={`c${i}`} cx={x} cy={y} r={i < lit ? 6 : 3.5} fill={i < lit ? 'var(--hb-accent)' : '#475569'} className={i < lit ? 'gw-lit' : undefined} style={{ ['--i' as string]: i }} />
                ))}
              </svg>
            </div>
            <div className="gw-hub-sub">{t('hub.litHint')}</div>
          </section>
          <section className="gw-hub-deck">
            <span className="gw-hub-kicker" style={{ color: 'var(--hb-alt)' }}>
              {t(`reasons.${quest.reason}`)}
            </span>
            <strong>{questTitle(quest)}</strong>
            <span className="gw-hub-sub">{t('hub.deck', { items: quest.items, game: quest.game.title })}</span>
            <button type="button" className="gw-hub-go" onClick={() => start(quest.game.id, quest.key)} disabled={!may || play.pending}>
              {t('hub.startReview')}
            </button>
          </section>
        </>
      )}

      {world.quests.length > 1 ? (
        <>
          <h2 className="gw-hub-h2">{t('hub.more')}</h2>
          <div className="gw-hub-list stagger">
            {world.quests.slice(1).map((q) => (
              <button key={q.key} type="button" className="gw-hub-item" onClick={() => start(q.game.id, q.key)} disabled={!may || play.pending}>
                <span className="gw-hub-tag" data-reason={q.reason}>
                  {t(`reasons.${q.reason}`)}
                </span>
                <strong>{questTitle(q)}</strong>
                <small>
                  {q.game.title} · {t('items', { n: q.items })}
                  {q.best_seconds !== null ? ` · ${t('hub.best', { s: secs(q.best_seconds) })}` : null}
                </small>
              </button>
            ))}
          </div>
        </>
      ) : null}

      <h2 className="gw-hub-h2">{t('hub.games')}</h2>
      <div className="gw-hub-games stagger">
        {world.islands.flatMap((island) =>
          island.games.map((g) => (
            <button key={g.id} type="button" className="gw-hub-game" onClick={() => start(g.id, null)} disabled={!may || !g.playable || play.pending}>
              <span className="gw-hub-emoji" aria-hidden="true">
                {island.emoji}
              </span>
              <strong>{g.title}</strong>
              <small>{g.playable ? (g.plays ? '★'.repeat(g.best ?? 0) + '☆'.repeat(3 - (g.best ?? 0)) : t('map.badge.new')) : t('locked')}</small>
            </button>
          )),
        )}
      </div>

      {world.recent.length > 0 ? (
        <>
          <h2 className="gw-hub-h2">{t('hub.recent')}</h2>
          <ul className="gw-hub-recent">
            {world.recent.map((r, i) => (
              <li key={`${r.at?.utc ?? ''}-${i}`}>
                <span>{r.game}</span>
                <b aria-label={t('stars', { n: r.stars })}>
                  {'★'.repeat(r.stars)}
                  {'☆'.repeat(3 - r.stars)}
                </b>
                <small>
                  {t('result.score', { correct: r.correct, total: r.total })}
                  {r.at ? ` · ${r.at.label}` : null}
                </small>
              </li>
            ))}
          </ul>
        </>
      ) : null}

      {play.round ? <GameRound round={play.round} look={look} muted={muted} onFinish={play.finish} onClose={play.leave} /> : null}
    </div>
  );
}

/** The race against yourself: the best time as a ghost, the last as the buddy, and the time bar. */
function Race({ quest, buddy, may, onGo }: { quest: Quest; buddy: World['islands'][number]['creatures'][number]['look']; may: boolean; onGo: () => void }) {
  const t = useTranslations('games');
  const best = quest.best_seconds;
  const last = quest.last_seconds;
  const length = raceLength(best, last);
  const secs = (s: number | null) => (s === null ? '—' : t('hub.seconds', { s }));
  return (
    <>
      <section className="gw-hub-card" aria-label={t('hub.race')}>
        <div className="gw-hub-ends">
          <span>{t('hub.start')}</span>
          <span>{t('hub.finish')}</span>
        </div>
        <div className="gw-track">
          <i style={{ top: 30 }} />
          <i style={{ top: 94 }} />
          <svg aria-hidden="true" className="gw-flag" width="22" height="120" viewBox="0 0 22 120">
            <path d="M4 0 V120" stroke="#FFFFFF" strokeWidth="3" />
            <path d="M6 4 h14 v8 h-14 z M6 12 h7 v8 h-7 z M13 12 h7 v8 h-7 z" fill="#FFFFFF" />
            <path d="M13 4 h7 v8 h-7 z M6 12 h7 v8 h-7 z" fill="var(--hb-bg)" />
          </svg>
          {best !== null ? (
            <div className="gw-ghost" style={{ left: `${raceAt(best, length)}%` }}>
              <CreatureArt look={buddy} size={56} shadow />
              <small>{t('hub.bestGhost')}</small>
            </div>
          ) : null}
          <div className="gw-racer" style={{ left: `${raceAt(last, length)}%` }}>
            <div className="gw-float">
              <CreatureArt look={buddy} size={60} motion line="#0B0726" />
            </div>
            <small>{t('hub.you')}</small>
          </div>
        </div>
        <div className="gw-timebar">
          <div role="img" aria-label={secs(last)}>
            <b style={{ width: `${last === null ? 100 : Math.round(100 * Math.min(1, last / length))}%` }} />
          </div>
          <span dir="ltr">{secs(last ?? best)}</span>
        </div>
      </section>
      <section className="gw-hub-card gw-hub-ready">
        <p>{t('hub.ready', { items: t('items', { n: quest.items }), title: quest.title || t(`reasons.${quest.reason}`) })}</p>
        <p className="gw-hub-big">{best === null ? t('hub.firstTime') : t('hub.best', { s: secs(best) })}</p>
        <button type="button" className="gw-hub-go" onClick={onGo} disabled={!may}>
          {t('hub.go')}
        </button>
      </section>
    </>
  );
}

/** A round's result on the dark screen: the record (or how close), the stars, a creature hatched, homework handed in, the egg. */
function Over({ played, race, onAgain, onLeave }: { played: Played; race: boolean; onAgain: () => void; onLeave: () => void }) {
  const t = useTranslations('games');
  const r = played.result;
  const again = useRef<HTMLButtonElement>(null);
  useStarSounds(r.stars);
  useEffect(() => again.current?.focus({ preventScroll: true }), []);
  const before = played.bestBefore;
  const kicker = before === null ? t('hub.first') : r.seconds !== null && r.seconds < before ? t('hub.beat') : t('hub.close');
  return (
    <section className="gw-hub-over" role="status">
      <div className="gw-hub-over-kicker">{kicker}</div>
      <h2>{t('hub.over', { correct: r.correct, total: r.total, s: r.seconds === null ? '—' : t('hub.seconds', { s: r.seconds }) })}</h2>
      <div className="gw-hub-stars" role="img" aria-label={t('stars', { n: r.stars })}>
        {[1, 2, 3].map((i) => (
          <span key={i} data-on={i <= r.stars ? 'yes' : 'no'} style={{ ['--i' as string]: i - 1 }} aria-hidden="true">
            ★
          </span>
        ))}
      </div>
      {r.creature ? (
        <p className="gw-hub-hatch">
          <CreatureArt look={r.creature.look} size={40} motion /> {t('hatched', { name: r.creature.name })}
        </p>
      ) : null}
      {r.homework?.handed_in ? <p>✅ {t('homeworkDone', { title: r.homework.title })}</p> : null}
      <p className="gw-hub-sub">{t('egg', { have: r.egg.have, need: r.egg.need })}</p>
      <div className="gw-hub-row">
        <button ref={again} type="button" className="gw-hub-go" onClick={onAgain}>
          {race ? t('again') : t('hub.again')}
        </button>
        <button type="button" className="gw-hub-ghostbtn" onClick={onLeave}>
          {t('hub.dismiss')}
        </button>
      </div>
    </section>
  );
}
