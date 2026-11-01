'use client';

import { useEffect, useRef, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { dayLabel, zoneName } from '@/lib/format';
import { calendarFileUrl, googleCalendarUrl, type CalendarEvent } from '@/lib/site/trial';
import { formatClock, type LocalTime, type TimeFormat } from '@/lib/time';
import { Icon } from '../icons';
import { Confetti } from '../join-platform';

/** A heading that takes the focus when its card appears, so a screen reader starts there. */
export function FocusHeading({ children, level = 3, className }: { children: ReactNode; level?: 2 | 3; className?: string }) {
  const ref = useRef<HTMLHeadingElement>(null);
  useEffect(() => ref.current?.focus(), []);
  const Tag = level === 2 ? 'h2' : 'h3';
  return <Tag ref={ref} tabIndex={-1} className={className}>{children}</Tag>;
}

/** The booked time, big: the day, the clock on the family's own clock, the zone. */
export function BigTime({ time, timeFormat }: { time: LocalTime; timeFormat: TimeFormat }) {
  const locale = useLocale();
  const day = time.local.slice(0, 10);
  return (
    <div className="lt-bigtime">
      <span className="lt-bigtime-cal" aria-hidden="true">
        <small>{dayLabel(day, locale, { month: 'short' })}</small>
        <b>{dayLabel(day, locale, { day: 'numeric' })}</b>
      </span>
      <span className="lt-bigtime-words">
        <span className="lt-bigtime-day">{dayLabel(day, locale, { weekday: 'long', day: 'numeric', month: 'long' })}</span>
        <span className="lt-bigtime-clock">{formatClock(time.local.slice(11, 16), timeFormat, locale)}</span>
        {time.timezone ? <span className="lt-bigtime-zone">{zoneName(time.timezone, locale)}</span> : null}
      </span>
    </div>
  );
}

/** "Add to calendar": a calendar file written on the server, and Google Calendar, both from the API's UTC instant. */
export function AddToCalendar({ event }: { event: CalendarEvent }) {
  const t = useTranslations('site.trial.booked');
  return (
    <div className="lt-calendar">
      <a className="ls-btn ls-btn-dark ls-btn-small" href={calendarFileUrl(event)} download="trial-lesson.ics">
        <Icon name="calendar" />{t('ics')}
      </a>
      <a className="ls-btn lt-btn-soft ls-btn-small" href={googleCalendarUrl(event)} target="_blank" rel="noopener">
        {t('google')}
      </a>
    </div>
  );
}

/**
 * A free trial booked: the time big, with whom and what, add to calendar,
 * and what happens next (the message with the class link now, a reminder
 * the day before on WhatsApp, the links to change or cancel in it).
 */
export function BookedCard({ time, subject, teacher, minutes, academy, timeFormat, title, lead, next, actions }: {
  time: LocalTime;
  subject: string;
  teacher?: string;
  minutes?: number;
  academy: string;
  timeFormat: TimeFormat;
  title: string;
  lead?: string;
  next: string[];
  actions?: ReactNode;
}) {
  const t = useTranslations('site.trial.booked');
  const length = minutes && minutes > 0 ? minutes : 30;
  const event: CalendarEvent = {
    utc: time.utc,
    minutes: length,
    title: t('eventTitle', { subject, academy }),
    details: teacher ? t('eventDetails', { teacher }) : undefined,
  };
  return (
    <div className="lt-booked" role="status">
      <Confetti />
      <span className="lt-booked-tick" aria-hidden="true"><Icon name="check" /></span>
      <FocusHeading>{title}</FocusHeading>
      {lead ? <p className="lt-muted">{lead}</p> : null}
      <BigTime time={time} timeFormat={timeFormat} />
      <ul className="lt-facts">
        <li><Icon name="book" /><span>{subject}</span></li>
        {teacher ? <li><Icon name="user" /><span>{t.rich('with', { teacher, name: (chunks) => <bdi>{chunks}</bdi> })}</span></li> : null}
        <li><Icon name="clock" /><span>{t('minutes', { minutes: length })}</span></li>
      </ul>
      <AddToCalendar event={event} />
      <div className="lt-next">
        <h4>{t('nextTitle')}</h4>
        <ol>
          {next.map((line) => <li key={line}>{line}</li>)}
        </ol>
      </div>
      {actions ? <div className="lt-actions lt-actions-end">{actions}</div> : null}
    </div>
  );
}
