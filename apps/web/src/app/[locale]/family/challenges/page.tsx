import { getTranslations } from 'next-intl/server';
import { ChallengeBoard, ChallengeEnd, CheerOn, CheersReceived } from '@/components/family/challenges';
import { Icon, type IconName } from '@/components/icons';
import { Empty, Heading, Progress, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { Challenge, Challenges } from '@/lib/family/types';

const ICONS: Record<string, IconName> = {
  calendar: 'calendar',
  homework: 'book',
  clock: 'clock',
  star: 'star',
  bolt: 'bolt',
  trophy: 'trophy',
  crown: 'crown',
  chest: 'chest',
};

/** A sticker's picture where we draw one; the rest are stars. Colours stay the academy's tokens. */
const STICKER_ICONS: Record<string, IconName> = { sun: 'sun', crown: 'crown', trophy: 'trophy', rocket: 'send', tree: 'spark', balloon: 'heart', castle: 'flag', kite: 'map' };
const STICKER_TONES = ['bg-reward', 'bg-brand', 'bg-info', 'bg-attention', 'bg-danger'];

function toneOf(key: string) {
  let n = 0;
  for (const ch of key) n = (n + ch.charCodeAt(0)) % STICKER_TONES.length;
  return STICKER_TONES[n]!;
}

/** Whole days left in the challenges' week (it ends on Saturday, on the student's clock). */
function daysUntil(utc: string) {
  return Math.max(0, Math.floor((Date.parse(utc) - Date.now()) / 86_400_000));
}

/**
 * The week's challenges in the learner's own stage (missions for kids, quests
 * in middle school, goals for teens): what is done, what is left, and the
 * rewards to collect (the student only). A guardian follows and cheers.
 */
export default async function ChallengesPage() {
  const [t, fam] = await Promise.all([getTranslations('family'), family()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const { data } = await get<{ data: Challenges }>('/v1/learning/challenges', { query: { learner_id: learner.id } });
  const self = data.can_claim;
  const titles: Record<string, string> = { kids: t('missionsTitle'), middle: t('questsTitle'), teen: t('goalsTitle') };
  const title = self ? (titles[data.stage] ?? (await getTranslations('nav.classic'))('challenges')) : t('challengesOf', { name: learner.name });

  const groups: { group: string; items: Challenge[] }[] = [];
  for (const c of data.challenges) {
    const last = groups.at(-1);
    if (last?.group === c.group) last.items.push(c);
    else groups.push({ group: c.group, items: [c] });
  }
  const ready = data.challenges.filter((c) => c.claimable).length;

  return (
    <ChallengeBoard>
      <Heading title={title} hint={t('daysLeft', { n: daysUntil(data.week.ends_at.utc) })} />
      {self ? <CheersReceived cheers={data.cheers} /> : null}

      <section className="appear rounded-card border border-line bg-white p-5" aria-label={self ? t('points', { n: data.points }) : t('learnerPoints', { name: learner.name })}>
        <div className="flex flex-wrap items-center gap-3">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-reward-soft px-3 py-1 font-heading text-xl font-bold tabular-nums">
            <Icon name={data.stage === 'middle' ? 'bolt' : 'star'} size={20} fill="var(--color-reward)" className="text-reward" />
            {data.points.toLocaleString()}
          </span>
          <span className="rounded-full bg-brand-soft px-3 py-1 text-sm font-semibold text-brand-strong">{data.rank.label}</span>
          {ready > 0 && self ? <span className="pop ms-auto text-sm font-semibold text-ink">{t('rewardReady', { n: ready })}</span> : null}
        </div>
        {data.rank.next !== null && data.rank.next_label ? (
          <div className="mt-4">
            <Progress value={data.rank.progress} label={t('pointsTo', { rank: data.rank.next_label })} />
            <p className="mt-1.5 text-sm text-muted">{t('nextRank', { n: data.rank.next - data.points, rank: data.rank.next_label })}</p>
          </div>
        ) : (
          <p className="mt-3 text-sm font-semibold text-brand-strong">{t('topRank')}</p>
        )}
      </section>

      {groups.length === 0 ? (
        <div className="mt-6">
          <Empty>{t('noChallenges')}</Empty>
        </div>
      ) : (
        groups.map(({ group, items }) => (
          <section key={group} className="mt-6" aria-labelledby={`group-${group}`}>
            <h2 id={`group-${group}`} className="mb-2 text-sm font-semibold text-muted">
              {group === 'world' && items[0]?.world ? `${t('group_world')} · ${items[0].world.title}` : t(`group_${group}` as 'group_week')}
            </h2>
            <ul className="stagger space-y-2.5">
              {items.map((c) => (
                <ChallengeRow key={`${c.key}-${c.period}`} challenge={c} canClaim={self} kids={data.stage === 'kids'} />
              ))}
            </ul>
          </section>
        ))
      )}

      {data.stickers ? (
        <section className="mt-8 rounded-card border border-line bg-white p-5" aria-labelledby="album">
          <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="album" className="font-heading text-lg font-bold">
              {t('album')}
            </h2>
            <p className="text-sm text-muted">{t('albumLead', { earned: data.stickers.earned, total: data.stickers.items.length })}</p>
          </div>
          <ul className="grid grid-cols-4 gap-3 sm:grid-cols-6">
            {data.stickers.items.map((s, i) => (
              <li key={s.key} className="grid justify-items-center gap-1 text-center">
                {s.earned ? (
                  <span
                    className={join('grid size-14 place-items-center rounded-full text-white shadow-[0_0_0_3px_white,0_4px_10px_-4px_rgb(0_0_0/0.35)]', toneOf(s.key))}
                    style={{ transform: `rotate(${((i % 5) - 2) * 4}deg)` }}
                  >
                    <Icon name={STICKER_ICONS[s.key] ?? 'star'} size={28} />
                  </span>
                ) : (
                  <span className="grid size-14 place-items-center rounded-full border-2 border-dashed border-line bg-paper font-heading text-xl font-bold text-muted" aria-hidden="true">
                    ?
                  </span>
                )}
                <span className={join('text-xs', s.earned ? 'font-semibold' : 'text-muted')}>{s.earned ? s.name : '—'}</span>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {!self ? <CheerOn learnerId={learner.id} name={learner.name} left={data.cheers_left ?? 0} /> : null}
    </ChallengeBoard>
  );
}

function ChallengeRow({ challenge: c, canClaim, kids }: { challenge: Challenge; canClaim: boolean; kids: boolean }) {
  const boss = c.group === 'world';
  const golden = c.state === 'done' || c.state === 'claimed';
  const percent = c.target ? Math.min(100, Math.round((c.progress * 100) / c.target)) : 0;
  return (
    <li
      className={join(
        'grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-card border p-4',
        boss ? 'border-brand-strong bg-brand-strong text-white' : c.state === 'done' ? 'border-reward bg-reward-soft' : 'border-line bg-white',
        c.state === 'missed' && 'opacity-75',
      )}
    >
      <span
        className={join(
          'grid place-items-center',
          kids ? 'size-12 rounded-full' : 'size-11 rounded-control',
          golden ? 'bg-reward text-white' : c.state === 'missed' ? 'bg-paper text-muted' : boss ? 'bg-white/15 text-white' : 'bg-brand-soft text-brand-strong',
        )}
      >
        <Icon name={ICONS[c.icon] ?? 'star'} size={24} />
      </span>
      <div className="min-w-0">
        <p className="font-semibold">{c.title}</p>
        {c.hint ? <p className={join('text-sm', boss ? 'text-white/75' : 'text-muted')}>{c.hint}</p> : null}
        <span className={join('mt-2 block h-2 overflow-hidden rounded-full', boss ? 'bg-white/20' : 'bg-line/60')} role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100} aria-label={c.title}>
          <span className={join('bar-fill block h-full rounded-full', golden ? 'bg-reward' : boss ? 'bg-danger' : 'bg-brand')} style={{ width: `${percent}%` }} />
        </span>
      </div>
      <div className={c.claimable ? 'col-span-3 sm:col-span-1' : undefined}>
        <ChallengeEnd challenge={c} canClaim={canClaim} dark={boss} />
      </div>
    </li>
  );
}
