import { getLocale, getTranslations } from 'next-intl/server';
import type { ReactNode } from 'react';
import { Link } from '@/i18n/navigation';
import { DisputeState } from '@/components/dispute-state';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Badge, buttonLook, join, type Tone } from '@/components/ui';
import type { FamilyLesson } from '@/lib/family/types';
import { dayLabel } from '@/lib/format';
import { groupOf, someNames } from '@/lib/lesson-format';
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

/**
 * One lesson as a family sees it: when (on their clock, as the API wrote it,
 * in 12 or 24 hours as they read one), with which teacher, its state, and the
 * way in. A group lesson says so at a glance (its own edge, the group badge
 * with its seats, the learner's own: "seat 4 of 5") and with whom: the
 * classmates' first names, or in the kids' look only how many (the API sends
 * no names there).
 */
export async function FamilyLessonCard({ lesson, featured = false, actions }: { lesson: FamilyLesson; featured?: boolean; actions?: ReactNode }) {
  const [t, f, locale, format] = await Promise.all([getTranslations('family'), getTranslations('format'), getLocale(), viewerTimeFormat()]);
  const at = (time: LocalTime | null) => clock(time, format, locale);
  const cancelled = lesson.status === 'cancelled';
  // The day on the viewer's own calendar, from the wall clock the API wrote.
  const day = dayLabel(lesson.starts_at.local.slice(0, 10), locale, { weekday: 'short', day: 'numeric', month: 'short' });
  const group = groupOf(lesson);
  const shown = group?.classmates ? someNames(group.classmates, 3) : null;
  const names = shown && shown.names.length > 0 ? [...shown.names, ...(shown.more > 0 ? [f('more', { n: shown.more })] : [])].join(f('listJoin')) : null;
  return (
    <article
      data-lesson={lesson.id}
      data-format={group ? 'group' : 'private'}
      className={join(
        'rounded-card border bg-white p-4',
        group && 'border-s-4 border-s-group',
        featured ? 'border-brand-line shadow-[0_10px_30px_-18px_var(--color-brand)]' : group ? 'border-group-line' : 'border-line',
      )}
    >
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
          {group ? (
            <p className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
              <FormatBadge format="group" seats={{ taken: group.taken, capacity: group.capacity }} />
              {group.seatNumber ? (
                <span data-seat-number={group.seatNumber} className="font-semibold text-group-strong">
                  {f('seatNumber', { seat: group.seatNumber, capacity: group.capacity })}
                </span>
              ) : null}
              {group.classmatesLabel ? <span className="text-ink">{names ? f('named', { label: group.classmatesLabel, names }) : group.classmatesLabel}</span> : null}
            </p>
          ) : null}
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            {group?.seatLabel ? <Badge tone="info">{f('yourSeat', { label: group.seatLabel })}</Badge> : null}
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
