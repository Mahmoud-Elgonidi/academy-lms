'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useId, useState, useTransition } from 'react';
import { Button, Card, Notice } from '@/components/ui';
import { ZonePicker, useDeviceZone } from '@/components/zone-parts';
import { saveZone } from '@/lib/actions/shared';
import { zoneName } from '@/lib/format';
import { zoneDifferences, type Zone } from '@/lib/zones';

/**
 * My account's time zone (as "My settings" has it in the panels and the
 * zone chip in the corner): which zone the times are shown in now, the
 * cities by region to choose another, and the device's zone when it keeps
 * another clock. Saved on the account (`PATCH /v1/me`), so the app, the
 * panels and messages follow it; the page is read again.
 */
export function ZoneSetting({
  zones,
  shown,
  shownLabel,
  account,
  device: fromCookie,
}: {
  zones: Zone[] | null;
  shown: string;
  shownLabel: string;
  account: string | null;
  device: string | null;
}) {
  const t = useTranslations('zone');
  const common = useTranslations('common');
  const locale = useLocale();
  const title = useId();
  const device = useDeviceZone(fromCookie);
  const [picked, setPicked] = useState<Zone | null>(null);
  const [notice, setNotice] = useState<{ tone: 'brand' | 'danger'; text: string }>();
  const [pending, start] = useTransition();

  const labelOf = (zone: string) => zones?.find((z) => z.id === zone)?.label ?? zoneName(zone, locale);
  const differs = zoneDifferences({ shown, account, device, mismatch: false });
  const deviceToUse = device && (differs.device || differs.account) ? device : null;

  const save = (zone: string, label: string, follow: 'chosen' | 'device') =>
    start(async () => {
      const done = await saveZone(zone, follow);
      if (done.ok) setPicked(null);
      setNotice(done.ok ? { tone: 'brand', text: t('saved', { zone: label }) } : { tone: 'danger', text: done.message ?? t('failed') });
    });

  return (
    <Card>
      <form
        aria-labelledby={title}
        onSubmit={(e) => {
          e.preventDefault();
          if (picked) save(picked.id, picked.label, 'chosen');
        }}
      >
        <h2 id={title} className="font-heading text-lg font-bold">
          {t('field.title')}
        </h2>
        <p className="mt-1 text-sm text-muted">{t('field.hint')}</p>
        <p className="mt-2 text-sm font-semibold" data-testid="zone-now">
          {t('field.now', { zone: shownLabel })}
        </p>
        {differs.device || differs.account ? (
          <p className="mt-1 text-sm text-muted">
            {differs.device ? `${t('device')}: ${labelOf(differs.device)}. ${t('deviceDiffers')}` : `${t('account')}: ${labelOf(differs.account!)}. ${t('accountDiffers')}`}
          </p>
        ) : null}
        {notice ? (
          <div className="mt-3">
            <Notice tone={notice.tone}>{notice.text}</Notice>
          </div>
        ) : null}
        {zones ? (
          <ZonePicker
            className="mt-4"
            zones={zones}
            current={shown}
            picked={picked?.id ?? shown}
            disabled={pending}
            onChoose={(zone) => {
              setPicked(zone.id === shown ? null : zone);
              setNotice(undefined);
            }}
          />
        ) : (
          <div className="mt-3">
            <Notice tone="danger">{t('unavailable')}</Notice>
          </div>
        )}
        {picked ? <p className="mt-2 text-sm">{t('field.chosen', { zone: picked.label })}</p> : null}
        <div className="mt-4 flex flex-wrap gap-2">
          <Button disabled={pending || !picked}>{common('save')}</Button>
          {deviceToUse ? (
            <Button type="button" variant="quiet" disabled={pending} onClick={() => save(deviceToUse, labelOf(deviceToUse), 'device')}>
              {t('useDevice')}
            </Button>
          ) : null}
        </div>
      </form>
    </Card>
  );
}
