'use client';

import { useEffect, useRef, useState, useSyncExternalStore, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { weekOrder, weekdayName } from '@/lib/format';
import {
  askForTrial,
  bookTrial,
  sendTrialCode,
  trialTimes,
  type BookedTrial,
  type CodeSent,
} from '@/lib/site/actions';
import {
  bookingSubject,
  byDay,
  checkStep,
  CHILD_AGES,
  cleanCode,
  EMPTY_DRAFT,
  nextFrom,
  normalizePhone,
  secondsUntil,
  stepsFor,
  waitLabel,
  whatsappLink,
  type StepKey,
  type TeacherGender,
  type TrialDraft,
  type TrialSettings,
} from '@/lib/site/trial';
import { formatClock, type LocalTime, type TimeFormat } from '@/lib/time';
import { Icon, WhatsAppIcon } from '../icons';
import { BigTime, BookedCard, FocusHeading } from './booked';
import { TimePicker, type TimesState } from './time-picker';
import './trial.css';

type Option = { id: string; name: string };

const noZone = () => () => undefined;
const browserZone = () => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
  } catch {
    return '';
  }
};

/** The visitor's own zone, once the page runs in their browser (the academy's stands in before). */
export function useBrowserZone(): string {
  return useSyncExternalStore(noZone, browserZone, () => '');
}

/** A new key for a write that must happen once however often it is sent. */
export function newKey(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
}

/** A clock that ticks each second while something counts down (a code's wait), for the words only. */
function useNow(running: boolean): number {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    if (!running) return;
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, [running]);
  return now;
}

type Code = CodeSent & { phone: string; resendAt: number; endsAt: number; wrong: number; value: string; notice?: 'kept' | 'new' };
type Done = { kind: 'booked'; data: BookedTrial; time: LocalTime } | { kind: 'sent' } | { kind: 'refused'; message: string };

const API_FIELDS: Record<string, [StepKey, keyof TrialDraft]> = {
  name: ['who', 'name'],
  child_name: ['who', 'childName'],
  child_birth_year: ['who', 'childAge'],
  subject_id: ['who', 'subjectId'],
  for_child: ['who', 'who'],
  preferred_days: ['when', 'days'],
  'preferred_days.0': ['when', 'days'],
  preferred_from: ['when', 'from'],
  preferred_to: ['when', 'to'],
  teacher_gender: ['when', 'teacherGender'],
  phone: ['contact', 'phone'],
  email: ['contact', 'email'],
  country: ['contact', 'country'],
  message: ['contact', 'message'],
};

/**
 * The free trial request on the academy's page, as a calm stepper: who is
 * learning and what (or "help me choose"), when suits them and the teacher
 * they would rather have, then, while the academy lets families book, a time
 * on their own clock and their number with the code sent to it, and the
 * booked lesson; otherwise (or when they would rather be called) their
 * number, and the promise to answer within the academy's hours. What was
 * typed stays whatever fails; Back goes back; each step's heading takes the
 * focus; refusals are said in the API's words, near what they are about.
 */
