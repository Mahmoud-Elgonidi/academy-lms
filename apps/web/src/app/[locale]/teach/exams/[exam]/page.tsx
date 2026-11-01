import { notFound } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { QuranOff } from '@/components/quran/off';
import { ExamList } from '@/components/quran/parts';
import { ExamResultForm } from '@/components/quran/recorder';
import { Notice, buttonLook } from '@/components/ui';
import { quranRead } from '@/lib/quran/server';
import type { ExaminerQueue } from '@/lib/quran/types';

/**
 * An exam booked with this examiner: the stop, the student and the lesson's
 * time, the way into the assessment lesson (its shared mushaf, where words
 * may be marked), and the result: mistakes by kind (else the mushaf's
 * marks), a grade of their own with a note, and for a fail what to work on
 * and the day to try again. Recorded once, from the lesson's opening on.
 */
export default async function ExamResult({ params }: { params: Promise<{ exam: string }> }) {
  const { exam: id } = await params;
  const [t, nav] = await Promise.all([getTranslations('quran'), getTranslations('nav')]);
  const found = await quranRead<ExaminerQueue>('/v1/quran/examiner/exams');
  if (found.state === 'off') return <QuranOff title={nav('exams')} />;
  if (found.state === 'missing') notFound();
  const queue = found.data;
  const exam = queue.booked.find((e) => e.id === id) ?? queue.recent.find((e) => e.id === id);
  if (!exam) notFound();
  const open = exam.status === 'booked';

  return (
    <div className="space-y-5">
      <Link href="/teach/exams" className="inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {nav('exams')}
      </Link>
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{exam.stop.label}</h1>
          <p className="mt-1 text-muted">{[exam.student, exam.program.name, exam.lesson?.starts_at?.label].filter(Boolean).join(' · ')}</p>
        </div>
        {open && exam.lesson ? (
          <Link href={`/teach/lessons/${exam.lesson.session_id}`} className={buttonLook('quiet')}>
            <Icon name="video" size={18} />
            {t('examiner.enter')}
          </Link>
        ) : null}
      </header>
      {open ? (
        <>
          <p className="text-sm text-muted">{t('examiner.formLead')}</p>
          <ExamResultForm exam={exam.id} types={queue.mistake_types} grades={queue.grades} />
        </>
      ) : (
        <>
          <Notice tone="brand">{t('form.examSaved')}</Notice>
          <ExamList exams={[exam]} examiner />
        </>
      )}
    </div>
  );
}
