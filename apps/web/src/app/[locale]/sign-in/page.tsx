import { getLocale, getTranslations } from 'next-intl/server';
import { Notice } from '@/components/ui';
import { branding } from '@/lib/viewer';
import { SignInForm } from './sign-in-form';

type Props = { searchParams: Promise<{ next?: string; expired?: string }> };

export default async function SignInPage({ searchParams }: Props) {
  const [{ next, expired }, t, locale, look] = await Promise.all([searchParams, getTranslations('signIn'), getLocale(), branding()]);
  const academy = locale === 'ar' ? look.name.ar : look.name.en;

  return (
    <main className="grid min-h-dvh place-items-center bg-[radial-gradient(ellipse_at_top,var(--color-brand-soft),transparent_60%)] px-4 py-10">
      <div className="appear w-full max-w-sm">
        <div className="mb-6 text-center">
          {look.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- the academy's logo from its branding
            <img src={look.logo_url} alt="" className="mx-auto mb-4 h-14 w-auto" />
          ) : (
            <div className="mx-auto mb-4 grid size-14 place-items-center rounded-card bg-brand text-2xl font-bold text-white">{academy.slice(0, 1)}</div>
          )}
          <h1 className="text-2xl font-bold">{t('title')}</h1>
          <p className="mt-1 text-muted">{t('subtitle', { academy })}</p>
        </div>
        {expired ? (
          <div className="mb-4">
            <Notice tone="attention">{t('expired')}</Notice>
          </div>
        ) : null}
        <SignInForm next={next ?? ''} />
      </div>
    </main>
  );
}
