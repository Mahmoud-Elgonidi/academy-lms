'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { confirmContact, sendContactCode } from '@/lib/actions/shared';
import { Button, Card, Field, Input, Notice } from '@/components/ui';

type Kind = 'email' | 'phone';

/**
 * The email and mobile number on this account (the same as "Email and
 * mobile" on My settings in the panels): a new one gets a code, and nothing
 * changes until the code comes back. A child the academy runs under a
 * guardian, or the platform team signed in as someone, sees them without
 * changing them; the API says which (`can_change_contact`).
 */
export function ContactChange({ email, phone, canChange }: { email: string | null; phone: string | null; canChange: boolean }) {
  const t = useTranslations('account.contact');
  const [pending, start] = useTransition();
  const [kind, setKind] = useState<Kind>();
  const [value, setValue] = useState('');
  const [sent, setSent] = useState<{ challenge: string; sentTo: string; until: string }>();
  const [code, setCode] = useState('');
  const [notice, setNotice] = useState<{ tone: 'brand' | 'danger'; text: string }>();

  const reset = () => {
    setKind(undefined);
    setValue('');
    setSent(undefined);
    setCode('');
  };
  const send = () =>
    start(async () => {
      if (!kind) return;
      const done = await sendContactCode(kind, value);
      if (!done.ok) return setNotice({ tone: 'danger', text: done.message ?? t('failed') });
      setNotice(undefined);
      setSent({ challenge: done.challenge, sentTo: done.sentTo, until: done.until });
    });
  const confirm = () =>
    start(async () => {
      if (!kind || !sent) return;
      const done = await confirmContact(sent.challenge, code);
      if (!done.ok) return setNotice({ tone: 'danger', text: done.message ?? t('failed') });
      setNotice({ tone: 'brand', text: t(kind === 'email' ? 'done_email' : 'done_phone') });
      reset();
    });

  const rows: { kind: Kind; value: string | null }[] = [
    { kind: 'email', value: email },
    { kind: 'phone', value: phone },
  ];

  return (
    <Card>
      <h2 className="font-heading text-lg font-bold">{t('title')}</h2>
      <p className="mt-1 text-sm text-muted">{t('hint')}</p>
      {notice ? (
        <div className="mt-3">
          <Notice tone={notice.tone}>{notice.text}</Notice>
        </div>
      ) : null}
      <ul className="mt-4 divide-y divide-line">
        {rows.map((row) => (
          <li key={row.kind} data-kind={row.kind} className="flex flex-wrap items-center justify-between gap-3 py-3">
            <div className="min-w-0">
              <p className="font-semibold">{t(row.kind)}</p>
              <p className="text-sm break-all text-muted" dir="ltr">
                {row.value ?? t('none')}
              </p>
            </div>
            {canChange && kind === undefined ? (
              <Button
                variant="quiet"
                disabled={pending}
                onClick={() => {
                  setNotice(undefined);
                  setKind(row.kind);
                }}
              >
                {t(row.value ? `change_${row.kind}` : `add_${row.kind}`)}
              </Button>
            ) : null}
          </li>
        ))}
      </ul>
      {!canChange ? <p className="mt-2 text-sm text-muted">{t('managed')}</p> : null}

      {kind && !sent ? (
        <div className="mt-3 space-y-2 rounded-control bg-paper p-3">
          <Field label={t(`new_${kind}`)} hint={kind === 'phone' ? t('phoneHint') : undefined}>
            <Input
              type={kind === 'email' ? 'email' : 'tel'}
              autoComplete={kind === 'email' ? 'email' : 'tel'}
              dir="ltr"
              maxLength={255}
              value={value}
              onChange={(e) => setValue(e.target.value)}
            />
          </Field>
          <div className="flex flex-wrap gap-2">
            <Button disabled={pending || value.trim() === ''} onClick={send}>
              {t('send')}
            </Button>
            <Button variant="quiet" disabled={pending} onClick={reset}>
              {t('back')}
            </Button>
          </div>
        </div>
      ) : null}

      {kind && sent ? (
        <div className="mt-3 space-y-2 rounded-control bg-paper p-3">
          <p className="text-sm">{t('codeHint', { contact: sent.sentTo, until: sent.until })}</p>
          <Field label={t('code')}>
            <Input inputMode="numeric" autoComplete="one-time-code" dir="ltr" maxLength={12} value={code} onChange={(e) => setCode(e.target.value)} />
          </Field>
          <div className="flex flex-wrap gap-2">
            <Button disabled={pending || code.trim() === ''} onClick={confirm}>
              {t('confirm')}
            </Button>
            <Button variant="quiet" disabled={pending} onClick={send}>
              {t('resend')}
            </Button>
            <Button variant="quiet" disabled={pending} onClick={reset}>
              {t('back')}
            </Button>
          </div>
        </div>
      ) : null}
    </Card>
  );
}
