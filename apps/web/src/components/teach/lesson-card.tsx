import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { DisputeLesson } from '@/components/dispute-lesson';
import { DisputeState } from '@/components/dispute-state';
import { Icon } from '@/components/icons';
import { Badge, buttonLook, join, type Tone } from '@/components/ui';
import type { Lesson } from '@/lib/teach/types';
import { clock } from '@/lib/time';

const statusTone: Record<string, Tone> = {
  in_progress: 'brand',
  completed: 'muted',
  student_absent: 'attention',
  teacher_absent: 'danger',
  needs_review: 'info',
  cancelled: 'danger',
};

/** One lesson: when (on the teacher's clock, as the API wrote it), with whom, where it stands, the way in, and a dispute of its outcome. */
export async function LessonCard({ lesson, featured = false }: { lesson: Lesson; featured?: boolean }) {
  const t = await getTranslations('teach');
  const cancelled = lesson.status === 'cancelled';
  return (
    <article
      className={join(
        'flex flex-wrap items-center gap-4 rounded-card border bg-white p-4',
        featured ? 'border-brand-line shadow-[0_10px_30px_-18px_var(--color-brand)]' : 'border-line',
      )}
    >
      <div className={join('min-w-16 text-center', cancelled && 'opacity-60')}>
        <p className={join('font-heading font-bold tabular-nums', featured ? 'text-2xl' : 'text-lg', cancelled && 'line-through')}>{clock(lesson.starts_at)}</p>
        <p className="text-xs tabular-nums text-muted">{clock(lesson.ends_at)}</p>
      </div>
      <div className="min-w-0 flex-1">
        <p className="font-semibold">{lesson.with}</p>
        <p className="text-sm text-muted">
          {lesson.subject} · {lesson.minutes} {t('minutesShort')}
        </p>
        <div className="mt-1.5 flex flex-wrap gap-1.5">
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
        <p className="w-full text-sm text-muted sm:w-auto">{t('opensAt', { time: clock(lesson.join.opens_at) })}</p>
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
