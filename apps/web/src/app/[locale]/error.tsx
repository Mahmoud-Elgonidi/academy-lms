'use client';

import { useTranslations } from 'next-intl';
import { useEffect } from 'react';

/** A page that could not load (the API down, or an answer it could not read): say so, and offer to try again. */
export default function PageError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const t = useTranslations('errors');
  const common = useTranslations('common');
  useEffect(() => {
    console.error(error);
  }, [error]);
  return (
    <div className="mx-auto grid min-h-[50dvh] max-w-md place-items-center px-6 text-center">
      <div className="appear">
        <h1 className="text-2xl font-bold">{t('title')}</h1>
        <p className="mt-2 text-muted">{t('lead')}</p>
        <button onClick={reset} className="mt-6 inline-flex min-h-11 items-center rounded-control bg-brand px-5 font-semibold text-white hover:bg-brand-strong">
          {common('retry')}
        </button>
      </div>
    </div>
  );
}
