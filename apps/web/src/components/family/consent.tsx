'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { grantConsent, withdrawConsent } from '@/app/[locale]/family/actions';
import { Badge, Button, Card, Notice } from '@/components/ui';
import type { ChildConsent } from '@/lib/family/types';

/** One child: the wording and "I agree" while they wait; who agreed and "withdraw" once agreed. */
export function ConsentCard({ child }: { child: ChildConsent }) {
  const t = useTranslations('privacy');
  const [ticked, setTicked] = useState(false);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const waiting = child.state === 'waiting';

  const agree = () =>
    start(async () => {
      const done = await grantConsent(child.student.id, child.text.version, ticked);
      setError(done.ok ? undefined : done.message);
    });
  const withdraw = () => {
    if (!window.confirm(t('withdrawConfirm', { student: child.student.name }))) return;
    start(async () => {
      const done = await withdrawConsent(child.student.id);
      setError(done.ok ? undefined : done.message);
      setTicked(false);
    });
  };

  return (
    <Card className={waiting ? 'ring-2 ring-attention/40' : undefined}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="font-heading text-lg font-bold">{child.student.name}</h2>
        <Badge tone={waiting ? 'attention' : 'brand'}>{waiting ? t('waiting') : t('granted')}</Badge>
      </div>
      {waiting ? (
        <div className="mt-3 space-y-3">
          <p className="leading-7">{child.text.intro}</p>
          <ul className="grid gap-2">
            {child.text.sections.map((part) => (
              <li key={part.title} className="rounded-control bg-brand-soft px-3 py-2 text-sm">
                <strong className="block">{part.title}</strong>
                {part.body}
              </li>
            ))}
          </ul>
          <label className="flex cursor-pointer items-start gap-3 rounded-control border border-line p-3 text-sm font-semibold">
            <input type="checkbox" className="mt-1 size-4 accent-brand" checked={ticked} onChange={(e) => setTicked(e.target.checked)} />
            <span>{child.text.agree}</span>
          </label>
          {error ? <Notice tone="danger">{error}</Notice> : null}
          <Button onClick={agree} disabled={pending}>
            {t('agree')}
          </Button>
        </div>
      ) : (
        <div className="mt-2 space-y-3">
          <p className="text-sm text-muted">{child.by}</p>
          {error ? <Notice tone="danger">{error}</Notice> : null}
          {child.can_withdraw ? (
            <Button variant="danger" onClick={withdraw} disabled={pending}>
              {t('withdraw')}
            </Button>
          ) : null}
        </div>
      )}
    </Card>
  );
}
