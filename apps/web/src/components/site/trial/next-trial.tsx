'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { bookNextTrial, nextTrialTimes, type BookedTrial, type NextTrialSource } from '@/lib/site/actions';
import { byDay, nextFrom, stillOffered, whatsappLink } from '@/lib/site/trial';
import type { LocalTime, TimeFormat } from '@/lib/time';
import { Icon, WhatsAppIcon } from '../icons';
import { BookedCard, FocusHeading } from './booked';
import { TimePicker, type TimesState } from './time-picker';
import { newKey, useBrowserZone } from './trial-request';

type Props = {
  source: NextTrialSource;
  /** second: with another teacher, after a report; rebook: a new time after a trial that did not take place. */
  kind: 'second' | 'rebook';
  learner: string;
  subject: string;
  academy: string;
  whatsapp: string | null;
  timeFormat: TimeFormat;
  /** The family's clock when this browser's is not known yet (the API's). */
  zone?: string | null;
  /** The second trial's last day to book, as the API wrote it. */
  until?: string | null;
  /** Where to go once it is booked (the family's list), if anywhere. */
  done?: { href: string; label: string };
};

type Step = { at: 'closed' } | { at: 'picking' } | { at: 'booked'; trial: BookedTrial & { starts_at: LocalTime } } | { at: 'refused'; text: string } | { at: 'gone'; text: string };

/**
 * One more free trial from an earlier one: the second trial with another
 * teacher (a card that opens into the times), or a new time after a trial
 * that did not take place (the times at once). The times are on the family's
 * clock as the API wrote them; booking needs no code (the link went to their
 * number, or they are signed in), and every refusal says what to do next.
 */
