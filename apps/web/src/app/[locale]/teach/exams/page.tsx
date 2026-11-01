import { notFound } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { QuranOff } from '@/components/quran/off';
import { ExamList } from '@/components/quran/parts';
import { Badge, Empty, Heading, SectionTitle } from '@/components/ui';
import { quranRead } from '@/lib/quran/server';
import type { ExaminerQueue } from '@/lib/quran/types';

/**
 * The examiner's Quran exams (GET quran/examiner/exams): the ones booked
 * with them, soonest first, each opening its result form, and the last
 * results they recorded. A teacher who examines nobody sees a calm word.
 */
export default async function Exams() {
  const [t, nav] = await Promise.all([getTranslations('quran'), getTranslations('nav')]);
  const found = await quranRead<ExaminerQueue>('/v1/quran/examiner/exams');
  if (found.state === 'off') return <QuranOff title={nav('exams')} />;
  if (found.state === 'missing') notFound();
  const { booked, recent } = found.data;
  return (
    <div>
      <Heading title={nav('exams')} hint={t('examiner.lead')} />
      {booked.length === 0 ? (
        <Empty>{t('examiner.none')}</Empty>
      ) : (
        <ul className="stagger space-y-2">
          {booked.map((exam) => (
            <li key={exam.id}>
              <Link href={`/teach/exams/${exam.id}`} className="lift flex items-center gap-3 rounded-card border border-line bg-white p-4" data-booked-exam={exam.id}>
                <span className="grid size-11 shrink-0 place-items-center rounded-control bg-info-soft text-info" aria-hidden="true">
                  <Icon name="trophy" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold">
                    {exam.stop.label}
                    {exam.student ? <span className="font-normal text-muted"> · {exam.student}</span> : null}
                  </span>
                  <span className="block text-sm text-muted">{[exam.program.name, exam.lesson?.starts_at?.label].filter(Boolean).join(' · ')}</span>
                </span>
                {exam.retake ? <Badge tone="attention">{t('retake')}</Badge> : null}
                <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
              </Link>
            </li>
          ))}
        </ul>
      )}
      {recent.length > 0 ? (
        <section aria-labelledby="recent-exams">
          <SectionTitle id="recent-exams">{t('examiner.recent')}</SectionTitle>
          <ExamList exams={recent} examiner />
        </section>
      ) : null}
    </div>
  );
}
