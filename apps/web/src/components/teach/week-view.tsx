import type { CSSProperties } from 'react';
import type { ShiftBlock } from '@/lib/teach/types';
import { weekOrder, weekdayName } from '@/lib/format';
import { formatClock, type TimeFormat } from '@/lib/time';
import { join } from '@/components/ui';

const DAY = 1440;
/** The bar's line starts at 06:00, or earlier on the hour when a span does. */
const MORNING = 6 * 60;

export const minutesOf = (hhmm: string) => Number(hhmm.slice(0, 2)) * 60 + Number(hhmm.slice(3, 5));

export const weeklyMinutes = (blocks: ShiftBlock[]) => blocks.reduce((n, b) => n + minutesOf(b.end) - minutesOf(b.start), 0);

type Segment = { from: number; to: number; kind: 'kept' | 'added' | 'removed' };

/** How a day compares with the week before it. */
export type DayMark = 'same' | 'added' | 'changed' | 'removed';

/** A day's spans; with a "before", what stayed, what was added and what was taken out. */
function segments(blocks: ShiftBlock[], before?: ShiftBlock[]): Segment[] {
  if (!before) return blocks.map((b) => ({ from: minutesOf(b.start), to: minutesOf(b.end), kind: 'kept' }));
  const slot = (list: ShiftBlock[]) => {
    const on = new Uint8Array(DAY / 5);
    for (const b of list) for (let m = minutesOf(b.start); m < minutesOf(b.end); m += 5) on[m / 5] = 1;
    return on;
  };
  const now = slot(blocks);
  const was = slot(before);
  const out: Segment[] = [];
  for (let i = 0; i < now.length; i++) {
    const kind = now[i] && was[i] ? 'kept' : now[i] ? 'added' : was[i] ? 'removed' : null;
    if (!kind) continue;
    const last = out.at(-1);
    if (last && last.kind === kind && last.to === i * 5) last.to += 5;
    else out.push({ from: i * 5, to: i * 5 + 5, kind });
  }
  return out;
}

/** A day against the same day before: unchanged, new, changed or taken out. */
export function dayMark(today: ShiftBlock[], was: ShiftBlock[]): DayMark {
  const key = (list: ShiftBlock[]) =>
    list
      .map((b) => `${b.start}-${b.end}`)
      .sort()
      .join(',');
  if (key(today) === key(was)) return 'same';
  if (was.length === 0) return 'added';
  if (today.length === 0) return 'removed';
  return 'changed';
}

const clockOf = (m: number) => (m === DAY ? '24:00' : `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`);

/** Spans written out ("5:00 م–7:00 م · 8:00 م–10:00 م"), each kept on one line. */
function Spans({ spans, format, locale }: { spans: { start: string; end: string }[]; format: TimeFormat; locale: string }) {
  return (
    <bdi>
      {spans.map((s, i) => (
        <span key={s.start}>
          {i > 0 ? ' · ' : null}
          <span className="whitespace-nowrap">
            {formatClock(s.start, format, locale)}–{formatClock(s.end, format, locale)}
          </span>
        </span>
      ))}
    </bdi>
  );
}

const markTone: Record<Exclude<DayMark, 'same'>, string> = {
  added: 'bg-brand-soft text-brand-strong',
  changed: 'bg-attention-soft text-attention',
  removed: 'bg-danger-soft text-danger',
};

/**
 * A week of working spans on the teacher's own clock: each day a row with its
 * spans written out (in 12 or 24 hours, as the teacher reads a clock) and a
 * small line from the morning to midnight whose spans fill in. With `before`,
 * the changes show: new, changed and removed days marked, unchanged ones
 * calmer, and added or taken-out time in their own colours.
 */
