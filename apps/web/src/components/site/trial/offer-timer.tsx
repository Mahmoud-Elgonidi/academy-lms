'use client';

import { useEffect, useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { countdownUnits, nextTick, timeLeft, urgency } from '@/lib/site/report';

/**
 * The offer while it is open, with the time left on it ticking from the API's
 * end (its UTC instant, on this device's clock): days, hours and minutes,
 * then hours, minutes and seconds in its last day. It first shows what the
 * server worked out (the same numbers, so the page reads whole without
 * script), and when the end comes it gives way to the plain price (`ended`)
 * with no reload. Under reduced motion the numbers change without moving.
 */
export function OfferTimer({ endsUtc, serverNow, until, ended, children }: {
  endsUtc: string;
  /** The server's clock when it wrote the page: the first numbers, the same in both. */
  serverNow: number;
  /** "until …", as the API wrote the end on the family's clock. */
  until: string;
  ended: ReactNode;
  children: ReactNode;
}) {
  const t = useTranslations('site.report.offer');
  const [now, setNow] = useState(serverNow);
  const left = timeLeft(endsUtc, now);

  useEffect(() => {
    let timer: ReturnType<typeof setTimeout>;
    const tick = () => {
      const at = Date.now();
      setNow(at);
      const wait = nextTick(timeLeft(endsUtc, at));
      if (wait > 0) timer = setTimeout(tick, wait + 20);
    };
    timer = setTimeout(tick, 0);
    return () => clearTimeout(timer);
  }, [endsUtc]);

  if (left.over) return <>{ended}</>;
  const mood = urgency(left);
  return (
    <div className="lr-deal">
      {children}
      <div className="lr-count" data-urgency={mood} data-ends={endsUtc} role="timer" aria-atomic="true">
        <p className="lr-count-head">
          <span>{t('endsIn')}</span>
          {mood !== 'calm' ? <b className="lr-count-flag">{mood === 'last' ? t('last') : t('soon')}</b> : null}
        </p>
        <ol className="lr-count-units">
          {countdownUnits(left).map(({ unit, value }) => (
            <li key={unit}>
              <b key={value}>{String(value).padStart(2, '0')}</b>
              <small>{t(`units.${unit}`, { count: value })}</small>
            </li>
          ))}
        </ol>
        <p className="lr-count-until">{t('until', { when: until })}</p>
      </div>
    </div>
  );
}
