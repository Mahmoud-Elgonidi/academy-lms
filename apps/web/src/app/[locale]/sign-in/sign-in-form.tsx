'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useActionState, useEffect, useState } from 'react';
import { Button, Field, Input, Notice, join } from '@/components/ui';
import { startHref, type SocialProvider } from '@/lib/social';
import { sendCode, signInWithPassword, verifyCode, verifyTwoFactor, type SignInState } from './actions';

const start: SignInState = { step: 'contact' };

/**
 * The ways in: a one-time code or a password, then Google or Apple below
 * (where the platform has them). Coming back from Google or Apple to an
 * account with two-step sign-in, only the authenticator's step shows.
 */
export function SignInForm({ next, providers = [], twoFactorToken, staff = false }: { next: string; providers?: SocialProvider[]; twoFactorToken?: string; staff?: boolean }) {
  const t = useTranslations('signIn');
  const locale = useLocale();
  const [mode, setMode] = useState<'code' | 'password'>('code');

  if (twoFactorToken) {
    return (
      <div className="rounded-card border border-line bg-white p-5 shadow-sm">
        <TwoFactorStep token={twoFactorToken} next={next} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="rounded-card border border-line bg-white p-5 shadow-sm">
        {staff ? <Staff /> : null}
        <div role="tablist" className="mb-5 grid grid-cols-2 gap-1 rounded-control bg-paper p-1">
          {(['code', 'password'] as const).map((m) => (
            <button
              key={m}
              role="tab"
              type="button"
              aria-selected={mode === m}
              onClick={() => setMode(m)}
              className={join('min-h-10 rounded-control text-sm font-semibold transition-colors', mode === m ? 'bg-white text-brand-strong shadow-sm' : 'text-muted hover:text-ink')}
            >
              {m === 'code' ? t('byCode') : t('byPassword')}
            </button>
          ))}
        </div>
        {mode === 'code' ? <CodeForm next={next} /> : <PasswordForm next={next} />}
      </div>
      {providers.length > 0 ? (
        <div className="space-y-3">
          <p className="flex items-center gap-3 text-sm text-muted before:h-px before:flex-1 before:bg-line after:h-px after:flex-1 after:bg-line">{t('or')}</p>
          {providers.map((p) => (
            <a
              key={p.provider}
              href={startHref(p.provider, locale, { back: next || undefined })}
              data-provider={p.provider}
              className="flex min-h-11 w-full items-center justify-center rounded-control border border-line bg-white px-4 font-semibold text-ink shadow-sm transition-colors hover:bg-paper"
            >
              {t('withProvider', { provider: p.label })}
            </a>
          ))}
        </div>
      ) : null}
    </div>
  );
}

function Staff() {
  const t = useTranslations('signIn');
  return (
    <div className="mt-4 space-y-3">
      <Notice tone="info">{t('staff')}</Notice>
      <a href="/admin" className="block text-center font-semibold text-brand-strong underline underline-offset-4">
        {t('openAdmin')}
      </a>
    </div>
  );
}

function CodeForm({ next }: { next: string }) {
  const t = useTranslations('signIn');
  const [sent, send, sending] = useActionState(sendCode, start);
  const [checked, verify, verifying] = useActionState(verifyCode, start);
  const [editing, setEditing] = useState(false);
  const state = checked.error || checked.staff ? { ...sent, ...checked } : sent;
  const wait = useCountdown(sent.resendAfter, sent.challengeId);

  if (checked.step === 'two-factor' && checked.twoFactorToken && !editing) return <TwoFactorStep token={checked.twoFactorToken} next={next} />;
  if (sent.step === 'contact' || editing) {
    return (
      <form action={(form) => { setEditing(false); send(form); }} className="space-y-4">
        <Field label={t('contact')} error={sent.error}>
          <Input name="contact" defaultValue={sent.contact} autoComplete="username" inputMode="email" required dir="ltr" />
        </Field>
        <Button wide disabled={sending}>{t('sendCode')}</Button>
      </form>
    );
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted">
        {t('sentTo', { to: sent.sentTo ?? '' })}{' '}
        <button type="button" onClick={() => setEditing(true)} className="font-semibold text-brand-strong underline underline-offset-4">
          {t('change')}
        </button>
      </p>
      <form action={verify} className="space-y-4">
        <input type="hidden" name="next" value={next} />
        <ChallengeCarrier state={sent} />
        <Field label={t('code')} error={checked.error}>
          <Input name="code" autoComplete="one-time-code" inputMode="numeric" maxLength={12} required dir="ltr" className="text-center text-xl tracking-[0.5em]" />
        </Field>
        <Button wide disabled={verifying}>{t('enter')}</Button>
      </form>
      <form action={send}>
        <input type="hidden" name="contact" value={sent.contact} />
        <button disabled={wait > 0 || sending} className="w-full text-sm font-semibold text-brand-strong disabled:font-normal disabled:text-muted">
          {wait > 0 ? t('resendIn', { n: wait }) : t('resend')}
        </button>
      </form>
      {state.staff ? <Staff /> : null}
    </div>
  );
}

/** The challenge sendCode just made, for verifyCode (each action keeps its own state). */
function ChallengeCarrier({ state }: { state: SignInState }) {
  return <input type="hidden" name="challenge" value={JSON.stringify({ challengeId: state.challengeId, contact: state.contact })} />;
}

/**
 * The second step for an account with two-step sign-in: the six digits the
 * authenticator app shows now, or one of the recovery codes.
 */
function TwoFactorStep({ token, next }: { token: string; next: string }) {
  const t = useTranslations('signIn');
  const [state, check, checking] = useActionState(verifyTwoFactor, { step: 'two-factor', twoFactorToken: token });
  // A back-office account passed both steps: its place is the back office.
  if (state.staff) return <Staff />;
  if (state.step === 'contact') {
    return (
      <div className="space-y-3">
        <Notice tone="danger">{state.error ?? t('twoFactorExpired')}</Notice>
        <a href="" className="block text-center font-semibold text-brand-strong underline underline-offset-4">{t('startAgain')}</a>
      </div>
    );
  }
  return (
    <form action={check} className="space-y-4">
      <input type="hidden" name="next" value={next} />
      <input type="hidden" name="token" value={state.twoFactorToken ?? token} />
      <div>
        <p className="font-heading font-semibold">{t('twoFactorTitle')}</p>
        <p className="text-sm text-muted">{t('twoFactorHint')}</p>
      </div>
      <Field label={t('twoFactorCode')} error={state.error}>
        <Input name="code" autoComplete="one-time-code" maxLength={40} required dir="ltr" autoFocus className="text-center text-xl tracking-[0.3em]" />
      </Field>
      <Button wide disabled={checking}>{t('enter')}</Button>
    </form>
  );
}

function PasswordForm({ next }: { next: string }) {
  const t = useTranslations('signIn');
  const [state, signIn, pending] = useActionState(signInWithPassword, start);
  if (state.step === 'two-factor' && state.twoFactorToken) return <TwoFactorStep token={state.twoFactorToken} next={next} />;
  return (
    <form action={signIn} className="space-y-4">
      <input type="hidden" name="next" value={next} />
      <Field label={t('login')}>
        <Input name="login" defaultValue={state.contact} autoComplete="username" required dir="ltr" />
      </Field>
      <Field label={t('password')} error={state.error}>
        <Input name="password" type="password" autoComplete="current-password" required dir="ltr" />
      </Field>
      <Button wide disabled={pending}>{t('enter')}</Button>
      {state.staff ? <Staff /> : null}
    </form>
  );
}

/** Seconds left before another code can be asked for, starting again for each new challenge. */
function useCountdown(seconds: number | undefined, key: string | undefined): number {
  const [left, setLeft] = useState(seconds ?? 0);
  const [counting, setCounting] = useState(key);
  if (counting !== key) {
    setCounting(key);
    setLeft(seconds ?? 0);
  }
  useEffect(() => {
    if (left <= 0) return;
    const tick = setTimeout(() => setLeft((s) => Math.max(0, s - 1)), 1000);
    return () => clearTimeout(tick);
  }, [left]);
  return left;
}