export function TrialRequest({ token, subjects, countries, utm, chosenSubject, settings, whatsapp, academy, timeFormat }: {
  token: string;
  subjects: Option[];
  countries: { code: string; name: string }[];
  utm: { source?: string; medium?: string; campaign?: string };
  chosenSubject?: string;
  settings: TrialSettings;
  whatsapp: string | null;
  academy: string;
  timeFormat: TimeFormat;
}) {
  const t = useTranslations('site.trial');
  const locale = useLocale();
  const zone = useBrowserZone();
  const [draft, setDraft] = useState<TrialDraft>({ ...EMPTY_DRAFT, subjectId: chosenSubject ?? '' });
  const [leaveToUs, setLeaveToUs] = useState(false);
  const [at, setAt] = useState<StepKey>('who');
  const [moved, setMoved] = useState(false);
  const [wrong, setWrong] = useState<Record<string, string>>({});
  const [alert, setAlert] = useState<string | null>(null);
  const [times, setTimes] = useState<TimesState>({ status: 'loading' });
  const [loadedFor, setLoadedFor] = useState('');
  const [chosen, setChosen] = useState<LocalTime | null>(null);
  const [code, setCode] = useState<Code | null>(null);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<Done | null>(null);
  // After a code was used on a time that went: the next code step says a new one is needed.
  const [codeNotice, setCodeNotice] = useState<'new' | null>(null);
  const now = useNow(code !== null && at === 'contact');

  const steps = stepsFor(settings, draft, leaveToUs);
  const index = Math.max(0, steps.indexOf(at));
  const booking = steps.includes('time');
  const subject = bookingSubject(settings, draft);
  const subjectName = (id: string | null) => subjects.find((s) => s.id === id)?.name ?? '';
  const set = <K extends keyof TrialDraft>(field: K, value: TrialDraft[K]) => {
    setDraft((d) => ({ ...d, [field]: value }));
    setWrong((w) => {
      const { [field]: _gone, ...rest } = w;
      return rest;
    });
  };

  const go = (step: StepKey) => {
    setMoved(true);
    setAlert(null);
    setWrong({});
    setAt(step);
  };

  const searchKey = (from: string | null) => [subject, draft.who === 'child' ? draft.childAge : '', draft.teacherGender, from ?? '', zone].join('|');

  const loadTimes = async (from: string | null = null) => {
    if (!subject) return;
    setTimes({ status: 'loading' });
    setLoadedFor(searchKey(from));
    const found = await trialTimes({
      subjectId: subject,
      childAge: draft.who === 'child' ? draft.childAge : undefined,
      teacherGender: draft.teacherGender,
      from,
      timezone: zone,
    });
    setTimes(found.ok ? { status: 'ready', times: found.data, from } : { status: 'failed' });
    return found.ok ? found.data : null;
  };

  const next = () => {
    const missing = checkStep(at, draft);
    if (Object.keys(missing).length > 0) {
      setWrong(Object.fromEntries(Object.entries(missing).map(([field, key]) => [field, t(`errors.${key}`)])));
      setAlert(t('errors.fix'));
      return;
    }
    const following = steps[index + 1];
    if (!following) return;
    if (following === 'time') {
      const from = times.status === 'ready' ? times.from : null;
      if (loadedFor !== searchKey(from) || times.status !== 'ready') {
        setChosen(null);
        void loadTimes(null);
      }
    }
    go(following);
  };

  const back = () => {
    const previous = steps[index - 1];
    if (previous) go(previous);
  };

  /** What the API said about fields, put next to them, on the first step that has one. */
  const showFieldErrors = (errors: Record<string, string>, fallback: string) => {
    const mapped: Record<string, string> = {};
    let first: StepKey | null = null;
    for (const [field, message] of Object.entries(errors)) {
      const place = API_FIELDS[field];
      if (!place) continue;
      mapped[place[1]] = message;
      if (first === null || steps.indexOf(place[0]) < steps.indexOf(first)) first = place[0];
    }
    if (first) {
      setMoved(true);
      setAt(first);
    }
    setWrong(mapped);
    setAlert(Object.keys(mapped).length ? t('errors.fix') : fallback);
  };

  const askCode = async (channel?: 'whatsapp' | 'sms') => {
    const missing = checkStep('contact', draft);
    if (Object.keys(missing).length > 0) {
      setWrong(Object.fromEntries(Object.entries(missing).map(([field, key]) => [field, t(`errors.${key}`)])));
      setAlert(t('errors.fix'));
      return;
    }
    setBusy(true);
    setAlert(null);
    const sent = await sendTrialCode(draft.phone, channel);
    setBusy(false);
    if (!sent.ok) {
      const { refusal } = sent;
      if (refusal.errors.phone) setWrong({ phone: refusal.errors.phone });
      else setAlert(refusal.status === 429 ? (refusal.message || t('errors.limited')) : (refusal.message || t('errors.failed')));
      return;
    }
    const sentAt = Date.now();
    setCodeNotice(null);
    setCode({
      ...sent.data,
      phone: normalizePhone(draft.phone),
      resendAt: sentAt + sent.data.resend_after * 1000,
      endsAt: sent.data.expires_at ? Date.parse(sent.data.expires_at.utc) : sentAt + 600_000,
      wrong: 0,
      value: '',
    });
  };

  const sendRequest = async () => {
    const missing = checkStep('contact', draft);
    if (Object.keys(missing).length > 0) {
      setWrong(Object.fromEntries(Object.entries(missing).map(([field, key]) => [field, t(`errors.${key}`)])));
      setAlert(t('errors.fix'));
      return;
    }
    setBusy(true);
    setAlert(null);
    const sent = await askForTrial({ draft, token, timezone: zone, utm });
    setBusy(false);
    if (sent.ok) {
      setMoved(true);
      setDone({ kind: 'sent' });
      return;
    }
    if (sent.refusal.status === 429) return setAlert(t('errors.limited'));
    if (sent.refusal.status === 422) return showFieldErrors(sent.refusal.errors, sent.refusal.message || t('errors.failed'));
    setAlert(t('errors.failed'));
  };

  const confirm = async () => {
    if (!code || !chosen || !subject) return;
    const typed = cleanCode(code.value);
    if (typed.length !== 6) {
      setWrong({ code: t('errors.code') });
      return;
    }
    setBusy(true);
    setAlert(null);
    const result = await bookTrial({ draft, subjectId: subject, time: chosen, challengeId: code.challenge_id, code: typed, timezone: zone, utm, key: newKey() });
    setBusy(false);
    if (result.ok) {
      setMoved(true);
      setDone({ kind: 'booked', data: result.data, time: result.data.starts_at ?? chosen });
      return;
    }
    const { refusal } = result;
    const spent = refusal.codeSpent;
    switch (refusal.kind) {
      case 'taken':
        setCode(spent ? null : { ...code, notice: 'kept' });
        setChosen(null);
        setMoved(true);
        setWrong({});
        setAt('time');
        setAlert(refusal.message || t('time.taken'));
        void loadTimes(times.status === 'ready' ? times.from : null);
        if (spent) setCodeNotice('new');
        return;
      case 'code':
        setCode({ ...code, wrong: code.wrong + 1, value: '' });
        setWrong({ code: refusal.message || t('errors.code') });
        return;
      case 'fields':
        setCode(null);
        showFieldErrors(refusal.errors, refusal.message || t('errors.failed'));
        return;
      case 'refused':
        setCode(null);
        setMoved(true);
        setDone({ kind: 'refused', message: refusal.message });
        return;
      case 'limited':
        setAlert(refusal.message || t('errors.limited'));
        return;
      default:
        setAlert(t('errors.failed'));
    }
  };

  const startAgain = (keepLearner: boolean) => {
    setDraft((d) => ({ ...d, childName: keepLearner ? d.childName : '', childAge: keepLearner ? d.childAge : '' }));
    setChosen(null);
    setCode(null);
    setCodeNotice(null);
    setDone(null);
    setLeaveToUs(false);
    setLoadedFor('');
    go('who');
  };

  if (done) {
    return (
      <div className="ls-form lt-form" data-done={done.kind}>
        {done.kind === 'booked' ? (
          <BookedCard
            time={done.time}
            subject={done.data.subject || subjectName(subject)}
            teacher={done.data.teacher || undefined}
            minutes={done.data.minutes}
            academy={academy}
            timeFormat={timeFormat}
            title={draft.who === 'child' ? t('booked.titleChild', { name: draft.childName.trim() }) : t('booked.title')}
            next={[t('booked.next.message'), t('booked.next.reminder'), t('booked.next.change'), t('booked.next.after')]}
            actions={(
              <>
                {draft.who === 'child' ? (
                  <button type="button" className="ls-btn lt-btn-soft ls-btn-small" onClick={() => startAgain(false)}>
                    <Icon name="child" />{t('booked.another')}
                  </button>
                ) : (
                  <button type="button" className="ls-btn lt-btn-soft ls-btn-small" onClick={() => startAgain(true)}>
                    <Icon name="book" />{t('booked.anotherSubject')}
                  </button>
                )}
              </>
            )}
          />
        ) : done.kind === 'sent' ? (
          <SentCard settings={settings} picked={booking} onAgain={() => startAgain(false)} />
        ) : (
          <RefusedCard
            message={done.message}
            whatsapp={whatsappLink(whatsapp, t('whatsappWords'))}
            onEdit={() => startAgain(true)}
            onRequest={() => {
              setDone(null);
              setLeaveToUs(true);
              setChosen(null);
              go('contact');
            }}
          />
        )}
      </div>
    );
  }

  const heading = (key: string, lead?: ReactNode) => (
    <header className="lt-step-head">
      <StepHeading focus={moved}>{t(key)}</StepHeading>
      {lead ? <p className="lt-muted">{lead}</p> : null}
    </header>
  );
  const err = (field: string) => wrong[field];
  const described = (field: string) => (err(field) ? { 'aria-invalid': true as const, 'aria-describedby': `lt-${field}-error` } : {});
  const fieldError = (field: string) => (err(field) ? <small id={`lt-${field}-error`} className="lt-error">{err(field)}</small> : null);

  return (
    <form
      className="ls-form lt-form"
      method="post"
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        if (at === 'contact') {
          if (!booking) void sendRequest();
          else if (code) void confirm();
          else void askCode();
        } else next();
      }}
    >
      <Progress steps={steps} index={index} />
      <p className="lt-alert" role="alert" aria-live="assertive">{alert ?? ''}</p>

      {at === 'who' ? (
        <section className="lt-step" aria-labelledby="lt-step-title">
          {heading('who.title', t('who.lead'))}
          <fieldset className="lt-fieldset">
            <legend className="ls-sr">{t('who.title')}</legend>
            <div className="lt-who">
              {(['child', 'me'] as const).map((who) => (
                <label key={who} className="lt-who-card">
                  <input type="radio" name="who" value={who} checked={draft.who === who} onChange={() => set('who', who)} />
                  <span>
                    <Icon name={who === 'child' ? 'child' : 'user'} />
                    <b>{t(`who.${who}`)}</b>
                    <small>{t(`who.${who}Hint`)}</small>
                  </span>
                </label>
              ))}
            </div>
          </fieldset>
          <div className="ls-form-grid">
            <label className="ls-field">
              <span>{draft.who === 'child' ? t('who.parentName') : t('who.name')}</span>
              <input value={draft.name} onChange={(e) => set('name', e.target.value)} maxLength={120} autoComplete="name" {...described('name')} />
              {fieldError('name')}
            </label>
            {draft.who === 'child' ? (
              <>
                <label className="ls-field">
                  <span>{t('who.childName')}</span>
                  <input value={draft.childName} onChange={(e) => set('childName', e.target.value)} maxLength={60} autoComplete="off" {...described('childName')} />
                  {fieldError('childName')}
                </label>
                <label className="ls-field">
                  <span>{t('who.childAge')}</span>
                  <select value={draft.childAge} onChange={(e) => set('childAge', e.target.value)} {...described('childAge')}>
                    <option value="">{t('who.agePick')}</option>
                    {CHILD_AGES.map((age) => <option key={age} value={age}>{t('who.years', { count: age })}</option>)}
                  </select>
                  {fieldError('childAge')}
                </label>
              </>
            ) : null}
          </div>
          <fieldset className="ls-field ls-full lt-fieldset" {...(err('subjectId') ? { 'aria-describedby': 'lt-subjectId-error' } : {})}>
            <legend>{t('who.subject')}</legend>
            <div className="ls-chips lt-subjects">
              {subjects.map((s) => (
                <label key={s.id} className="ls-chip">
                  <input type="radio" name="subject" value={s.id} checked={!draft.helpChoose && draft.subjectId === s.id} onChange={() => { set('subjectId', s.id); set('helpChoose', false); }} />
                  <span>{s.name}</span>
                </label>
              ))}
              <label className="ls-chip lt-chip-help">
                <input type="radio" name="subject" value="help" checked={draft.helpChoose} onChange={() => { set('helpChoose', true); set('subjectId', ''); }} />
                <span><Icon name="sparkle" />{t('who.helpChoose')}</span>
              </label>
            </div>
            {fieldError('subjectId')}
            {draft.helpChoose ? (
              <p className="lt-hint" role="note">
                {settings.help_choose_subject && settings.help_choose_subject_id && subjectName(settings.help_choose_subject_id)
                  ? t('who.helpChooseStart', { subject: subjectName(settings.help_choose_subject_id) })
                  : t('who.helpChooseTeam', { hours: settings.response_hours })}
              </p>
            ) : null}
          </fieldset>
        </section>
      ) : null}

      {at === 'when' ? (
        <WhenStep draft={draft} set={set} heading={heading} wrong={wrong} timeFormat={timeFormat} locale={locale} zone={zone} />
      ) : null}

      {at === 'time' ? (
        <section className="lt-step" aria-labelledby="lt-step-title">
          {heading('time.title', t('time.lead'))}
          <TimePicker
            name="trial-time"
            state={times}
            chosen={chosen}
            onChoose={(time) => {
              setChosen(time);
              setAlert(null);
            }}
            onRetry={() => void loadTimes(times.status === 'ready' ? times.from : null)}
            onLater={() => void loadTimes(times.status === 'ready' ? nextFrom(byDay(times.times, draft), times.from) : null)}
            onSoonest={times.status === 'ready' && times.from ? () => void loadTimes(null) : null}
            prefs={draft}
            timeFormat={timeFormat}
            zone={zone}
            empty={(
              <>
                <p className="lt-muted">{t('time.emptyText', { hours: settings.response_hours })}</p>
                <button type="button" className="ls-btn ls-btn-dark ls-btn-small" onClick={() => { setLeaveToUs(true); go('contact'); }}>
                  {t('time.sendInstead')}
                </button>
              </>
            )}
          />
          {times.status === 'ready' && times.times.length > 0 ? (
            <button type="button" className="lt-link lt-skip" onClick={() => { setLeaveToUs(true); setChosen(null); go('contact'); }}>
              {t('time.skip', { hours: settings.response_hours })}
            </button>
          ) : null}
        </section>
      ) : null}

      {at === 'contact' ? (
        <section className="lt-step" aria-labelledby="lt-step-title">
          {booking
            ? heading('contact.title', t('contact.lead'))
            : heading('contact.titleRequest', t('contact.leadRequest', { hours: settings.response_hours }))}
          {booking && chosen ? (
            <div className="lt-summary">
              <BigTime time={chosen} timeFormat={timeFormat} />
              <p>{subjectName(subject)}{draft.who === 'child' && draft.childName ? ` · ${draft.childName}` : ''}</p>
              <button type="button" className="lt-link" onClick={() => go('time')}><Icon name="pencil" />{t('contact.changeTime')}</button>
            </div>
          ) : null}
          {booking && code ? (
            <CodeStep
              code={code}
              now={now}
              busy={busy}
              error={err('code')}
              notice={code.notice}
              onCode={(value) => {
                setCode({ ...code, value: cleanCode(value) });
                setWrong({});
              }}
              onResend={(channel) => void askCode(channel)}
              onChangeNumber={() => {
                setCode(null);
                setWrong({});
                setAlert(null);
              }}
            />
          ) : (
            <>
              {booking && codeNotice === 'new' ? <p className="lt-hint" role="note">{t('contact.codeNew')}</p> : null}
              <div className="ls-form-grid">
                <label className="ls-field ls-full">
                  <span>{t('contact.phone')}</span>
                  <input
                    type="tel"
                    value={draft.phone}
                    onChange={(e) => set('phone', e.target.value)}
                    maxLength={22}
                    autoComplete="tel"
                    inputMode="tel"
                    dir="ltr"
                    placeholder="+966 50 123 4567"
                    aria-describedby={err('phone') ? 'lt-phone-error' : 'lt-phone-hint'}
                    aria-invalid={err('phone') ? true : undefined}
                  />
                  {err('phone') ? fieldError('phone') : <small id="lt-phone-hint" className="lt-field-hint">{t('contact.phoneHint')}</small>}
                </label>
              </div>
              <details className="lt-more" open={Boolean(err('email') || err('country') || draft.email || draft.country || !booking)}>
                <summary>{booking ? t('contact.more') : t('contact.moreRequest')}</summary>
                <div className="ls-form-grid">
                  <label className="ls-field">
                    <span>{t('contact.email')}</span>
                    <input type="email" value={draft.email} onChange={(e) => set('email', e.target.value)} maxLength={190} autoComplete="email" dir="ltr" {...described('email')} />
                    {fieldError('email')}
                  </label>
                  <label className="ls-field">
                    <span>{t('contact.country')}</span>
                    <select value={draft.country} onChange={(e) => set('country', e.target.value)} {...described('country')}>
                      <option value="">{t('contact.pickCountry')}</option>
                      {countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
                    </select>
                    {fieldError('country')}
                  </label>
                  {!booking ? (
                    <label className="ls-field ls-full">
                      <span>{t('contact.message')}</span>
                      <textarea rows={3} value={draft.message} onChange={(e) => set('message', e.target.value)} maxLength={1000} {...described('message')} />
                      {fieldError('message')}
                    </label>
                  ) : null}
                </div>
              </details>
            </>
          )}
        </section>
      ) : null}

      <div className="lt-nav">
        {index > 0 ? (
          <button type="button" className="lt-back" onClick={back} disabled={busy}>
            <Icon name="chevron" className="ls-flip" />{t('back')}
          </button>
        ) : <span className="lt-privacy">{t('privacy')}</span>}
        <SubmitButton at={at} booking={booking} code={code} chosen={chosen} busy={busy} times={times} now={now} />
      </div>
    </form>
  );
}