export function WeekView({
  blocks,
  before,
  locale,
  format,
  labels,
}: {
  blocks: ShiftBlock[];
  before?: ShiftBlock[];
  locale: string;
  format: TimeFormat;
  labels: { off: string; added?: string; removed?: string; marks?: Record<Exclude<DayMark, 'same'>, string> };
}) {
  const all = [...blocks, ...(before ?? [])];
  const start = Math.min(MORNING, ...all.map((b) => Math.floor(minutesOf(b.start) / 60) * 60));
  const span = DAY - start;
  const place = (from: number, to: number): CSSProperties => {
    const a = Math.max(from, start);
    return { insetInlineStart: `${((a - start) / span) * 100}%`, width: `${((Math.max(to, a) - a) / span) * 100}%` };
  };
  const ticks = [start, 12 * 60, 18 * 60, DAY].filter((m, i, list) => list.indexOf(m) === i);

  return (
    <div>
      <ul className="stagger divide-y divide-line">
        {weekOrder(locale).map((day, row) => {
          const today = blocks.filter((b) => b.weekday === day).sort((a, b) => a.start.localeCompare(b.start));
          const was = before?.filter((b) => b.weekday === day);
          const parts = segments(today, was);
          const mark = was ? dayMark(today, was) : 'same';
          const calm = was !== undefined && mark === 'same';
          return (
            <li key={day} data-mark={was ? mark : undefined} className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 py-2.5 sm:grid-cols-[7.5rem_1fr_11rem]">
              <span className={join('flex flex-wrap items-center gap-1.5 text-sm', calm ? 'text-muted' : 'font-semibold')}>
                <span>{weekdayName(day, locale)}</span>
                {mark !== 'same' && labels.marks ? <span className={join('rounded-full px-2 py-px text-[11px] font-semibold', markTone[mark])}>{labels.marks[mark]}</span> : null}
              </span>
              <span className="relative order-last col-span-2 h-2.5 overflow-hidden rounded-full bg-line/60 sm:order-none sm:col-span-1" aria-hidden="true">
                {parts.map((p) => (
                  <span
                    key={`${p.kind}-${p.from}`}
                    className={join(
                      'bar-fill absolute inset-y-0 rounded-full',
                      p.kind === 'kept' && (calm ? 'bg-brand-line' : 'bg-brand'),
                      p.kind === 'added' && 'bg-info',
                      p.kind === 'removed' && 'bg-[repeating-linear-gradient(135deg,var(--color-danger)_0_4px,transparent_4px_7px)]',
                    )}
                    style={{ ...place(p.from, p.to), animationDelay: `${120 + row * 45}ms` }}
                  />
                ))}
              </span>
              <span className={join('text-end text-sm tabular-nums', calm || today.length === 0 ? 'text-muted' : 'text-ink')}>
                {today.length === 0 ? labels.off : <Spans spans={today} format={format} locale={locale} />}
                {was && parts.some((p) => p.kind === 'removed') ? (
                  <span className="block text-danger line-through">
                    <Spans
                      spans={parts.filter((p) => p.kind === 'removed').map((p) => ({ start: clockOf(p.from), end: clockOf(p.to) }))}
                      format={format}
                      locale={locale}
                    />
                  </span>
                ) : null}
              </span>
            </li>
          );
        })}
      </ul>
      <div className="mt-1 hidden grid-cols-[7.5rem_1fr_11rem] gap-x-3 sm:grid" aria-hidden="true">
        <span />
        <span className="relative h-4 text-[11px] tabular-nums text-muted">
          {ticks.map((m, i) => (
            <span
              key={m}
              className={join('absolute top-0', i === 0 ? '' : i === ticks.length - 1 ? '-translate-x-full rtl:translate-x-full' : '-translate-x-1/2 rtl:translate-x-1/2')}
              style={{ insetInlineStart: `${((m - start) / span) * 100}%` }}
            >
              <bdi>{formatClock(clockOf(m === DAY ? 0 : m), format, locale)}</bdi>
            </span>
          ))}
        </span>
        <span />
      </div>
      {before && labels.added && labels.removed && blocks.length > 0 && before.length > 0 ? (
        <p className="mt-3 flex flex-wrap gap-4 text-xs text-muted">
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2.5 rounded-full bg-info" /> {labels.added}
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2.5 rounded-full bg-[repeating-linear-gradient(135deg,var(--color-danger)_0_3px,transparent_3px_5px)]" /> {labels.removed}
          </span>
        </p>
      ) : null}
    </div>
  );
}
