'use client';

import { useLocale } from 'next-intl';
import { createContext, useCallback, useContext, type ReactNode } from 'react';
import { clock, DEFAULT_TIME_FORMAT, formatClock, type LocalTime, type TimeFormat } from '@/lib/time';

/*
 * How the person signed in reads a clock (12 or 24 hours), for the client
 * components that write one. The teacher's and the family's frames provide it
 * from `me()` (`time_format`: their own choice, else the academy's); a server
 * component asks `viewerTimeFormat()` instead. Outside a frame, 12 hours.
 */
const Format = createContext<TimeFormat>(DEFAULT_TIME_FORMAT);

export function TimeFormatProvider({ format, children }: { format: TimeFormat; children: ReactNode }) {
  return <Format value={format}>{children}</Format>;
}

/** 'h12' or 'h24', as the frame was given it. */
export function useTimeFormat(): TimeFormat {
  return useContext(Format);
}

/** Writes clocks the reader's way, in the page's language: a LocalTime's wall clock, or a wall clock kept as "HH:MM". */
export function useClock(): { clock: (time: LocalTime | null | undefined) => string; wall: (hhmm: string) => string } {
  const format = useTimeFormat();
  const locale = useLocale();
  return {
    clock: useCallback((time: LocalTime | null | undefined) => clock(time, format, locale), [format, locale]),
    wall: useCallback((hhmm: string) => formatClock(hhmm, format, locale), [format, locale]),
  };
}