function SubmitButton({ at, booking, code, chosen, busy, times, now }: { at: StepKey; booking: boolean; code: Code | null; chosen: LocalTime | null; busy: boolean; times: TimesState; now: number }) {
  const t = useTranslations('site.trial');
  if (at === 'time') {
    const ready = times.status === 'ready' && times.times.length > 0;
    if (!ready) return null;
    return (
      <button type="submit" className="ls-btn ls-btn-main" disabled={!chosen} aria-disabled={!chosen}>
        {chosen ? t('time.continue') : t('time.pickFirst')}
        <Icon name="chevron" className="ls-flip" />
      </button>
    );
  }
  if (at !== 'contact') {
    return <button type="submit" className="ls-btn ls-btn-dark">{t('next')}<Icon name="chevron" className="ls-flip" /></button>;
  }
  if (!booking) return <button type="submit" className="ls-btn ls-btn-main" disabled={busy}>{busy ? t('contact.sending') : t('contact.sendRequest')}</button>;
  if (!code) return <button type="submit" className="ls-btn ls-btn-main" disabled={busy}><WhatsAppIcon />{busy ? t('contact.sending') : t('contact.sendCode')}</button>;
  const dead = code.wrong >= 5 || now >= code.endsAt;
  return <button type="submit" className="ls-btn ls-btn-main" disabled={busy || dead}>{busy ? t('contact.confirming') : t('contact.confirm')}</button>;
}

