'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { unlinkSocial } from '@/lib/actions/shared';
import { Button } from '@/components/ui';

/** "Unlink", after a word on what it means; the API's refusal (the only way in) shows under it. */
export function UnlinkSocial({ provider, label }: { provider: string; label: string }) {
  const t = useTranslations('account');
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const unlink = () => {
    if (!window.confirm(t('unlinkConfirm', { provider: label }))) return;
    start(async () => {
      const done = await unlinkSocial(provider);
      setError(done.ok ? undefined : done.message);
    });
  };
  return (
    <div className="text-end">
      <Button variant="danger" onClick={unlink} disabled={pending}>
        {t('unlink')}
      </Button>
      {error ? <p className="mt-1 max-w-64 text-sm text-danger">{error}</p> : null}
    </div>
  );
}