export function NextTrialPanel({ source, kind, learner, subject, academy, whatsapp, timeFormat, zone: fallback, until, done }: Props) {
  const t = useTranslations('site.report');
  const tt = useTranslations('site.trial');
  const browser = useBrowserZone();
  const zone = browser || fallback || '';
  const [step, setStep] = useState<Step>(kind === 'rebook' ? { at: 'picking' } : { at: 'closed' });
  const [times, setTimes] = useState<TimesState>({ status: 'loading' });
  const [chosen, setChosen] = useState<LocalTime | null>(null);
  const [alert, setAlert] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const picking = step.at === 'picking';

  const show = (found: Awaited<ReturnType<typeof nextTrialTimes>>, from: string | null): LocalTime[] | null => {
    if (!found.ok) {
      const { refusal } = found;
      if (refusal.status === 404 || refusal.status === 410) setStep({ at: 'gone', text: refusal.message });
      else if (refusal.status === 422 || refusal.status === 403) setStep({ at: 'refused', text: refusal.message });
      setTimes({ status: 'failed' });
      return null;
    }
    setTimes({ status: 'ready', times: found.data, from });
    return found.data;
  };
  const load = async (from: string | null) => {
    setTimes({ status: 'loading' });
    return show(await nextTrialTimes(source, from, zone), from);
  };

  useEffect(() => {
    if (!picking) return;
    // The times on this browser's clock, once the picker is open and the clock known.
    void (async () => show(await nextTrialTimes(source, null, zone), null))();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- once per clock and opening
  }, [picking, zone]);

  const book = async () => {
    if (!chosen) return;
    setBusy(true);
    setAlert(null);
    const answer = await bookNextTrial(source, chosen.utc, zone, newKey());
    setBusy(false);
    if (answer.ok) return setStep({ at: 'booked', trial: { ...answer.data, starts_at: answer.data.starts_at ?? chosen } });
    const { refusal } = answer;
    if (refusal.status === 404 || refusal.status === 410) return setStep({ at: 'gone', text: refusal.message });
    if (refusal.status === 429) return setAlert(refusal.message || tt('errors.limited'));
    if (refusal.status === 422 || refusal.status === 403) {
      // The time went meanwhile: a fresh list and another pick; anything else is a reason to say.
      const fresh = await load(times.status === 'ready' ? times.from : null);
      if (fresh && !stillOffered(fresh, chosen.utc)) {
        setChosen(null);
        return setAlert(refusal.message || tt('time.taken'));
      }
      if (fresh) return setStep({ at: 'refused', text: refusal.message });
      return;
    }
    setAlert(tt('errors.failed'));
  };

  const chat = whatsappLink(whatsapp, tt('whatsappWords'));
  const chatButton = chat ? <a className="ls-btn ls-btn-dark ls-btn-small" href={chat} target="_blank" rel="noopener"><WhatsAppIcon />{tt('whatsapp')}</a> : null;

  if (step.at === 'booked') {
    const { trial } = step;
    return (
      <div className="lt-card lr-next-card" data-state="booked">
        <BookedCard
          time={trial.starts_at}
          subject={trial.subject || subject}
          teacher={trial.teacher || undefined}
          minutes={trial.minutes}
          academy={academy}
          timeFormat={timeFormat}
          title={kind === 'second' ? t('next.booked.title', { name: learner }) : t('rebook.booked.title', { name: learner })}
          lead={kind === 'second' ? t('next.booked.lead') : undefined}
          next={[tt('booked.next.message'), tt('booked.next.reminder'), tt('booked.next.change'), kind === 'second' ? t('next.booked.after') : tt('booked.next.after')]}
          actions={done ? <a className="ls-btn lt-btn-soft ls-btn-small" href={done.href}>{done.label}</a> : undefined}
        />
      </div>
    );
  }
  if (step.at === 'refused' || step.at === 'gone') {
    return (
      <div className="lt-card lt-calm" data-tone="attention" role="status">
        <span className="lt-calm-icon" aria-hidden="true"><Icon name={step.at === 'gone' ? 'clock' : 'chat'} /></span>
        <FocusHeading level={2}>{step.at === 'gone' ? t('next.goneTitle') : kind === 'second' ? t('next.notNow') : t('rebook.cantTitle')}</FocusHeading>
        <p>{step.text || tt('refused.text')}</p>
        <div className="lt-actions">{chatButton}</div>
      </div>
    );
  }
  if (step.at === 'closed') {
    return (
      <section className="lt-card lr-next-card" aria-labelledby="lr-next-title">
        <div className="lr-next-head">
          <span className="lr-next-icon" aria-hidden="true"><Icon name="users" /></span>
          <div>
            <h2 id="lr-next-title">{t('next.title')}</h2>
            <p className="lt-muted">{t('next.text', { subject })}</p>
          </div>
        </div>
        {until ? <p className="lr-chip"><Icon name="clock" />{t('next.until', { when: until })}</p> : null}
        <div className="lt-actions">
          <button type="button" className="ls-btn ls-btn-dark" onClick={() => setStep({ at: 'picking' })}>
            <Icon name="calendar" />{t('next.cta')}
          </button>
        </div>
      </section>
    );
  }

  return (
    <section className="lt-card lr-next-card" aria-labelledby="lr-pick-title">
      {kind === 'second' ? (
        <header className="lt-step-head">
          <FocusHeading level={2}><span id="lr-pick-title">{t('next.pickTitle')}</span></FocusHeading>
          <p className="lt-muted">{t('next.pickLead', { subject, learner })}</p>
        </header>
      ) : (
        <header className="lt-step-head">
          <h2 id="lr-pick-title" className="lt-subhead">{tt('time.title')}</h2>
          <p className="lt-muted">{t('rebook.pickLead', { subject })}</p>
        </header>
      )}
      <p className="lt-alert" role="alert">{alert ?? ''}</p>
      <TimePicker
        name={`${kind}-time`}
        state={times}
        chosen={chosen}
        onChoose={(time) => {
          setChosen(time);
          setAlert(null);
        }}
        onRetry={() => void load(times.status === 'ready' ? times.from : null)}
        onLater={() => void load(times.status === 'ready' ? nextFrom(byDay(times.times, { days: [], from: '', to: '' }), times.from) : null)}
        onSoonest={times.status === 'ready' && times.from ? () => void load(null) : null}
        timeFormat={timeFormat}
        zone={zone}
        empty={<p className="lt-muted">{kind === 'second' ? t('next.empty') : t('rebook.empty')}</p>}
      />
      <div className="lt-nav">
        {kind === 'second' ? (
          <button type="button" className="lt-back" onClick={() => setStep({ at: 'closed' })}>{t('next.close')}</button>
        ) : <span className="lt-privacy">{tt('pick.noCode')}</span>}
        <button type="button" className="ls-btn ls-btn-main" onClick={() => void book()} disabled={!chosen || busy}>
          {busy ? t('next.booking') : chosen ? t('next.confirm') : tt('time.pickFirst')}
        </button>
      </div>
    </section>
  );
}
