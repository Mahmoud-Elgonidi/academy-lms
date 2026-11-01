import { useLocale, useTranslations } from 'next-intl';
import { Icon } from '@/components/icons';
import { Badge, Progress, join } from '@/components/ui';
import { dayLabel } from '@/lib/format';
import { examTone, gradeTone, juzDone, pagesText, partsOf, percentOf, starsOf, type JuzCell } from '@/lib/quran/quran';
import type { Certificate, Exam, LessonResult, PartKey, Plan, Portion } from '@/lib/quran/types';

/*
 * The Quran program's pieces, shared by the family's Quran page, the
 * teacher's lesson panel and student plan, and the examiner's queue: how far
 * along (a bar, or a child's stars), the thirty parts, a portion's three
 * cards, the last lessons' grades, exams and certificates. Passages are the
 * API's titles (surah and verses), never the Quran's words.
 */

const PART_ICON: Record<PartKey, 'spark' | 'clock' | 'book'> = { new: 'spark', near: 'clock', far: 'book' };

/** How far along the plan is: a bar with its percent, or, in the kids' look, five stars and no number. */
export function PlanProgress({ plan, kids = false }: { plan: Plan; kids?: boolean }) {
  const t = useTranslations('quran');
  if (kids) {
    const stars = starsOf(plan.progress);
    return (
      <div className="flex items-center gap-1" role="img" aria-label={t('kids.stars')} data-stars={stars.filled}>
        {Array.from({ length: stars.of }, (_, i) => (
          <Icon key={i} name="star" size={26} className={i < stars.filled ? 'pop text-attention' : 'text-line'} />
        ))}
      </div>
    );
  }
  const percent = percentOf(plan.progress);
  return (
    <div>
      <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
        <span className="text-muted">{t('progress')}</span>
        <span className="font-semibold tabular-nums" data-progress={percent}>
          {t('percent', { n: percent })}
        </span>
      </div>
      <Progress value={percent} label={t('progress')} />
    </div>
  );
}

/** The thirty parts of the mushaf as a bar, each filled by its memorized pages (from the hifz map); the kids' look without numbers. */
export function JuzStrip({ strip, kids = false }: { strip: JuzCell[]; kids?: boolean }) {
  const t = useTranslations('quran');
  const done = juzDone(strip);
  return (
    <div>
      <div className="mb-2 flex items-baseline justify-between gap-3 text-sm">
        <span className="font-semibold">{kids ? t('kids.juzTitle') : t('juzTitle')}</span>
        {kids ? null : <span className="text-muted tabular-nums">{t('juzDone', { n: done })}</span>}
      </div>
      <ol className="grid grid-cols-10 gap-1 sm:grid-cols-15 lg:grid-cols-30" aria-label={kids ? t('kids.juzTitle') : t('juzDone', { n: done })} data-juz-strip>
        {strip.map((cell) => (
          <li
            key={cell.juz}
            className={join('relative h-7 overflow-hidden rounded-[0.35rem] bg-line/70', cell.done && 'ring-1 ring-brand-strong/30')}
            title={kids ? undefined : t('juzCell', { juz: cell.juz, memorized: cell.memorized, total: cell.total })}
            data-juz={cell.juz}
            data-share={Math.round(cell.share * 100)}
          >
            <span className="bar-fill absolute inset-y-0 start-0 bg-brand" style={{ width: `${Math.round(cell.share * 100)}%` }} />
            {cell.learning > 0 && !cell.done ? <span className="absolute inset-x-0 bottom-0 h-1 bg-info/70" /> : null}
            {kids ? null : (
              <span className={join('relative grid h-full place-items-center text-[10px] font-semibold tabular-nums', cell.share >= 0.5 ? 'text-white' : 'text-muted')}>{cell.juz}</span>
            )}
          </li>
        ))}
      </ol>
      {kids ? null : (
        <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2.5 rounded-sm bg-brand" aria-hidden="true" />
            {t('legendMemorized')}
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="h-1 w-3 rounded-sm bg-info/70" aria-hidden="true" />
            {t('legendLearning')}
          </span>
        </p>
      )}
    </div>
  );
}

