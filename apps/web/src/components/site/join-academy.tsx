'use client';

import { useActionState, useState, useSyncExternalStore } from 'react';
import { useTranslations } from 'next-intl';
import { askTheAcademy, type SiteFormState } from '@/lib/site/actions';
import { Done } from './join-platform';

type Kind = 'trial' | 'teach';
type Option = { id: string; name: string };

// The open tab is the address's: #teach opens "teach with us", and a tab picked writes it there.
const HASH_EVENT = 'ls:hash';
const onHash = (changed: () => void) => {
  window.addEventListener('hashchange', changed);
  window.addEventListener(HASH_EVENT, changed);
  return () => {
    window.removeEventListener('hashchange', changed);
    window.removeEventListener(HASH_EVENT, changed);
  };
};
const noZone = () => () => undefined;
const browserZone = () => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
  } catch {
    return '';
  }
};

/**
 * The academy's two doors on its page: a free trial lesson (families) and
 * "teach with us" (teachers), as two tabs over one form each. A link to
 * #teach opens the second. The visitor's time zone rides along, read in the
 * browser after the page shows (the form reads the same without it).
 */
export function JoinAcademyForm({ token, subjects, countries, utm, teach, chosenSubject }: {
  token: string;
  subjects: Option[];
  countries: { code: string; name: string }[];
  utm: { source?: string; medium?: string; campaign?: string };
  /** Whether the academy shows "teach with us". */
  teach: boolean;
  chosenSubject?: string;
}) {
  const t = useTranslations('site.join');
  const hash = useSyncExternalStore(onHash, () => window.location.hash, () => '');
  const kind: Kind = teach && hash === '#teach' ? 'teach' : 'trial';
  const open = (tab: Kind) => {
    window.history.replaceState(null, '', tab === 'teach' ? '#teach' : '#join');
    window.dispatchEvent(new Event(HASH_EVENT));
  };

  return (
    <div className="ls-form-wrap">
      {teach ? (
        <div className="ls-tabs" role="tablist" aria-label={t('eyebrow')}>
          {(['trial', 'teach'] as const).map((tab) => (
            <button
              key={tab}
              type="button"
              role="tab"
              id={`ls-tab-${tab}`}
              aria-selected={kind === tab}
              aria-controls={`ls-panel-${tab}`}
              onClick={() => open(tab)}
            >
              {t(`tabs.${tab}`)}
            </button>
          ))}
        </div>
      ) : null}
      {(['trial', 'teach'] as const).filter((tab) => tab === 'trial' || teach).map((tab) => (
        <div key={tab} role={teach ? 'tabpanel' : undefined} id={`ls-panel-${tab}`} aria-labelledby={teach ? `ls-tab-${tab}` : undefined} hidden={kind !== tab}>
          <OneForm kind={tab} token={token} subjects={subjects} countries={countries} utm={utm} chosenSubject={chosenSubject} />
        </div>
      ))}
    </div>
  );
}

