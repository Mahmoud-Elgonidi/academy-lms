'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useEffect, useId, useRef, useState, useTransition } from 'react';
import { Icon } from '@/components/icons';
import { join } from '@/components/ui';
import { ZonePicker, useDeviceZone, useMinute } from '@/components/zone-parts';
import { forgetChosenZone, listZones, saveZone, saveZoneChip } from '@/lib/actions/shared';
import { zoneName } from '@/lib/format';
import { formatClock, type TimeFormat } from '@/lib/time';
import { cityOf, sameClock, wallClockIn, zoneDifferences, type Zone } from '@/lib/zones';

type Props = {
  /** The zone the pages are shown in (`me.display_timezone`) and its label (`me.timezone_label`). */
  shown: string;
  shownLabel: string;
  /** The account's own zone (`me.timezone`), or null while none was chosen. */
  account: string | null;
  academy: { zone: string; label: string } | null;
  /** `me.timezone_mismatch`: the device and the account keep different clocks. */
  mismatch: boolean;
  /** The zone chosen on this browser (its cookie), if any. */
  chosen: string | null;
  /** The device's zone as the page wrote it last (its cookie), for the first drawing. */
  device: string | null;
  format: TimeFormat;
  /** `me.zone_chip`: the person shows the chip (hidden from its card, shown again from My account). */
  wanted: boolean;
};

/**
 * The zone chip (as in the panels and the app): a pill in the bottom corner
 * at the start, above the phone's tab bar, with the city of the zone the
 * pages are shown in and its time, ticking on the minute. A dot when the
 * device or the account keeps another clock. Opened, a card: your zone, the
 * academy's and the device's when they keep another clock ("Use my device's
 * zone"), and "Change my zone" with the cities by region. Saved on the
 * account (`PATCH /v1/me`), then the page is read again.
 *
 * "Hide this" at the bottom of the card hides it on every page (and in the
 * app and the panels): it sinks away, the account keeps the choice
 * (`zone_chip`), and a note says it comes back from My account. Hidden, only
 * that note shows until it is closed.
 */
