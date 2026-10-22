import type { ShiftBlock } from '@/lib/teach/types';
import { weekOrder, weekdayName } from '@/lib/format';
import { formatClock, type TimeFormat } from '@/lib/time';
import { join } from '@/components/ui';

const DAY = 1440;

export const minutesOf = (hhmm: string) => Number(hhmm.slice(0, 2)) * 60 + Number(hhmm.slice(3, 5));

export const weeklyMinutes = (blocks: ShiftBlock[]) => blocks.reduce((n, b) => n + minutesOf(b.end) - minutesOf(b.start), 0);

type Segment = { from: number; to: number; kind: 'kept' | 'added' | 'removed' };

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

/**
 * A week of working spans on the teacher's own clock: each day as a line from
 * midnight to midnight (in the reading direction), with its spans written out
 * in 12 or 24 hours (`format`, as the teacher reads a clock). With `before`,
 * the changes show: added and taken-out time in their own colours.
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
  labels: { off: string; added?: string; removed?: string };
}) {
  return (
    <div>
      <ul className="space-y-2.5">
        {weekOrder(locale).map((day) => {
          const today = blocks.filter((b) => b.weekday === day).sort((a, b) => a.start.localeCompare(b.start));
          const was = before?.filter((b) => b.weekday === day);
          const parts = segments(today, was);
          return (
            <li key={day} className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 sm:grid-cols-[6rem_1fr_11rem]">
              <span className="text-sm font-semibold">{weekdayName(day, locale)}</span>
              <span className="relative order-last col-span-2 h-3 overflow-hidden rounded-full bg-line/60 sm:order-none sm:col-span-1" aria-hidden="true">
                {parts.map((p) => (
                  <span
                    key={`${p.kind}-${p.from}`}
                    className={join(
                      'absolute inset-y-0 rounded-full',
                      p.kind === 'kept' && 'bg-brand',
                      p.kind === 'added' && 'bg-info',
                      p.kind === 'removed' && 'bg-[repeating-linear-gradient(135deg,var(--color-danger)_0_4px,transparent_4px_7px)]',
                    )}
                    style={{ insetInlineStart: `${(p.from / DAY) * 100}%`, width: `${((p.to - p.from) / DAY) * 100}%` }}
                  />
                ))}
              </span>
              <span className="text-end text-sm tabular-nums text-muted">
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
      {before && labels.added && labels.removed ? (
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
