import { notFound } from 'next/navigation';
import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { HifzReview } from '@/components/hifz/review';
import { Icon } from '@/components/icons';
import { Badge, Empty } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { nextPage, pageParam, pageTitle, strengthTone } from '@/lib/hifz/hifz';
import type { Hifz, HifzPage, Recitation } from '@/lib/hifz/types';

/**
 * One page of the hifz review, for the learner on screen: recited (the
 * academy's check on: recorded here, heard and checked in the background, the
 * result arriving with the hifz signal; ?recitation= is the one just sent) or
 * said how it went after reciting it from memory and then uncovering it.
 */
export default async function HifzPageScreen({ params, searchParams }: { params: Promise<{ page: string }>; searchParams: Promise<{ recitation?: string }> }) {
  const [{ page: asked }, { recitation: recited }] = await Promise.all([params, searchParams]);
  const page = pageParam(asked);
  if (page === null) notFound();
  const [t, fam, locale] = await Promise.all([getTranslations('hifz'), family(), getLocale()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const who = { learner_id: learner.id };
  const [{ data: hifz }, { data: shown }, check] = await Promise.all([
    get<{ data: Hifz }>('/v1/learning/hifz', { query: who }),
    get<{ data: HifzPage }>('/v1/learning/hifz/pages/{page}', { params: { page: String(page) }, query: who }),
    recited ? get<{ data: Recitation }>('/v1/learning/hifz/recitations/{recitation}', { params: { recitation: recited }, query: who }) : Promise.resolve(null),
  ]);
  if (hifz.pages_total === 0) notFound();
  const recitation = check && check.data.page === page ? check.data : null;

  return (
    <div>
      <Link href="/family/hifz" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {t('back')}
      </Link>
      <header className="mb-5">
        <h1 className="text-2xl font-bold">{pageTitle(shown, locale, t('page', { page }))}</h1>
        <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
          <span>{t('juz', { n: shown.juz })}</span>
          <Badge tone={strengthTone(shown.strength)}>{hifz.strengths[shown.strength] ?? shown.strength}</Badge>
          {shown.due_at ? <span>{t('dueAt', { when: shown.due_at.label })}</span> : null}
        </p>
      </header>
      <HifzReview
        key={`${learner.id}-${page}-${recitation?.id ?? ''}`}
        learnerId={learner.id}
        page={page}
        surahs={shown.surahs}
        verses={shown.verses}
        attribution={shown.attribution}
        mode={hifz.mode}
        recitation={recitation}
        next={nextPage(hifz.wird.pages, page)}
        kids={fam.stage === 'kids'}
      />
    </div>
  );
}
