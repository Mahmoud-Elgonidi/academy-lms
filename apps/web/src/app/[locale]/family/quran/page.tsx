import { notFound } from 'next/navigation';
import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { HifzCard } from '@/components/hifz/card';
import { Icon } from '@/components/icons';
import { RefreshOn } from '@/components/live-refresh';
import { QuranOff } from '@/components/quran/off';
import { CertificateList, ExamList, JuzStrip, PlanProgress, PortionCards, RecentResults } from '@/components/quran/parts';
import { Badge, Card, Empty, Heading, SectionTitle } from '@/components/ui';
import { family } from '@/lib/family/context';
import { firstName } from '@/lib/games/world';
import type { Hifz } from '@/lib/hifz/types';
import { familyNotebook } from '@/lib/mushaf/server';
import { juzStrip, shownCertificates } from '@/lib/quran/quran';
import { dataOf, quranRead } from '@/lib/quran/server';
import type { FamilyCertificates, FamilyExams, FamilyPlans } from '@/lib/quran/types';

/**
 * The Quran of the learner on screen (docs/architecture/02, section 26):
 * each Quran course's plan (where they are, how far along, the next
 * lesson's portion: new, near and far review, and the last lessons'
 * grades), the thirty parts from the hifz map, today's review, the way to
 * the hifz map and the mistakes notebook, the exams, and the certificates
 * while the academy shows them to families. The kids' look in simple words,
 * with stars and no counts (the API sends none). With the academy's plan
 * leaving the Quran out, a calm word instead. It refreshes with the page's
 * signals (lessons, hifz; the notebook's count on mushaf).
 */
export default async function QuranPage() {
  const [t, fam, locale] = await Promise.all([getTranslations('quran'), family(), getLocale()]);
  const learner = fam.current;
  const nav = await getTranslations(`nav.${fam.stage}`);
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const kids = fam.stage === 'kids';
  const title = learner.is_self ? nav('quran') : t('of', { name: `⁨${firstName(learner.name)}⁩` });
  const who = { query: { learner_id: learner.id } };
  const [plans, hifzRead, examsRead, certificatesRead, book] = await Promise.all([
    quranRead<FamilyPlans>('/v1/quran/plan', who),
    quranRead<Hifz>('/v1/learning/hifz', who),
    quranRead<FamilyExams>('/v1/quran/exams', who),
    quranRead<FamilyCertificates>('/v1/quran/certificates', who),
    familyNotebook(learner.id),
  ]);
  if (plans.state === 'off') return <QuranOff title={title} />;
  if (plans.state === 'missing') notFound();

  const courses = plans.data.courses;
  const hifz = dataOf(hifzRead);
  const mapped = hifz !== null && hifz.pages_total > 0;
  const exams = dataOf(examsRead)?.exams ?? [];
  const certificates = shownCertificates(dataOf(certificatesRead));
  const strip = mapped ? juzStrip(hifz.juzs) : null;
  const memorized = mapped && hifz.counts.memorized + hifz.counts.learning > 0;

  if (courses.length === 0 && !memorized && exams.length === 0 && certificates.length === 0) {
    return (
      <div>
        <Heading title={title} />
        <Empty>{kids ? t('kids.none') : t('none')}</Empty>
      </div>
    );
  }

  return (
    <div className="stagger space-y-5">
      <Heading title={title} hint={kids ? t('kids.lead') : t('lead')} />

      {courses.map(({ enrollment_id, subject, plan, recent }) => (
        <Card key={enrollment_id} className="space-y-5" as="section">
          <header className="flex flex-wrap items-start justify-between gap-3" data-plan={plan.id}>
            <div className="min-w-0">
              <h2 className="text-lg font-bold">{subject || plan.program.name}</h2>
              <p className="text-sm text-muted">{kids ? plan.program.track_label : [plan.program.name, plan.program.track_label].join(' · ')}</p>
            </div>
            {plan.frozen ? <Badge tone="attention">{plan.frozen_label ?? t(`frozen.${plan.frozen}`)}</Badge> : plan.finished ? <Badge tone="brand">{t('finishedBadge')}</Badge> : null}
          </header>

          <div className="space-y-3">
            {plan.position ? (
              <p className="flex items-center gap-2">
                <Icon name="map" size={18} className="text-brand" />
                <span>
                  {kids ? t('kids.position') : t('position')} <strong data-position>{plan.position.title}</strong>
                </span>
              </p>
            ) : null}
            <PlanProgress plan={plan} kids={kids} />
          </div>

          {plan.portion ? (
            <section aria-labelledby={`portion-${enrollment_id}`}>
              <h3 id={`portion-${enrollment_id}`} className="mb-2 text-sm font-semibold text-muted">
                {kids ? t('kids.portion') : t('portion')}
              </h3>
              <PortionCards portion={plan.portion} kids={kids} />
            </section>
          ) : null}

          <section aria-labelledby={`recent-${enrollment_id}`}>
            <h3 id={`recent-${enrollment_id}`} className="mb-2 text-sm font-semibold text-muted">
              {kids ? t('kids.recent') : t('recent')}
            </h3>
            <RecentResults results={recent} kids={kids} />
          </section>
        </Card>
      ))}

      {strip ? (
        <Card>
          <JuzStrip strip={strip} kids={kids} />
        </Card>
      ) : null}

      {mapped ? (
        <section aria-labelledby="hifz-ways" className="space-y-3">
          <SectionTitle id="hifz-ways">{kids ? t('kids.hifz') : t('hifz')}</SectionTitle>
          <HifzCard hifz={{ due: hifz.wird.pages.length, done_today: hifz.wird.done_today, daily: hifz.wird.daily }} kids={kids} />
          <div className="grid gap-3 sm:grid-cols-2">
            <WayCard href="/family/hifz" icon="map" title={nav('hifz')} line={kids ? t('kids.mapLine') : t('mapLine', { n: hifz.counts.memorized })} />
            {book ? <WayCard href="/family/hifz#mistakes" icon="mark" title={nav('mistakes')} line={kids ? t('kids.bookLine') : t('bookLine', { n: book.words })} /> : null}
          </div>
          {book ? <RefreshOn signals={['mushaf.changed']} /> : null}
        </section>
      ) : null}

      {exams.length > 0 ? (
        <section aria-labelledby="exams">
          <SectionTitle id="exams">{kids ? t('kids.exams') : t('exams')}</SectionTitle>
          <ExamList exams={exams} kids={kids} />
        </section>
      ) : null}

      {certificates.length > 0 ? (
        <section aria-labelledby="certificates">
          <SectionTitle id="certificates">{t('certificates')}</SectionTitle>
          <CertificateList certificates={certificates} verifyHref={(code) => `/${locale}/certificates/${code}`} />
        </section>
      ) : null}
    </div>
  );
}

function WayCard({ href, icon, title, line }: { href: string; icon: 'map' | 'mark'; title: string; line: string }) {
  return (
    <Link href={href} className="lift flex items-center gap-3 rounded-card border border-line bg-white p-4">
      <span className="grid size-11 shrink-0 place-items-center rounded-control bg-brand-soft text-brand-strong" aria-hidden="true">
        <Icon name={icon} />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-semibold">{title}</span>
        <span className="block text-sm text-muted">{line}</span>
      </span>
      <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
    </Link>
  );
}
