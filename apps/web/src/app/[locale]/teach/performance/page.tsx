import { getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { Badge, Card, Empty, Heading, SectionTitle, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { Performance } from '@/lib/teach/types';

const GROUPS = ['commitment', 'quality', 'professionalism', 'business'] as const;

/**
 * The teacher's own numbers over the last 90 days (TeacherKpis, as the academy
 * sees them), what counted against them, and families' ratings without names.
 */
export default async function PerformancePage() {
  const [t, nav, perf] = await Promise.all([getTranslations('teach'), getTranslations('nav'), get<Performance>('/v1/teacher/performance')]);
  return (
    <div>
      <Heading title={nav('performance')} hint={t('perfLead', { days: perf.days })} />
      {GROUPS.map((group) => {
        const kpis = perf.kpis.filter((k) => k.group === group);
        if (kpis.length === 0) return null;
        return (
          <section key={group}>
            <SectionTitle>{t(`group_${group}`)}</SectionTitle>
            <ul className="stagger grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {kpis.map((k) => (
                <li key={k.key} className="rounded-card border border-line bg-white p-4">
                  <div className="flex items-start justify-between gap-2">
                    <p className="text-sm text-muted">{k.label}</p>
                    <Badge tone={k.state === 'good' ? 'brand' : k.state === 'bad' ? 'danger' : 'muted'}>{t(`state_${k.state}`)}</Badge>
                  </div>
                  <p className={join('mt-1 font-heading text-2xl font-bold tabular-nums', k.state === 'bad' && 'text-danger')} dir="ltr">
                    {k.value}
                  </p>
                  <p className="text-xs text-muted">{[k.target, k.hint].filter(Boolean).join(' · ')}</p>
                </li>
              ))}
            </ul>
          </section>
        );
      })}

      <SectionTitle>{t('whatCounted')}</SectionTitle>
      {perf.events.length === 0 ? (
        <Empty>{t('noEvents')}</Empty>
      ) : (
        <Card className="p-0">
          <ul className="divide-y divide-line">
            {perf.events.map((e, i) => (
              <li key={`${e.session}-${e.kind}-${i}`} className="px-4 py-3">
                <div className="flex flex-wrap items-center gap-2">
                  <p className={join('font-semibold', e.excluded && 'text-muted line-through')}>{e.text}</p>
                  {e.excluded ? <Badge>{t('excluded')}</Badge> : null}
                </div>
                <p className="text-sm text-muted">{e.lesson}</p>
                {e.comment ? <p className="mt-1 text-sm">“{e.comment}”</p> : null}
                {e.note ? <p className="mt-1 text-sm text-muted">{e.note}</p> : null}
              </li>
            ))}
          </ul>
        </Card>
      )}

      <SectionTitle trailing={perf.ratings.avg ? t('ratingsAvg', { avg: perf.ratings.avg, n: perf.ratings.count }) : undefined}>{t('ratingsTitle')}</SectionTitle>
      {perf.ratings.list.length === 0 ? (
        <Empty>{t('noRatings')}</Empty>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {perf.ratings.list.map((r, i) => (
            <li key={`${r.lesson}-${i}`} className="rounded-card border border-line bg-white p-4">
              <p className="flex items-center gap-0.5 text-attention" aria-label={t('rate', { n: r.stars })}>
                {[1, 2, 3, 4, 5].map((n) => (
                  <Icon key={n} name="star" size={18} fill={n <= r.stars ? 'currentColor' : 'none'} className={n <= r.stars ? undefined : 'text-line'} />
                ))}
              </p>
              <p className="mt-1 text-xs text-muted">{r.lesson}</p>
              {r.tags.length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {r.tags.map((tag) => (
                    <Badge key={tag}>{tag}</Badge>
                  ))}
                </div>
              ) : null}
              {r.comment ? <p className="mt-2 text-sm">“{r.comment}”</p> : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
