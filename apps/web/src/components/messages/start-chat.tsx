'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition, type ReactNode } from 'react';
import { useRouter } from '@/i18n/navigation';
import { Notice } from '@/components/ui';
import { openConversation } from '@/lib/actions/shared';

/** A conversation nobody opened yet: the first tap makes it, then it opens. */
export function StartChat({ base, withId, learnerId, className, children }: { base: string; withId: string; learnerId?: string; className: string; children: ReactNode }) {
  const common = useTranslations('common');
  const router = useRouter();
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();
  const go = () =>
    start(async () => {
      const done = await openConversation(withId, learnerId);
      if (!done.ok) {
        setMessage(done.message ?? common('problem'));
        return;
      }
      router.push(`${base}/${done.id}`);
    });
  return (
    <>
      <button type="button" className={className} disabled={pending} onClick={go}>
        {children}
      </button>
      {message ? <Notice tone="danger">{message}</Notice> : null}
    </>
  );
}
