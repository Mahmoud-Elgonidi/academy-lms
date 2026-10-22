import { getLocale, getTranslations } from 'next-intl/server';
import type { ReactNode } from 'react';
import { Link } from '@/i18n/navigation';
import { DisputeState } from '@/components/dispute-state';
import { Icon } from '@/components/icons';
import { Badge, buttonLook, join, type Tone } from '@/components/ui';
import type { FamilyLesson } from '@/lib/family/types';
import { dayLabel } from '@/lib/format';
import { clock, type LocalTime } from '@/lib/time';
import { viewerTimeFormat } from '@/lib/viewer';

const statusTone: Record<string, Tone> = {
  in_progress: 'brand',
  completed: 'muted',
  student_absent: 'attention',
  teacher_absent: 'danger',
  needs_review: 'info',
  cancelled: 'danger',
};

/** One lesson as a family sees it: when (on their clock, as the API wrote it, in 12 or 24 hours as they read one), with which teacher, its state, and the way in. */
export async function FamilyLessonCard({ lesson, featured = false, actions }: { lesson: FamilyLesson; featured?: boolean; actions?: ReactNode }) {
  const [t, locale, format] = await Promise.all([getTranslations('family'), getLocale(), viewerTimeFormat()]);
  const at = (time: LocalTime | null) => clock(time, format, locale);
  const cancelled = lesson.status === 'cancelled';
  // The day on the viewer's own calendar, from the wall clock the API wrote.
  const day = dayLabel(lesson.starts_at.local.slice(0, 10), locale, { weekday: 'short', day: 'numeric', month: 'short' });
  return (
    <article className={join('rounded-card border bg-white p-4', featured ? 'border-brand-line shadow-[0_10px_30px_-18px_var(--color-brand)]' : 'border-line')}>
      <div className="flex flex-wrap items-center gap-4">
        <div className={join('min-w-20 text-center', cancelled && 'opacity-60')}>
          <p className="text-xs text-muted">{day}</p>
          <p className={join('whitespace-nowrap font-heading font-bold tabular-nums', featured ? 'text-2xl' : 'text-lg', cancelled && 'line-through')}>{at(lesson.starts_at)}</p>
        </div>
        <div className="min-w-0 flex-1">
          <p className="font-semibold">{t('lessonWith', { subject: lesson.subject, teacher: lesson.with })}</p>
          <p className="text-sm tabular-nums text-muted">
            <span className="whitespace-nowrap">{at(lesson.starts_at)}</span>–<span className="whitespace-nowrap">{at(lesson.ends_at)}</span>
          </p>
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            {lesson.status !== 'scheduled' ? <Badge tone={statusTone[lesson.status] ?? 'muted'}>{lesson.status_label}</Badge> : null}
            {lesson.type_label ? <Badge tone="info">{lesson.type_label}</Badge> : null}
            {lesson.unfunded && !cancelled ? <Badge tone="attention">{t('unfunded')}</Badge> : null}
            {lesson.substitute_for ? <Badge tone="info">{t('substituteFor', { name: lesson.substitute_for })}</Badge> : null}
          </div>
          {cancelled && lesson.cancelled_because ? <p className="mt-1 text-sm text-muted">{lesson.cancelled_because}</p> : null}
        </div>
        {lesson.join.open ? (
          <Link href={`/family/lessons/${lesson.id}`} className={join(buttonLook('primary'), 'w-full sm:w-auto')}>
            <Icon name="video" size={18} />
            {t('joinLesson')}
          </Link>
        ) : featured && lesson.status === 'scheduled' && lesson.join.opens_at ? (
          <p className="w-full text-sm text-muted sm:w-auto">{t('opensAt', { time: at(lesson.join.opens_at) })}</p>
        ) : null}
      </div>
      {lesson.dispute ? <DisputeState dispute={lesson.dispute} /> : null}
      {actions ? <div className="mt-3 border-t border-line pt-3">{actions}</div> : null}
    </article>
  );
}
