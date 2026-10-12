import { cookies } from 'next/headers';
import { getLocale, getTranslations } from 'next-intl/server';
import { Notice } from '@/components/ui';
import { TWO_FACTOR_COOKIE, outcomeOf } from '@/lib/social';
import { webProviders } from '@/lib/social-server';
import { branding } from '@/lib/viewer';
import { SignInForm } from './sign-in-form';

type Props = { searchParams: Promise<{ next?: string; expired?: string; social?: string; provider?: string; step?: string; staff?: string }> };

export default async function SignInPage({ searchParams }: Props) {
  const [{ next, expired, social, provider, step, staff }, t, locale, look, providers, jar] = await Promise.all([
    searchParams,
    getTranslations('signIn'),
    getLocale(),
    branding(),
    webProviders(),
    cookies(),
  ]);
  const academy = locale === 'ar' ? look.name.ar : look.name.en;
  const outcome = outcomeOf(social);
  const providerLabel = providers.find((p) => p.provider === provider)?.label ?? provider ?? '';
  // Back from Google or Apple to an account with two-step sign-in: its authenticator's step.
  const twoFactorToken = step === 'two-factor' ? jar.get(TWO_FACTOR_COOKIE)?.value : undefined;

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
        {outcome ? (
          <div className="mb-4">
            <Notice tone={outcome === 'denied' ? 'attention' : 'danger'}>{t(`social.${outcome}`, { provider: providerLabel })}</Notice>
          </div>
        ) : null}
        <SignInForm next={next ?? ''} providers={providers} twoFactorToken={twoFactorToken} staff={staff === '1'} />
        {/* The academy's own privacy notice and terms: Laravel's pages on this host (the web part sends them there), in this language. */}
        <nav aria-label={t('legal')} className="mt-6 flex flex-wrap justify-center gap-x-2 text-sm text-muted">
          <a href={`/privacy?lang=${locale}`} target="_blank" rel="noopener" className="underline underline-offset-4 hover:text-brand-strong">
            {t('privacy')}
          </a>
          <span aria-hidden="true">·</span>
          <a href={`/terms?lang=${locale}`} target="_blank" rel="noopener" className="underline underline-offset-4 hover:text-brand-strong">
            {t('terms')}
          </a>
        </nav>
      </div>
    </main>
  );
}
