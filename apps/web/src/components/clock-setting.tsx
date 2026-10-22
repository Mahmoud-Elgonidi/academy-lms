'use client';

import { useTranslations } from 'next-intl';
import { useId, useState, useTransition } from 'react';
import { saveTimeFormat } from '@/lib/actions/shared';
import { Button, Card, Notice, join } from '@/components/ui';
import type { TimeFormat } from '@/lib/time';

type Choice = 'academy' | TimeFormat;

/**
 * How the person reads a clock (the same as "شكل الساعة" on My settings in
 * the panels): the academy's (which it names), 12 hours or 24. Saved on the
 * account, so the app, the panels and messages follow it too; the frame reads
 * it again and every page writes its clocks the new way.
 */
export function ClockSetting({ choice, academy }: { choice: TimeFormat | null; academy: TimeFormat }) {
  const t = useTranslations('account.clock');
  const common = useTranslations('common');
  const title = useId();
  const saved: Choice = choice ?? 'academy';
  const [value, setValue] = useState<Choice>(saved);
  const [notice, setNotice] = useState<{ tone: 'brand' | 'danger'; text: string }>();
  const [pending, start] = useTransition();

  const options: { value: Choice; label: string }[] = [
    { value: 'academy', label: t('academy', { hours: academy === 'h24' ? '24' : '12' }) },
    { value: 'h12', label: t('h12') },
    { value: 'h24', label: t('h24') },
  ];

  const save = () =>
    start(async () => {
      const done = await saveTimeFormat(value === 'academy' ? null : value);
      setNotice(done.ok ? { tone: 'brand', text: t('saved') } : { tone: 'danger', text: done.message ?? t('failed') });
    });

  return (
    <Card>
      <form
        aria-labelledby={title}
        onSubmit={(e) => {
          e.preventDefault();
          save();
        }}
      >
        <h2 id={title} className="font-heading text-lg font-bold">
          {t('title')}
        </h2>
        <p className="mt-1 text-sm text-muted">{t('hint')}</p>
        {notice ? (
          <div className="mt-3">
            <Notice tone={notice.tone}>{notice.text}</Notice>
          </div>
        ) : null}
        <fieldset className="mt-4">
          <legend className="sr-only">{t('title')}</legend>
          <div className="grid gap-2 sm:grid-cols-3">
            {options.map((o) => (
              <label
                key={o.value}
                className={join(
                  'flex min-h-11 cursor-pointer items-center gap-3 rounded-control border px-3 py-2.5 text-sm',
                  value === o.value ? 'border-brand bg-brand-soft font-semibold' : 'border-line bg-white hover:border-brand-line',
                )}
              >
                <input
                  type="radio"
                  name="time_format"
                  value={o.value}
                  checked={value === o.value}
                  onChange={() => {
                    setValue(o.value);
                    setNotice(undefined);
                  }}
                  className="accent-brand"
                />
                {o.label}
              </label>
            ))}
          </div>
        </fieldset>
        <div className="mt-4">
          <Button disabled={pending || value === saved}>{common('save')}</Button>
        </div>
      </form>
    </Card>
  );
}