function OneForm({ kind, token, subjects, countries, utm, chosenSubject }: {
  kind: Kind;
  token: string;
  subjects: Option[];
  countries: { code: string; name: string }[];
  utm: { source?: string; medium?: string; campaign?: string };
  chosenSubject?: string;
}) {
  const t = useTranslations('site.join');
  const [state, action, pending] = useActionState<SiteFormState, FormData>(askTheAcademy, { status: 'idle', errors: {} });
  const [forChild, setForChild] = useState(true);
  // The visitor's own zone, once the page runs in their browser (without it, the academy's stands in).
  const zone = useSyncExternalStore(noZone, browserZone, () => '');

  if (state.status === 'sent') {
    const done = kind === 'trial' ? 'doneTrial' : 'doneTeach';
    return (
      <div className="ls-form">
        <Done title={t(`${done}.title`)} text={t(`${done}.text`)} />
      </div>
    );
  }

  // What was typed comes back with an answer, and the form starts from it again.
  const was = (field: string, fallback = '') => {
    const value = state.values?.[field];
    return typeof value === 'string' ? value : fallback;
  };
  const picked = (field: string) => {
    const value = state.values?.[field];
    return Array.isArray(value) ? value : typeof value === 'string' && value ? [value] : [];
  };
  const err = (field: string) => state.errors[field] ?? (field === 'subject_ids' ? state.errors['subject_ids.0'] : undefined);
  const id = (name: string) => `${kind}-${name}`;
  const invalid = (name: string) => (err(name) ? { 'aria-invalid': true, 'aria-describedby': `${id(name)}-error` } : {});
  const field = (name: string, input: React.ReactNode, full = false) => (
    <label className={`ls-field${full ? ' ls-full' : ''}`}>
      <span>{t(`fields.${name}`)}</span>
      {input}
      {err(name) ? <small id={`${id(name)}-error`}>{err(name)}</small> : null}
    </label>
  );

  return (
    <form action={action} className="ls-form" noValidate>
      {kind === 'teach' ? <p className="ls-form-lead">{t('teachLead')}</p> : null}
      {state.status === 'failed' ? <p className="ls-error" role="alert">{state.message ?? (Object.keys(state.errors).length ? t('fix') : t('failed'))}</p> : null}
      <div className="ls-form-grid">
        {field('name', <input name="name" defaultValue={was('name')} required maxLength={120} autoComplete="name" {...invalid('name')} />)}
        {field('phone', <input name="phone" type="tel" defaultValue={was('phone')} required maxLength={20} autoComplete="tel" dir="ltr" placeholder="+966 50 000 0000" {...invalid('phone')} />)}
        {field('email', <input name="email" type="email" defaultValue={was('email')} maxLength={190} autoComplete="email" dir="ltr" {...invalid('email')} />)}
        {field('country', (
          <select key={`${state.status}-${was('country')}`} name="country" defaultValue={was('country')} {...invalid('country')}>
            <option value="">{t('pickCountry')}</option>
            {countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
          </select>
        ))}
        {kind === 'trial' ? (
          <>
            {subjects.length > 0 ? field('subject_id', (
              <select key={`${state.status}-${was('subject_id', chosenSubject ?? '')}`} name="subject_id" defaultValue={was('subject_id', chosenSubject ?? '')} {...invalid('subject_id')}>
                <option value="">{t('pickSubject')}</option>
                {subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            ), true) : null}
            <label className="ls-check ls-full">
              <input type="checkbox" name="for_child" checked={forChild} onChange={(e) => setForChild(e.target.checked)} />
              <span>{t('fields.for_child')}</span>
            </label>
            {forChild ? (
              <>
                {field('child_name', <input name="child_name" defaultValue={was('child_name')} maxLength={60} autoComplete="off" {...invalid('child_name')} />)}
                {field('child_birth_year', <input name="child_birth_year" defaultValue={was('child_birth_year')} inputMode="numeric" maxLength={4} dir="ltr" placeholder="2015" {...invalid('child_birth_year')} />)}
              </>
            ) : null}
          </>
        ) : (
          <>
            {subjects.length > 0 ? (
              <fieldset className="ls-field ls-full" {...invalid('subject_ids')}>
                <legend>{t('fields.subject_ids')}</legend>
                <div className="ls-chips">
                  {subjects.map((s) => (
                    <label key={s.id} className="ls-chip">
                      <input type="checkbox" name="subject_ids" value={s.id} defaultChecked={picked('subject_ids').includes(s.id)} />
                      <span>{s.name}</span>
                    </label>
                  ))}
                </div>
                {err('subject_ids') ? <small id={`${id('subject_ids')}-error`}>{err('subject_ids')}</small> : null}
              </fieldset>
            ) : null}
            {field('years_teaching', <input name="years_teaching" defaultValue={was('years_teaching')} inputMode="numeric" maxLength={2} dir="ltr" {...invalid('years_teaching')} />)}
          </>
        )}
        {field('message', <textarea name="message" rows={3} defaultValue={was('message')} maxLength={1000} {...invalid('message')} />, true)}
      </div>
      <input type="hidden" name="kind" value={kind} />
      <input type="hidden" name="form_token" value={token} />
      <input type="hidden" name="timezone" value={zone} />
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
