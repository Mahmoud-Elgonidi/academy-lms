import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { HifzMap } from '@/components/hifz/map';
import { Icon } from '@/components/icons';
import { Badge, Card, Empty, Heading, SectionTitle, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { firstName } from '@/lib/games/world';
import { pageTitle, strengthTone, wirdStatus } from '@/lib/hifz/hifz';
import type { Hifz } from '@/lib/hifz/types';

/**
 * The hifz map of the learner on screen (GET learning/hifz): every page of
 * the mushaf by part in its strength, the counts, the last page recited, and
 * today's review ("ورد النهارده"), each page opening to be recited or said
 * how it went. A student's own words by their look; a guardian's says whose
 * map it is. It refreshes on the hifz signal (a page reviewed, marked, or
 * recited and then heard). Nothing while the platform has no Quran text.
 */
export default async function HifzMapPage() {
  const [t, fam, locale] = await Promise.all([getTranslations('hifz'), family(), getLocale()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const [nav, { data: hifz }] = await Promise.all([getTranslations(`nav.${fam.stage}`), get<{ data: Hifz }>('/v1/learning/hifz', { query: { learner_id: learner.id } })]);
  const kids = fam.stage === 'kids';
  const title = learner.is_self ? nav('hifz') : t('of', { name: `\u2068${firstName(learner.name)}\u2069` });
  if (hifz.pages_total === 0) {
    return (
      <div>
        <Heading title={title} />
        <Empty>{t('none')}</Empty>
      </div>
    );
  }

  const { wird } = hifz;
  const status = wirdStatus({ due: wird.pages.length, done_today: wird.done_today, daily: wird.daily });
  const latest = hifz.latest_recitation;
  return (
    <div className="stagger space-y-5">
      <Heading
        title={title}
        hint={t('lead')}
        action={
          status.state === 'left' ? (
            <a href="#wird" className="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-brand-soft px-4 text-sm font-semibold text-brand-strong">
              <Icon name="book" size={16} />
              {t('wirdLeft', { n: status.left })}
            </a>
          ) : null
        }
      />

      {latest ? (
        <Link href={`/family/hifz/${latest.page}?recitation=${latest.id}`} className="lift flex items-center gap-3 rounded-card border border-line bg-white p-4" data-recitation={latest.status}>
          <span className={join('grid size-11 shrink-0 place-items-center rounded-control', latest.status === 'pending' ? 'bg-info-soft text-info' : latest.status === 'ready' ? 'bg-brand-soft text-brand-strong' : 'bg-danger-soft text-danger')} aria-hidden="true">
            <Icon name="mic" />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-semibold">{t('latest', { page: latest.page })}</span>
            <span className="block text-sm text-muted">{[latest.status === 'ready' ? (latest.grade_label ?? latest.status_label) : latest.status_label, latest.recited_at?.label].filter(Boolean).join(' · ')}</span>
          </span>
          <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
        </Link>
      ) : null}

      <Card>
        <HifzMap map={hifz} />
      </Card>

      <section aria-labelledby="wird">
        <SectionTitle id="wird" trailing={wird.daily > 0 ? t('wirdProgress', { done: wird.done_today, daily: wird.daily }) : null}>
          {t('wird')}
        </SectionTitle>
        {wird.pages.length === 0 ? (
          <p className={join('rounded-card p-5 text-center font-semibold', status.state === 'done' ? 'bg-brand-soft text-brand-strong' : 'border border-dashed border-line bg-white/60 text-muted')}>
            {status.state === 'done' ? (kids ? t('kids.wirdDone') : t('wirdDone')) : t('wirdNone')}
          </p>
        ) : (
          <ul className="space-y-2">
            {wird.pages.map((p) => (
              <li key={p.page}>
                <Link href={`/family/hifz/${p.page}`} className={join('lift flex items-center gap-3 rounded-card border border-line bg-white px-4', kids ? 'min-h-18 py-3' : 'min-h-14 py-2.5')}>
                  <span className="min-w-0 flex-1">
                    <span className={join('block truncate font-semibold', kids && 'text-lg')}>{pageTitle(p, locale, t('page', { page: p.page }))}</span>
                    <span className="block text-sm text-muted">
                      {[t('juz', { n: p.juz }), p.overdue_days > 0 ? t('overdue', { n: p.overdue_days }) : null].filter(Boolean).join(' · ')}
                    </span>
                  </span>
                  <Badge tone={strengthTone(p.strength)}>{hifz.strengths[p.strength] ?? p.strength}</Badge>
                  <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