/** A portion's three parts (new, near review, far review), each its passages and pages; a Noorani lesson as its own line. */
export function PortionCards({ portion, kids = false }: { portion: Portion; kids?: boolean }) {
  const t = useTranslations('quran');
  const parts = partsOf(portion);
  if (portion.finished && parts.length === 0) return <p className="rounded-card bg-brand-soft p-4 text-center font-semibold text-brand-strong">{t('finished')}</p>;
  return (
    <div className="space-y-3">
      {portion.lesson ? (
        <p className="rounded-card border border-line bg-white px-4 py-3 font-semibold" data-noorani>
          {portion.lesson.title}
        </p>
      ) : null}
      <ul className="stagger grid gap-3 sm:grid-cols-3">
        {parts.map((key) => {
          const part = portion[key]!;
          return (
            <li key={key} className="rounded-card border border-line bg-white p-4" data-part={key}>
              <p className="flex items-center gap-2 text-sm font-semibold text-brand-strong">
                <span className="grid size-8 place-items-center rounded-control bg-brand-soft" aria-hidden="true">
                  <Icon name={PART_ICON[key]} size={16} />
                </span>
                {kids ? t(`kids.parts.${key}`) : t(`parts.${key}`)}
              </p>
              <ul className="mt-2 space-y-0.5">
                {part.ranges.map((r) => (
                  <li key={`${r.surah}:${r.from}`} className="font-semibold">
                    {r.title}
                  </li>
                ))}
              </ul>
              {!kids && part.pages.length > 0 ? <p className="mt-1 text-xs text-muted">{t('pages', { list: pagesText(part.pages) })}</p> : null}
            </li>
          );
        })}
      </ul>
      {portion.stops.length > 0 ? (
        <p className="flex flex-wrap items-center gap-2 text-sm text-muted">
          <Icon name="flag" size={16} className="text-attention" />
          {portion.stops.map((s) => s.label).join('، ')}
        </p>
      ) : null}
    </div>
  );
}

/** The last lessons' grades, each part with its grade and (not in the kids' look) the mistakes by kind; the teacher also reads their note. */
export function RecentResults({ results, kids = false, teacher = false }: { results: LessonResult[]; kids?: boolean; teacher?: boolean }) {
  const t = useTranslations('quran');
  if (results.length === 0) return <p className="rounded-card border border-dashed border-line bg-white/60 p-5 text-center text-sm text-muted">{t('noResults')}</p>;
  return (
    <ul className="stagger space-y-2">
      {results.map((result) => (
        <li key={result.id} className="rounded-card border border-line bg-white p-4" data-result={result.id}>
          <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
            <span className="font-semibold">{result.recorded_at?.label ?? ''}</span>
            {result.frozen ? <Badge>{t('frozenResult')}</Badge> : result.advanced ? <Badge tone="brand">{kids ? t('kids.advanced') : t('advanced')}</Badge> : null}
          </div>
          <ul className="mt-2 space-y-1.5">
            {(Object.entries(result.parts) as [PartKey, NonNullable<LessonResult['parts'][PartKey]>][]).map(([key, part]) => (
              <li key={key} className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span className="text-muted">{kids ? t(`kids.parts.${key}`) : t(`parts.${key}`)}</span>
                <span className="font-semibold">{part.ranges.map((r) => r.title).join('، ') || part.lesson?.title}</span>
                <Badge tone={gradeTone(part.grade)}>{part.grade_label}</Badge>
                {!kids && part.mistakes.some((m) => m.count > 0) ? (
                  <span className="text-xs text-muted">
                    {part.mistakes.filter((m) => m.count > 0).map((m) => t('mistakeCount', { label: m.label, n: m.count })).join('، ')}
                  </span>
                ) : null}
                {teacher && part.note ? <span className="basis-full text-xs text-muted">{t('noteSaid', { note: part.note })}</span> : null}
              </li>
            ))}
          </ul>
        </li>
      ))}
    </ul>
  );
}

