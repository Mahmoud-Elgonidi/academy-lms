'use client';

import { useId, useState, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { dayLabel, zoneName } from '@/lib/format';
import { byDay, openingDay, suits, type TrialDraft } from '@/lib/site/trial';
import { formatClock, type LocalTime, type TimeFormat } from '@/lib/time';
import { Icon } from '../icons';

export type TimesState =
  | { status: 'loading' }
  | { status: 'failed' }
  | { status: 'ready'; times: LocalTime[]; from: string | null };

/**
 * Free times as a strip of days and a grid of times on the family's own
 * clock, as the API wrote them (its `local`), soonest first: the days with a
 * time that fits what they asked for carry a dot, and the strip opens on the
 * first of them. Choosing is a radio group, so a keyboard and a screen reader
 * move through it as through any form.
 */
export function TimePicker({ state, chosen, onChoose, onRetry, onLater, onSoonest, prefs, timeFormat, zone, empty, name }: {
  state: TimesState;
  chosen: LocalTime | null;
  onChoose: (time: LocalTime) => void;
  onRetry: () => void;
  onLater: () => void;
  onSoonest: (() => void) | null;
  prefs?: Pick<TrialDraft, 'days' | 'from' | 'to'>;
  timeFormat: TimeFormat;
  zone: string;
  /** What to offer when there is no time at all. */
  empty: ReactNode;
  name: string;
}) {
  const t = useTranslations('site.trial.time');
  const locale = useLocale();
  const id = useId();
  const wishes = prefs ?? { days: [], from: '', to: '' };
  const days = state.status === 'ready' ? byDay(state.times, wishes) : [];
  const [picked, setPicked] = useState<string | null>(null);
  const chosenDay = chosen ? chosen.local.slice(0, 10) : null;
  const open = days.some((d) => d.day === picked) ? picked : days.some((d) => d.day === chosenDay) ? chosenDay : openingDay(days);
  const today = days.find((d) => d.day === open);
  const anyWishes = wishes.days.length > 0 || wishes.from !== '' || wishes.to !== '';
  const zoneWords = zone ? zoneName(zone, locale) : '';

  if (state.status === 'loading') {
    return (
      <div className="lt-picker" aria-busy="true">
        <p className="lt-zone" role="status"><Icon name="clock" />{t('loading')}</p>
        <div className="lt-skeleton" aria-hidden="true">
          {Array.from({ length: 5 }, (_, i) => <span key={i} />)}
        </div>
        <div className="lt-skeleton lt-skeleton-times" aria-hidden="true">
          {Array.from({ length: 8 }, (_, i) => <span key={i} />)}
        </div>
      </div>
    );
  }
  if (state.status === 'failed') {
    return (
      <div className="lt-card-note" role="alert">
        <p>{t('failed')}</p>
        <button type="button" className="ls-btn ls-btn-dark ls-btn-small" onClick={onRetry}>{t('retry')}</button>
      </div>
    );
  }
  if (days.length === 0) {
    return (
      <div className="lt-empty">
        <span className="lt-empty-icon" aria-hidden="true"><Icon name="calendar" /></span>
        <h4>{t('emptyTitle')}</h4>
        {empty}
        <div className="lt-actions">
          <button type="button" className="lt-link" onClick={onLater}>{t('emptyLater')}</button>
          {onSoonest ? <button type="button" className="lt-link" onClick={onSoonest}>{t('soonest')}</button> : null}
        </div>
      </div>
    );
  }

  return (
    <div className="lt-picker">
      {zoneWords ? <p className="lt-zone"><Icon name="globe" />{t('zone', { zone: zoneWords })}</p> : null}
      <div className="lt-days" role="group" aria-label={t('days')}>
        {days.map((d) => {
          const isOpen = d.day === open;
          return (
            <button
              key={d.day}
              type="button"
              className="lt-day"
              aria-pressed={isOpen}
              data-suits={anyWishes && d.suiting > 0 ? '' : undefined}
              onClick={() => setPicked(d.day)}
            >
              <span className="lt-day-name">{dayLabel(d.day, locale, { weekday: 'short' })}</span>
              <b>{dayLabel(d.day, locale, { day: 'numeric' })}</b>
              <span className="lt-day-month">{dayLabel(d.day, locale, { month: 'short' })}</span>
              <span className="ls-sr">{t('count', { count: d.times.length })}</span>
            </button>
          );
        })}
      </div>
      {today ? (
        <fieldset className="lt-times" key={today.day}>
          <legend>
            {dayLabel(today.day, locale)}
            <small>{t('count', { count: today.times.length })}</small>
          </legend>
          <div className="lt-time-grid">
            {today.times.map((time) => {
              const fits = anyWishes && suits(time, wishes);
              return (
                <label key={time.utc} className="lt-time" data-suits={fits ? '' : undefined}>
                  <input
                    type="radio"
                    name={`${name}-${id}`}
                    value={time.utc}
                    checked={chosen?.utc === time.utc}
                    onChange={() => onChoose(time)}
                  />
                  <span>
                    {formatClock(time.local.slice(11, 16), timeFormat, locale)}
                    {fits ? <i className="ls-sr">{t('suitsYou')}</i> : null}
                  </span>
                </label>
              );
            })}
          </div>
          {anyWishes ? <p className="lt-legend"><i aria-hidden="true" />{t('suitsLegend')}</p> : null}
        </fieldset>
      ) : null}
      <div className="lt-actions">
        <button type="button" className="lt-link" onClick={onLater}>{t('later')}<Icon name="chevron" className="ls-flip" /></button>
        {onSoonest ? <button type="button" className="lt-link" onClick={onSoonest}>{t('soonest')}</button> : null}
      </div>
    </div>
  );
}