/** A step's heading: takes the focus once the family has moved between steps (never on the page's first paint). */
function StepHeading({ children, focus }: { children: ReactNode; focus: boolean }) {
  const ref = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    if (!focus || !ref.current) return;
    // The form's top (progress and any refusal above the heading) comes into view under the site's bar, then the heading takes the focus.
    ref.current.closest('.lt-form')?.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    ref.current.focus({ preventScroll: true });
  }, [focus]);
  return <h3 id="lt-step-title" ref={ref} tabIndex={-1}>{children}</h3>;
}

/** Where the family is: each step's name, the one they are on marked, and "step 2 of 4" for a screen reader. */
function Progress({ steps, index }: { steps: StepKey[]; index: number }) {
  const t = useTranslations('site.trial');
  return (
    <nav className="lt-progress" aria-label={t('progress', { at: index + 1, count: steps.length })}>
      <ol>
        {steps.map((step, i) => (
          <li key={step} data-state={i < index ? 'done' : i === index ? 'now' : 'later'} aria-current={i === index ? 'step' : undefined}>
            <span className="lt-progress-dot" aria-hidden="true">{i < index ? <Icon name="check" /> : i + 1}</span>
            <span className="lt-progress-name">{t(`steps.${step}`)}</span>
          </li>
        ))}
      </ol>
      <p className="lt-progress-count" aria-hidden="true">{t('progress', { at: index + 1, count: steps.length })}</p>
    </nav>
  );
}