/** A learner's exams (or the examiner's): the stop, where it stands, the lesson's time, and the result with the examiner's words. */
export function ExamList({ exams, kids = false, examiner = false }: { exams: Exam[]; kids?: boolean; examiner?: boolean }) {
  const t = useTranslations('quran');
  const locale = useLocale();
  return (
    <ul className="stagger space-y-2">
      {exams.map((exam) => (
        <li key={exam.id} className="rounded-card border border-line bg-white p-4" data-exam={exam.status}>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-semibold">
              {exam.stop.label}
              {examiner && exam.student ? <span className="font-normal text-muted"> · {exam.student}</span> : null}
            </p>
            <Badge tone={examTone(exam.status)}>{kids && exam.status === 'passed' ? t('kids.passed') : exam.status_label}</Badge>
          </div>
          <p className="mt-1 text-sm text-muted">
            {[
              exam.program.name,
              exam.retake ? t('retake') : null,
              exam.status === 'booked' && exam.lesson?.starts_at ? exam.lesson.starts_at.label : null,
              exam.status === 'booked' && exam.examiner?.name && !examiner ? t('examiner', { name: exam.examiner.name }) : null,
              exam.status === 'suggested' && exam.not_before ? t('notBefore', { day: dayLabel(exam.not_before, locale) }) : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </p>
          {exam.result ? (
            <div className="mt-2 space-y-1 text-sm">
              <p className="flex flex-wrap items-center gap-2">
                <Badge tone={gradeTone(exam.result.grade)}>{exam.result.grade_label}</Badge>
                {kids && exam.result.passed ? <Icon name="star" size={18} className="pop text-attention" /> : null}
                {!kids && exam.result.mistakes.some((m) => m.count > 0) ? (
                  <span className="text-xs text-muted">{exam.result.mistakes.filter((m) => m.count > 0).map((m) => t('mistakeCount', { label: m.label, n: m.count })).join('، ')}</span>
                ) : null}
              </p>
              {exam.result.note ? <p className="rounded-control bg-paper px-3 py-2">{exam.result.note}</p> : null}
              {exam.result.retake_on && !exam.result.passed ? <p className="text-muted">{t('retakeOn', { day: dayLabel(exam.result.retake_on, locale) })}</p> : null}
            </div>
          ) : null}
        </li>
      ))}
    </ul>
  );
}

/** A learner's certificates: what each is for, its grade and day, its page to print, and the code anyone checks it by; a revoked one says so. */
export function CertificateList({ certificates, verifyHref }: { certificates: Certificate[]; verifyHref: (code: string) => string }) {
  const t = useTranslations('quran');
  return (
    <ul className="stagger grid gap-3 sm:grid-cols-2">
      {certificates.map((c) => (
        <li key={c.id} className={join('rounded-card border bg-white p-4', c.revoked ? 'border-line opacity-80' : 'border-brand-line')} data-certificate={c.number}>
          <div className="flex items-start gap-3">
            <span className={join('grid size-11 shrink-0 place-items-center rounded-control', c.revoked ? 'bg-line/60 text-muted' : 'bg-attention-soft text-attention')} aria-hidden="true">
              <Icon name="trophy" />
            </span>
            <div className="min-w-0 flex-1">
              <p className="font-semibold">{c.title}</p>
              <p className="text-sm text-muted">{[c.portion.label, c.riwaya, c.issued_at?.label].filter(Boolean).join(' · ')}</p>
              <p className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                <Badge tone={c.revoked ? 'danger' : gradeTone(c.grade)}>{c.revoked ? t('revoked') : c.grade_label}</Badge>
                <span className="text-xs text-muted tabular-nums" dir="ltr">
                  {c.number}
                </span>
              </p>
            </div>
          </div>
          {!c.revoked ? (
            <div className="mt-3 flex flex-wrap gap-2">
              {c.url ? (
                <a href={c.url} target="_blank" rel="noopener" className="inline-flex min-h-10 items-center gap-1.5 rounded-control bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-strong">
                  <Icon name="file" size={16} />
                  {t('openCertificate')}
                </a>
              ) : null}
              {c.code ? (
                <a href={verifyHref(c.code)} className="inline-flex min-h-10 items-center gap-1.5 rounded-control border border-line bg-white px-4 text-sm font-semibold hover:bg-brand-soft">
                  <Icon name="shield" size={16} />
                  {t('verify')}
                  <span className="sr-only"> {c.code}</span>
                </a>
              ) : null}
            </div>
          ) : null}
        </li>
      ))}
    </ul>
  );
}