export function ZoneChip({ shown, shownLabel, account, academy, mismatch, chosen, device: fromCookie, format, wanted }: Props) {
  const t = useTranslations('zone');
  const common = useTranslations('common');
  const locale = useLocale();
  const now = useMinute();
  const device = useDeviceZone(fromCookie);
  const [open, setOpen] = useState(false);
  const [changing, setChanging] = useState(false);
  const [zones, setZones] = useState<Zone[] | null | 'failed'>(null);
  const [notice, setNotice] = useState<{ tone: 'brand' | 'danger'; text: string }>();
  const [pending, start] = useTransition();
  const [loading, load] = useTransition();
  const [leaving, setLeaving] = useState(false);
  const [hidNote, setHidNote] = useState(false);
  const box = useRef<HTMLDivElement>(null);
  const pill = useRef<HTMLButtonElement>(null);
  const card = useId();

  // A zone chosen on this browser that is no longer the account's (changed in the app or the panels, or someone else's): let go of it.
  useEffect(() => {
    if (chosen && chosen !== account) void forgetChosenZone();
  }, [chosen, account]);

  // The minute beats once it changes (not when the page comes in).
  const minute = Math.floor(now.getTime() / 60_000);
  const [first] = useState(minute);

  useEffect(() => {
    if (!open) return;
    const away = (e: PointerEvent) => {
      if (box.current && !box.current.contains(e.target as Node)) setOpen(false);
    };
    const escape = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return;
      setOpen(false);
      pill.current?.focus();
    };
    document.addEventListener('pointerdown', away);
    document.addEventListener('keydown', escape);
    return () => {
      document.removeEventListener('pointerdown', away);
      document.removeEventListener('keydown', escape);
    };
  }, [open]);

  const fetchZones = () =>
    load(async () => {
      const found = await listZones();
      setZones(found ?? 'failed');
    });

  const toggle = () => {
    const next = !open;
    setOpen(next);
    setChanging(false);
    setNotice(undefined);
    if (next && (zones === null || zones === 'failed')) fetchZones();
  };

  const list = Array.isArray(zones) ? zones : [];
  const labelOf = (zone: string) => list.find((z) => z.id === zone)?.label ?? zoneName(zone, locale);
  const time = (zone: string) => formatClock(wallClockIn(zone, now), format, locale);

  const differs = zoneDifferences({ shown, account, device, mismatch, at: now });
  const academyRow = academy && !sameClock(academy.zone, shown, now) ? academy : null;
  const deviceToUse = device && (differs.device || differs.account) ? device : null;

  const save = (zone: string, label: string, follow: 'chosen' | 'device') =>
    start(async () => {
      const done = await saveZone(zone, follow);
      if (done.ok) {
        setChanging(false);
        setNotice({ tone: 'brand', text: t('saved', { zone: label }) });
      } else {
        setNotice({ tone: 'danger', text: done.message ?? t('failed') });
      }
    });

  const hide = () =>
    start(async () => {
      const done = await saveZoneChip(false);
      setLeaving(false);
      if (done.ok) {
        setOpen(false);
        setHidNote(true);
      } else {
        setNotice({ tone: 'danger', text: done.message ?? t('hideFailed') });
      }
    });

  // It sinks away first (only where motion is allowed: else at once), then the account keeps the choice.
  const startHiding = () => {
    setNotice(undefined);
    if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches) setLeaving(true);
    else hide();
  };

  if (!wanted) {
    return hidNote ? (
      <div role="status" data-testid="zone-hidden" className="zone-note fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] start-3 z-30 flex max-w-[min(22rem,calc(100vw-1.5rem))] items-start gap-2 rounded-card border border-line bg-white py-2.5 pe-2 ps-3.5 text-sm shadow-[0_10px_30px_-14px_color-mix(in_oklab,var(--neutral)_60%,transparent)] lg:bottom-5 lg:start-[17rem]">
        <p className="py-1">{t('hidden')}</p>
        <button type="button" onClick={() => setHidNote(false)} aria-label={common('close')} className="grid size-8 shrink-0 place-items-center rounded-control text-muted hover:bg-paper">
          <Icon name="x" size={14} />
        </button>
      </div>
    ) : null;
  }

  const rows: { kind: string; title: string; label: string; zone: string }[] = [
    { kind: 'mine', title: t('yours'), label: shownLabel, zone: shown },
    ...(academyRow ? [{ kind: 'academy', title: t('academy'), label: academyRow.label, zone: academyRow.zone }] : []),
    ...(differs.device ? [{ kind: 'device', title: t('device'), label: labelOf(differs.device), zone: differs.device }] : []),
    ...(differs.account ? [{ kind: 'account', title: t('account'), label: labelOf(differs.account), zone: differs.account }] : []),
  ];

  return (
    <div
      ref={box}
      data-testid="zone-chip"
      onAnimationEnd={(e) => {
        if (leaving && e.target === e.currentTarget) hide();
      }}
      className={join(leaving && 'zone-leaving pointer-events-none', 'zone-chip fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] start-3 z-30 lg:bottom-5 lg:start-[17rem]')}
    >
      {open ? (
        <div
          id={card}
          role="dialog"
          aria-label={t('title')}
          className="zone-card absolute bottom-full start-0 mb-2 w-[min(22rem,calc(100vw-1.5rem))] rounded-card border border-line bg-white p-4 shadow-[0_18px_48px_-20px_color-mix(in_oklab,var(--neutral)_55%,transparent)]"
        >
          <div className="flex items-center justify-between gap-3">
            {changing ? (
              <button type="button" onClick={() => setChanging(false)} className="flex min-h-9 items-center gap-1.5 rounded-control px-1 text-sm font-semibold text-muted hover:text-ink">
                <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
                {t('back')}
              </button>
            ) : (
              <h2 className="font-heading text-base font-bold">{t('title')}</h2>
            )}
            <button type="button" onClick={() => setOpen(false)} aria-label={common('close')} className="grid size-9 place-items-center rounded-control text-muted hover:bg-paper">
              <Icon name="x" size={16} />
            </button>
          </div>

          {notice ? (
            <p role="status" className={join('mt-2 rounded-control px-3 py-2 text-sm', notice.tone === 'brand' ? 'bg-brand-soft text-brand-strong' : 'bg-danger-soft text-danger')}>
              {notice.text}
            </p>
          ) : null}

          {changing ? (
            <div className="mt-2">
              {Array.isArray(zones) ? (
                <ZonePicker zones={zones} current={shown} onChoose={(zone) => save(zone.id, zone.label, 'chosen')} disabled={pending} autoFocus />
              ) : zones === 'failed' ? (
                <p className="py-4 text-center text-sm text-danger">
                  {t('unavailable')}{' '}
                  <button type="button" onClick={fetchZones} className="font-semibold underline">
                    {common('retry')}
                  </button>
                </p>
              ) : (
                <p className="py-6 text-center text-sm text-muted">{t('loading')}</p>
              )}
            </div>
          ) : (
            <>
              <dl className="mt-3 space-y-2">
                {rows.map((row) => (
                  <div key={row.kind} data-kind={row.kind} className={join('flex items-center justify-between gap-3 rounded-control px-3 py-2', row.kind === 'mine' ? 'bg-brand-soft' : 'bg-paper')}>
                    <div className="min-w-0">
                      <dt className="text-xs text-muted">{row.title}</dt>
                      <dd className="truncate text-sm font-semibold">{row.label}</dd>
                    </div>
                    <bdi className="shrink-0 font-heading text-base font-bold tabular-nums" suppressHydrationWarning>
                      {time(row.zone)}
                    </bdi>
                  </div>
                ))}
              </dl>
              <p className="mt-3 text-sm text-muted">{differs.device ? t('deviceDiffers') : differs.account ? t('accountDiffers') : t('hint')}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                {deviceToUse ? (
                  <button
                    type="button"
                    disabled={pending}
                    onClick={() => save(deviceToUse, labelOf(deviceToUse), 'device')}
                    className="inline-flex min-h-10 items-center rounded-control bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-strong disabled:opacity-60"
                  >
                    {t('useDevice')}
                  </button>
                ) : null}
                <button
                  type="button"
                  disabled={pending || loading}
                  onClick={() => {
                    setNotice(undefined);
                    setChanging(true);
                    if (zones === 'failed') fetchZones();
                  }}
                  className={join(
                    'inline-flex min-h-10 items-center rounded-control px-4 text-sm font-semibold disabled:opacity-60',
                    deviceToUse ? 'border border-line bg-white text-ink hover:border-brand-line hover:bg-brand-soft' : 'bg-brand text-white hover:bg-brand-strong',
                  )}
                >
                  {t('change')}
                </button>
              </div>
              <button
                type="button"
                disabled={pending || leaving}
                onClick={startHiding}
                className="mt-3 flex min-h-9 w-full items-center justify-center gap-1.5 rounded-control text-xs font-semibold text-muted hover:bg-paper hover:text-ink disabled:opacity-60"
              >
                <Icon name="eyeOff" size={14} />
                {t('hide')}
              </button>
            </>
          )}
        </div>
      ) : null}

      <button
        ref={pill}
        type="button"
        onClick={toggle}
        aria-expanded={open}
        aria-controls={open ? card : undefined}
        aria-label={t('open', { zone: shownLabel })}
        className="zone-pill relative flex min-h-10 items-center gap-2 rounded-full border border-line bg-white/95 py-1.5 pe-3.5 ps-2.5 text-sm shadow-[0_10px_30px_-14px_color-mix(in_oklab,var(--neutral)_60%,transparent)] backdrop-blur hover:border-brand-line"
      >
        <span aria-hidden="true" className="grid size-7 place-items-center rounded-full bg-brand-soft text-brand">
          <Icon name="clock" size={16} />
        </span>
        <span className="max-w-[9rem] truncate font-semibold">{cityOf(shownLabel)}</span>
        <span aria-hidden="true" className="text-muted">
          ·
        </span>
        <bdi key={minute} className={join('font-heading font-bold tabular-nums', minute !== first && 'zone-beat')} suppressHydrationWarning>
          {time(shown)}
        </bdi>
        {differs.dot ? <span data-testid="zone-dot" aria-hidden="true" className="zone-dot absolute -top-0.5 end-0.5 size-2.5 rounded-full bg-attention ring-2 ring-white" /> : null}
      </button>
    </div>
  );
}
