'use client';

import { useTranslations } from 'next-intl';
import { useDeferredValue, useEffect, useId, useState, useSyncExternalStore } from 'react';
import { Icon } from '@/components/icons';
import { join } from '@/components/ui';
import { groupZones, untilNextMinute, type Zone } from '@/lib/zones';

/*
 * What the zone chip and My account's zone field share: the minute that
 * ticks, the device's own zone, and the list of zones to choose from.
 */

/** The current minute, moving on when the next one begins (a timer to the minute, never asking the API). */
export function useMinute(): Date {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    let timer: ReturnType<typeof setTimeout>;
    const next = () => {
      timer = setTimeout(() => {
        setNow(new Date());
        next();
      }, untilNextMinute(new Date()));
    };
    next();
    // A tab that comes back after sleeping catches up at once.
    const seen = () => document.visibilityState === 'visible' && setNow(new Date());
    document.addEventListener('visibilitychange', seen);
    return () => {
      clearTimeout(timer);
      document.removeEventListener('visibilitychange', seen);
    };
  }, []);
  return now;
}

const still = () => () => {};

/** The device's zone from Intl; on the server, the one the page wrote last time (the lms_tz cookie). */
export function useDeviceZone(fromCookie: string | null): string | null {
  return useSyncExternalStore(
    still,
    () => {
      try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || fromCookie;
      } catch {
        return fromCookie;
      }
    },
    () => fromCookie,
  );
}

/**
 * The zones by region, searched as one types (city, IANA name, offset or
 * region; Arabic letters compared loosely). Choosing one calls `onChoose`;
 * the current one (or the one picked so far) is marked.
 */
export function ZonePicker({
  zones,
  current,
  picked,
  onChoose,
  disabled,
  autoFocus,
  className,
}: {
  zones: readonly Zone[];
  current: string;
  picked?: string | null;
  onChoose: (zone: Zone) => void;
  disabled?: boolean;
  autoFocus?: boolean;
  className?: string;
}) {
  const t = useTranslations('zone');
  const [query, setQuery] = useState('');
  const deferred = useDeferredValue(query);
  const groups = groupZones(zones, deferred);
  const search = useId();
  return (
    <div className={className}>
      <label htmlFor={search} className="sr-only">
        {t('search')}
      </label>
      <div className="relative">
        <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 start-3 grid place-items-center text-muted">
          <Icon name="globe" size={16} />
        </span>
        <input
          id={search}
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t('search')}
          autoFocus={autoFocus}
          autoComplete="off"
          className="min-h-11 w-full rounded-control border border-line bg-white py-2 pe-3 ps-9 text-[15px] outline-none transition-colors placeholder:text-muted/70 focus:border-brand focus:ring-2 focus:ring-brand-soft"
        />
      </div>
      <div className="mt-2 max-h-72 overflow-y-auto overscroll-contain rounded-control border border-line" role="group" aria-label={t('field.list')}>
        {groups.length === 0 ? <p className="p-4 text-center text-sm text-muted">{t('none')}</p> : null}
        {groups.map((group) => (
          <div key={group.region} role="group" aria-label={group.region}>
            <h3 className="sticky top-0 z-10 border-b border-line bg-paper px-3 py-1.5 text-xs font-semibold text-muted">{group.region}</h3>
            <ul>
              {group.zones.map((zone) => {
                const on = (picked ?? current) === zone.id;
                return (
                  <li key={zone.id}>
                    <button
                      type="button"
                      disabled={disabled}
                      onClick={() => onChoose(zone)}
                      aria-pressed={picked !== undefined ? on : undefined}
                      aria-current={picked === undefined && on ? 'true' : undefined}
                      className={join(
                        'flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-start text-sm transition-colors disabled:opacity-60',
                        on ? 'bg-brand-soft font-semibold text-brand-strong' : 'hover:bg-paper',
                      )}
                    >
                      <span className="min-w-0 truncate">{zone.city}</span>
                      <span className="flex shrink-0 items-center gap-2 text-xs text-muted">
                        {zone.id === current ? <span className="rounded-full bg-brand-soft px-2 py-0.5 font-semibold text-brand-strong">{t('current')}</span> : null}
                        <bdi dir="ltr">{zone.offset}</bdi>
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </div>
    </div>
  );
}
