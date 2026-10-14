'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { saveMeetingLink } from '@/app/[locale]/teach/actions';
import { Button, Card, Field, Input, Notice } from '@/components/ui';

/**
 * The teacher's own Zoom or Meet link (the same as "Your link" on the
 * today page in /teach): when the academy's lessons run on outside links,
 * families join through it and a lesson cannot open without it.
 */
export function MeetingLink({ url, used, label, hint }: { url: string | null; used: boolean; label: string; hint: string }) {
  const t = useTranslations('account.meetingLink');
  const [pending, start] = useTransition();
  const [value, setValue] = useState(url ?? '');
  const [notice, setNotice] = useState<{ tone: 'brand' | 'danger'; text: string }>();

  const save = () =>
    start(async () => {
      const done = await saveMeetingLink(value);
      setNotice(done.ok ? { tone: 'brand', text: t(value.trim() === '' ? 'removed' : 'saved') } : { tone: 'danger', text: done.errors?.url?.[0] ?? done.message ?? t('failed') });
    });

  return (
    <Card>
      <h2 className="font-heading text-lg font-bold">{t('title')}</h2>
      <p className="mt-1 text-sm text-muted">{hint}</p>
      {used && !url ? (
        <div className="mt-3">
          <Notice tone="attention">{t('needed')}</Notice>
        </div>
      ) : null}
      {notice ? (
        <div className="mt-3">
          <Notice tone={notice.tone}>{notice.text}</Notice>
        </div>
      ) : null}
      <div className="mt-4 space-y-3">
        <Field label={label}>
          <Input
            type="url"
            dir="ltr"
            inputMode="url"
            maxLength={500}
            placeholder="https://meet.google.com/…"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            name="meeting_link"
          />
        </Field>
        <Button disabled={pending || value.trim() === (url ?? '')} onClick={save}>
          {t('save')}
        </Button>
      </div>
    </Card>
  );
}