const PRESETS: [string, number, number][] = [['morning', 8, 12], ['afternoon', 12, 17], ['evening', 17, 22]];

function WhenStep({ draft, set, heading, wrong, timeFormat, locale, zone }: {
  draft: TrialDraft;
  set: <K extends keyof TrialDraft>(field: K, value: TrialDraft[K]) => void;
  heading: (key: string, lead?: ReactNode) => ReactNode;
  wrong: Record<string, string>;
  timeFormat: TimeFormat;
  locale: string;
  zone: string;
}) {
  const t = useTranslations('site.trial.when');
  const hour = (h: number) => formatClock(`${String(h).padStart(2, '0')}:00`, timeFormat, locale);
  const toggleDay = (day: number) => set('days', draft.days.includes(day) ? draft.days.filter((d) => d !== day) : [...draft.days, day].sort());
  return (
    <section className="lt-step" aria-labelledby="lt-step-title">
      {heading('when.title', t('lead'))}
      <fieldset className="ls-field lt-fieldset">
        <legend>{t('days')}</legend>
        <div className="ls-chips lt-weekdays">
          {weekOrder(locale).map((day) => (
            <label key={day} className="ls-chip">
              <input type="checkbox" checked={draft.days.includes(day)} onChange={() => toggleDay(day)} />
              <span>{weekdayName(day, locale)}</span>
            </label>
          ))}
        </div>
        <small className="lt-field-hint">{draft.days.length === 0 ? t('anyDay') : t('daysChosen', { count: draft.days.length })}</small>
      </fieldset>
      <fieldset className="ls-field lt-fieldset">
        <legend>{t('hours')}</legend>
        <div className="ls-chips">
          {PRESETS.map(([name, from, to]) => {
            const on = draft.from === String(from) && draft.to === String(to);
            return (
              <button
                key={name}
                type="button"
                className="lt-preset"
                aria-pressed={on}
                onClick={() => {
                  set('from', on ? '' : String(from));
                  set('to', on ? '' : String(to));
                }}
              >
                {t(`presets.${name}`)}
                <small dir="auto">{hour(from)} – {hour(to)}</small>
              </button>
            );
          })}
        </div>
        <div className="lt-hours">
          <label className="ls-field">
            <span>{t('from')}</span>
            <select value={draft.from} onChange={(e) => set('from', e.target.value)}>
              <option value="">{t('anyTime')}</option>
              {Array.from({ length: 24 }, (_, h) => <option key={h} value={h}>{hour(h)}</option>)}
            </select>
          </label>
          <label className="ls-field">
            <span>{t('to')}</span>
            <select value={draft.to} onChange={(e) => set('to', e.target.value)} aria-invalid={wrong.to ? true : undefined} aria-describedby={wrong.to ? 'lt-to-error' : undefined}>
              <option value="">{t('anyTime')}</option>
              {Array.from({ length: 24 }, (_, h) => h + 1).map((h) => <option key={h} value={h}>{hour(h % 24)}</option>)}
            </select>
            {wrong.to ? <small id="lt-to-error" className="lt-error">{wrong.to}</small> : null}
          </label>
        </div>
        {zone ? <small className="lt-field-hint">{t('onYourClock')}</small> : null}
      </fieldset>
      <fieldset className="ls-field lt-fieldset">
        <legend>{t('gender')}</legend>
        <div className="ls-chips">
          {(['', 'female', 'male'] as TeacherGender[]).map((g) => (
            <label key={g || 'any'} className="ls-chip">
              <input type="radio" name="teacher_gender" checked={draft.teacherGender === g} onChange={() => set('teacherGender', g)} />
              <span>{t(`genders.${g || 'any'}`)}</span>
            </label>
          ))}
        </div>
      </fieldset>
    </section>
  );
}

