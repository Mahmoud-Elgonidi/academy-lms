'use client';

import { useActionState, type CSSProperties } from 'react';
import { useTranslations } from 'next-intl';
import { askToJoinPlatform, type SiteFormState } from '@/lib/site/actions';

const STUDENTS = ['1-20', '21-50', '51-200', '201+'];

/** A little burst of colour when the request is in (decorative, still without motion). */
export function Confetti() {
  const colours = ['#F2B53A', '#17A487', '#74C0FC', '#FF6B6B', '#B197FC', '#8CE99A'];
  return (
    <div className="ls-confetti" aria-hidden="true">
      {Array.from({ length: 28 }, (_, i) => {
        const angle = (i / 28) * Math.PI * 2;
        const reach = 120 + (i % 5) * 35;
        return (
          <i
            key={i}
            style={{
              background: colours[i % colours.length],
              '--x': `${Math.round(Math.cos(angle) * reach)}px`,
              '--y': `${Math.round(Math.sin(angle) * reach + 60)}px`,
              '--r': `${(i % 2 ? 1 : -1) * (180 + i * 23)}deg`,
              '--d': (i % 7) * 40,
            } as CSSProperties}
          />
        );
      })}
    </div>
  );
}

/** The drawn tick of a form that went through. */
export function Done({ title, text }: { title: string; text: string }) {
  return (
    <div className="ls-done" role="status">
      <Confetti />
      <svg viewBox="0 0 80 80" aria-hidden="true">
        <circle cx="40" cy="40" r="36" />
        <path d="M24 41 L35 52 L57 29" />
      </svg>
      <h3>{title}</h3>
      <p>{text}</p>
    </div>
  );
}

/** The request to join the platform: who the academy is, and how to reach them. */
export function JoinPlatformForm({ token, plans, countries, utm, chosen }: {
  token: string;
  plans: { code: string; name: string }[];
  /** Named on the server, so the page reads the same before and after its script runs. */
  countries: { code: string; name: string }[];
  utm: { source?: string; medium?: string; campaign?: string };
  chosen?: string;
}) {
  const t = useTranslations('platformSite.join');
  const [state, action, pending] = useActionState<SiteFormState, FormData>(askToJoinPlatform, { status: 'idle', errors: {} });

  if (state.status === 'sent') {
    return (
      <div className="ls-form">
        <Done title={t('doneTitle')} text={t('doneText')} />
      </div>
    );
  }

  const err = (field: string) => state.errors[field];
  const field = (name: string, input: React.ReactNode, full = false) => (
    <label className={`ls-field${full ? ' ls-full' : ''}`}>
      <span>{t(`fields.${name}`)}</span>
      {input}
      {err(name) ? <small id={`${name}-error`}>{err(name)}</small> : null}
    </label>
  );
  const invalid = (name: string) => (err(name) ? { 'aria-invalid': true, 'aria-describedby': `${name}-error` } : {});

  return (
    <form action={action} className="ls-form" noValidate>
      {state.status === 'failed' ? <p className="ls-error" role="alert">{state.message ?? (Object.keys(state.errors).length ? t('fix') : t('failed'))}</p> : null}
      <div className="ls-form-grid">
        {field('academy_name', <input name="academy_name" required maxLength={120} autoComplete="organization" {...invalid('academy_name')} />, true)}
        {field('contact_name', <input name="contact_name" required maxLength={120} autoComplete="name" {...invalid('contact_name')} />)}
        {field('email', <input name="email" type="email" required maxLength={190} autoComplete="email" dir="ltr" {...invalid('email')} />)}
        {field('phone', <input name="phone" type="tel" maxLength={20} autoComplete="tel" dir="ltr" placeholder="+20 100 000 0000" {...invalid('phone')} />)}
        {field('country', (
          <select name="country" defaultValue="" {...invalid('country')}>
            <option value="">{t('pickCountry')}</option>
            {countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
          </select>
        ))}
        {field('students', (
          <select name="students" defaultValue="" {...invalid('students')}>
            <option value="">{t('pickStudents')}</option>
            {STUDENTS.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        ))}
        {field('plan', (
          <select name="plan" defaultValue={chosen ?? ''} {...invalid('plan')}>
            <option value="">{t('pickPlan')}</option>
            {plans.map((p) => <option key={p.code} value={p.code}>{p.name}</option>)}
          </select>
        ))}
        {field('message', <textarea name="message" rows={3} maxLength={1000} {...invalid('message')} />, true)}
      </div>
      <input type="hidden" name="form_token" value={token} />
      <input type="hidden" name="utm_source" value={utm.source ?? ''} />
      <input type="hidden" name="utm_medium" value={utm.medium ?? ''} />
      <input type="hidden" name="utm_campaign" value={utm.campaign ?? ''} />
      {/* Left empty by people; a form that fills it is a machine's. */}
      <div className="ls-hp" aria-hidden="true">
        <label>
          Website
          <input name="website" tabIndex={-1} autoComplete="off" defaultValue="" />
        </label>
      </div>
      <div className="ls-form-foot">
        <span>{t('privacy')}</span>
        <button type="submit" className="ls-btn ls-btn-dark" disabled={pending}>
          {pending ? t('sending') : t('send')}
        </button>
      </div>
    </form>
  );
}
