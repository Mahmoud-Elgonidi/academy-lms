import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { DisputeLesson } from '@/components/dispute-lesson';
import { DisputeState } from '@/components/dispute-state';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Badge, buttonLook, join, type Tone } from '@/components/ui';
import { firstName, groupOf, someNames } from '@/lib/lesson-format';
import type { Lesson } from '@/lib/teach/types';
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
 * One lesson: when (on the teacher's clock, as the API wrote it, in 12 or 24
 * hours as they read one), with whom, where it stands, the way in, and a
 * dispute of its outcome. A group lesson says so at a glance: its own edge
 * and tone, the group badge with its seats, and who is in it.
 */
export async function LessonCard({ lesson, featured = false }: { lesson: Lesson; featured?: boolean }) {
  const [t, f, locale, format] = await Promise.all([getTranslations('teach'), getTranslations('format'), getLocale(), viewerTimeFormat()]);
  const at = (time: LocalTime | null) => clock(time, format, locale);
  const cancelled = lesson.status === 'cancelled';
  const group = groupOf(lesson);
  const roster = group ? someNames(group.students.map((s) => s.name), 4) : null;
  return (
    <article
      data-lesson={lesson.id}
      data-format={group ? 'group' : 'private'}
      className={join(
        'flex flex-wrap items-center gap-4 rounded-card border bg-white p-4',
        group && 'border-s-4 border-s-group',
        featured ? 'border-brand-line shadow-[0_10px_30px_-18px_var(--color-brand)]' : group ? 'border-group-line' : 'border-line',
      )}
    >
      <div className={join('min-w-16 text-center', cancelled && 'opacity-60')}>
        <p className={join('whitespace-nowrap font-heading font-bold tabular-nums', featured ? 'text-2xl' : 'text-lg', cancelled && 'line-through')}>{at(lesson.starts_at)}</p>
        <p className="whitespace-nowrap text-xs tabular-nums text-muted">{at(lesson.ends_at)}</p>
      </div>
      <div className="min-w-0 flex-1">
        <p className="font-semibold">{group ? f('groupOf', { subject: lesson.subject }) : lesson.with}</p>
        <p className="text-sm text-muted">
          {lesson.subject} · {lesson.minutes} {t('minutesShort')}
        </p>
        {group && roster && roster.names.length > 0 ? (
          <ul aria-label={t('roster')} className="mt-1.5 flex flex-wrap items-center gap-1">
            {roster.names.map((name, i) => (
              <li key={`${name}-${i}`} title={name} className="max-w-36 truncate rounded-full bg-paper px-2 py-0.5 text-xs font-semibold text-ink">
                {firstName(name)}
              </li>
            ))}
            {roster.more > 0 ? <li className="px-1 text-xs font-semibold text-muted">{f('more', { n: roster.more })}</li> : null}
          </ul>
        ) : null}
        <div className="mt-1.5 flex flex-wrap gap-1.5">
          {group ? <FormatBadge format="group" seats={{ taken: group.taken, capacity: group.capacity }} /> : null}
          {lesson.status !== 'scheduled' ? <Badge tone={statusTone[lesson.status] ?? 'muted'}>{lesson.status_label}</Badge> : null}
          {lesson.type_label ? <Badge tone="info">{lesson.type_label}</Badge> : null}
          {lesson.unfunded && !cancelled ? <Badge tone="attention">{t('unfunded')}</Badge> : null}
          {lesson.substitute_for ? <Badge>{t('substituteFor', { name: lesson.substitute_for })}</Badge> : null}
        </div>
        {cancelled && lesson.cancelled_because ? <p className="mt-1 text-sm text-muted">{lesson.cancelled_because}</p> : null}
      </div>
      {lesson.join.open ? (
        <Link href={`/teach/lessons/${lesson.id}`} className={join(buttonLook('primary'), 'w-full sm:w-auto')}>
          <Icon name="video" size={18} />
          {t('enter')}
        </Link>
      ) : lesson.status === 'scheduled' && lesson.join.opens_at && featured ? (
        <p className="w-full text-sm text-muted sm:w-auto">{t('opensAt', { time: at(lesson.join.opens_at) })}</p>
      ) : null}
      {lesson.dispute ? (
        <div className="w-full">
          <DisputeState dispute={lesson.dispute} />
        </div>
      ) : null}
      {lesson.can_dispute ? (
        <div className="w-full border-t border-line pt-3">
          <DisputeLesson id={lesson.id} side="teacher" reasons={lesson.dispute_reasons} />
        </div>
      ) : null}
    </article>
  );
}