function CodeStep({ code, now, busy, error, notice, onCode, onResend, onChangeNumber }: {
  code: Code;
  now: number;
  busy: boolean;
  error?: string;
  notice?: 'kept' | 'new';
  onCode: (value: string) => void;
  onResend: (channel?: 'whatsapp' | 'sms') => void;
  onChangeNumber: () => void;
}) {
  const t = useTranslations('site.trial.contact');
  const wait = secondsUntil(code.resendAt, now);
  const ended = secondsUntil(code.endsAt, now) === 0;
  const locked = code.wrong >= 5;
  const dead = ended || locked;
  const ref = useRef<HTMLInputElement>(null);
  useEffect(() => ref.current?.focus(), [code.challenge_id]);
  return (
    <div className="lt-code">
      <p className="lt-code-sent" role="status">
        <WhatsAppIcon />
        <span>{t('codeSent', { to: code.sent_to, channel: t(`channels.${code.channel === 'sms' ? 'sms' : code.channel === 'email' ? 'email' : 'whatsapp'}`) })}</span>
      </p>
      {notice === 'kept' && !dead && code.wrong === 0 ? <p className="lt-hint" role="note">{t('codeKept')}</p> : null}
      <label className="ls-field lt-code-field">
        <span>{t('codeLabel')}</span>
        <input
          ref={ref}
          value={code.value}
          onChange={(e) => onCode(e.target.value)}
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="[0-9]*"
          maxLength={6}
          dir="ltr"
          disabled={dead || busy}
          aria-invalid={error ? true : undefined}
          aria-describedby={error || dead ? 'lt-code-error' : undefined}
          placeholder="••••••"
        />
        {dead ? (
          <small id="lt-code-error" className="lt-error">{ended ? t('codeEnded') : t('codeLocked')}</small>
        ) : error ? (
          <small id="lt-code-error" className="lt-error">{error}</small>
        ) : null}
      </label>
      <div className="lt-code-actions">
        {wait > 0 ? (
          <span className="lt-wait" aria-live="off">{t('resendIn', { wait: waitLabel(wait) })}</span>
        ) : (
          <>
            <button type="button" className="lt-link" onClick={() => onResend()} disabled={busy} data-strong={dead ? '' : undefined}>
              <Icon name="refresh" />{t('resend')}
            </button>
            {code.channel !== 'sms' ? (
              <button type="button" className="lt-link" onClick={() => onResend('sms')} disabled={busy}>{t('bySms')}</button>
            ) : null}
          </>
        )}
        <button type="button" className="lt-link" onClick={onChangeNumber} disabled={busy}><Icon name="pencil" />{t('changeNumber')}</button>
      </div>
    </div>
  );
}

