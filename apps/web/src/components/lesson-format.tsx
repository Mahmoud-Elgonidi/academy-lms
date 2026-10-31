import { useTranslations } from 'next-intl';
import { Icon } from '@/components/icons';
import { join } from '@/components/ui';
import { seatDots, type LessonFormat } from '@/lib/lesson-format';

/**
 * Private or group, one look everywhere (lessons, courses, money, the
 * academy's page): a group is its own tone with the people icon and its
 * seats as dots (●●●○○ 3/5); a private lesson, where both can appear, a
 * plain badge. Seats are said in words to assistive technology.
 */
export function FormatBadge({
  format,
  seats,
  size = 'sm',
  className,
}: {
  format: LessonFormat;
  /** A group's seats taken and how many it has; left out, the badge says only "group". */
  seats?: { taken: number; capacity: number } | null;
  size?: 'sm' | 'md';
  className?: string;
}) {
  const t = useTranslations('format');
  if (format === 'private') {
    return (
      <span data-format="private" className={join('inline-flex items-center rounded-full border border-line bg-white px-2.5 py-0.5 text-xs font-semibold text-muted', className)}>
        {t('private')}
      </span>
    );
  }
  return (
    <span
      data-format="group"
      className={join(
        'inline-flex max-w-full items-center gap-1.5 rounded-full border border-group-line bg-group-soft font-semibold text-group-strong',
        size === 'md' ? 'px-3 py-1 text-sm' : 'px-2.5 py-0.5 text-xs',
        className,
      )}
    >
      <Icon name="people" size={size === 'md' ? 16 : 14} className="shrink-0" />
      <span>{t('group')}</span>
      {seats ? (
        <>
          <SeatDots taken={seats.taken} capacity={seats.capacity} />
          <span dir="ltr" aria-hidden="true" className="tabular-nums">
            {seats.taken}/{seats.capacity}
          </span>
          <span className="sr-only">{t('seatsTaken', { taken: seats.taken, capacity: seats.capacity })}</span>
        </>
      ) : null}
    </span>
  );
}

/** The seats as dots, taken first in the reading order (none past a row's worth: the numbers say it). */
export function SeatDots({ taken, capacity, className }: { taken: number; capacity: number; className?: string }) {
  const dots = seatDots(taken, capacity);
  if (dots.length === 0) return null;
  return (
    <span aria-hidden="true" data-seats={`${taken}/${capacity}`} className={join('inline-flex items-center gap-[3px]', className)}>
      {dots.map((full, i) => (
        <span key={i} data-seat={full ? 'taken' : 'free'} className={join('size-[7px] rounded-full', full ? 'bg-group' : 'border border-group-line bg-white')} />
      ))}
    </span>
  );
}