/** A request sent: the promise to answer, and what happens until then. */
function SentCard({ settings, picked, onAgain }: { settings: TrialSettings; picked: boolean; onAgain: () => void }) {
  const t = useTranslations('site.trial.sent');
  const family = settings.booking === 'family' && !picked;
  return (
    <div className="lt-booked lt-sent">
      <span className="lt-booked-tick" aria-hidden="true"><Icon name="check" /></span>
      <FocusHeading>{t('title')}</FocusHeading>
      <p className="lt-promise"><Icon name="clock" />{t('promise', { hours: settings.response_hours })}</p>
      <div className="lt-next">
        <h4>{t('nextTitle')}</h4>
        <ol>
          <li>{t('next.message')}</li>
          {family ? <li>{t('next.pick')}</li> : null}
          <li>{t('next.call', { hours: settings.response_hours })}</li>
          <li>{t('next.lesson')}</li>
        </ol>
      </div>
      <div className="lt-actions lt-actions-end">
        <button type="button" className="ls-btn lt-btn-soft ls-btn-small" onClick={onAgain}>{t('again')}</button>
      </div>
    </div>
  );
}

/** The academy books this one itself: the API's sentence, and the ways on. */
function RefusedCard({ message, whatsapp, onEdit, onRequest }: { message: string; whatsapp: string | null; onEdit: () => void; onRequest: () => void }) {
  const t = useTranslations('site.trial.refused');
  return (
    <div className="lt-calm" data-tone="attention">
      <span className="lt-calm-icon" aria-hidden="true"><Icon name="chat" /></span>
      <FocusHeading>{t('title')}</FocusHeading>
      <p>{message || t('text')}</p>
      <div className="lt-actions">
        {whatsapp ? <a className="ls-btn ls-btn-dark ls-btn-small" href={whatsapp} target="_blank" rel="noopener"><WhatsAppIcon />{t('whatsapp')}</a> : null}
        <button type="button" className="ls-btn lt-btn-soft ls-btn-small" onClick={onRequest}>{t('request')}</button>
        <button type="button" className="lt-link" onClick={onEdit}><Icon name="pencil" />{t('edit')}</button>
      </div>
    </div>
  );
}
