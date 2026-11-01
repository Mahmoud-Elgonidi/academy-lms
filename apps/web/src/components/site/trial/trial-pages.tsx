'use client';

import { useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import {
  bookFromPick,
  cancelTrial,
  lessonTimes,
  moveTrial,
  trialLesson,
  trialTimes,
  type BookedTrial,
  type TrialLesson,
} from '@/lib/site/actions';
import { byDay, nextFrom, stillOffered, whatsappLink } from '@/lib/site/trial';
import type { LocalTime, TimeFormat } from '@/lib/time';
import { Icon, WhatsAppIcon } from '../icons';
import { AddToCalendar, BigTime, BookedCard, FocusHeading } from './booked';
import { TimePicker, type TimesState } from './time-picker';
import { newKey, useBrowserZone } from './trial-request';

/** What a "pick a time" link's request says (GET site/trials/pick/{token}). */
export type PickRequest = {
  name: string;
  subject: { id: string; name: string } | null;
  for_child: boolean;
  child_name: string | null;
  child_birth_year: number | null;
  timezone: string | null;
  preferred_days: number[];
  preferred_from: number | null;
  preferred_to: number | null;
  teacher_gender: string | null;
};

type Common = { token: string; academy: string; whatsapp: string | null; timeFormat: TimeFormat; locale: string };

/** A link that stopped working while the page was open: what happened and the ways on. */
function Gone({ title, text, whatsapp, home, homeLabel }: { title: string; text: string; whatsapp: string | null; home?: string; homeLabel?: string }) {
  const t = useTranslations('site.trial');
  const chat = whatsappLink(whatsapp, t('whatsappWords'));
  return (
    <section className="lt-card lt-calm" data-tone="attention">
      <span className="lt-calm-icon" aria-hidden="true"><Icon name="clock" /></span>
      <FocusHeading level={2}>{title}</FocusHeading>
      <p>{text}</p>
      <div className="lt-actions">
        {chat ? <a className="ls-btn ls-btn-dark ls-btn-small" href={chat} target="_blank" rel="noopener"><WhatsAppIcon />{t('whatsapp')}</a> : null}
        {home ? <a className="ls-btn lt-btn-soft ls-btn-small" href={home}>{homeLabel}</a> : null}
      </div>
    </section>
  );
}

/**
 * The "pick a time" page from the instant reply: the request's subject and
 * learner, the free times on the family's clock with what suits them first,
 * and booking with no code (the link went to their number).
 */
export function PickTrial({ request, token, academy, whatsapp, timeFormat, locale }: Common & { request: PickRequest }) {
  const t = useTranslations('site.trial');
  const browser = useBrowserZone();
  const zone = browser || request.timezone || '';
  const subject = request.subject!;
  const prefs = {
    days: request.preferred_days,
    from: request.preferred_from === null ? '' : String(request.preferred_from),
    to: request.preferred_to === null ? '' : String(request.preferred_to),
  };
  const [times, setTimes] = useState<TimesState>({ status: 'loading' });
  const [chosen, setChosen] = useState<LocalTime | null>(null);
  const [alert, setAlert] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [booked, setBooked] = useState<BookedTrial | null>(null);
  const [refused, setRefused] = useState<string | null>(null);
  const [gone, setGone] = useState(false);

  const ask = (from: string | null) => trialTimes({
    subjectId: subject.id,
    birthYear: request.for_child ? request.child_birth_year : null,
    teacherGender: request.teacher_gender ?? '',
    from,
    timezone: zone,
  });
  const show = (found: Awaited<ReturnType<typeof ask>>, from: string | null) => {
    if (!found.ok && found.refusal.status === 404) setGone(true);
    setTimes(found.ok ? { status: 'ready', times: found.data, from } : { status: 'failed' });
    return found.ok ? found.data : null;
  };
  const load = async (from: string | null) => {
    setTimes({ status: 'loading' });
    return show(await ask(from), from);
  };

  useEffect(() => {
    // The times on this browser's clock, once it is known.
    void (async () => {
      const found = await ask(null);
      show(found, null);
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- once per clock
  }, [zone]);

  const book = async () => {
    if (!chosen) return;
    setBusy(true);
    setAlert(null);
    const done = await bookFromPick(token, chosen.utc, zone, newKey());
    setBusy(false);
    if (done.ok) return setBooked({ ...done.data, starts_at: done.data.starts_at ?? chosen });
    const { refusal } = done;
    if (refusal.status === 404) return setGone(true);
    if (refusal.status === 429) return setAlert(refusal.message || t('errors.limited'));
    if (refusal.status === 422) {
      const fresh = await load(times.status === 'ready' ? times.from : null);
      if (fresh && !stillOffered(fresh, chosen.utc)) {
        setChosen(null);
        return setAlert(refusal.message || t('time.taken'));
      }
      return setRefused(refusal.message);
    }
    setAlert(t('errors.failed'));
  };

  if (gone) {
    return <Gone title={t('pick.gone.title')} text={t('pick.gone.text')} whatsapp={whatsapp} home={`/${locale}#join`} homeLabel={t('pick.gone.again')} />;
  }
  if (booked?.starts_at) {
    return (
      <section className="lt-card">
        <BookedCard
          time={booked.starts_at}
          subject={booked.subject || subject.name}
          teacher={booked.teacher || undefined}
          minutes={booked.minutes}
          academy={academy}
          timeFormat={timeFormat}
          title={request.for_child && request.child_name ? t('booked.titleChild', { name: request.child_name }) : t('booked.title')}
          next={[t('booked.next.message'), t('booked.next.reminder'), t('booked.next.change'), t('booked.next.after')]}
        />
      </section>
    );
  }
  if (refused !== null) {
    const chat = whatsappLink(whatsapp, t('whatsappWords'));
    return (
      <section className="lt-card lt-calm" data-tone="attention">
        <span className="lt-calm-icon" aria-hidden="true"><Icon name="chat" /></span>
        <FocusHeading level={2}>{t('refused.title')}</FocusHeading>
        <p>{refused || t('refused.text')}</p>
        <div className="lt-actions">
          {chat ? <a className="ls-btn ls-btn-dark ls-btn-small" href={chat} target="_blank" rel="noopener"><WhatsAppIcon />{t('whatsapp')}</a> : null}
        </div>
      </section>
    );
  }

  const learner = request.for_child && request.child_name ? request.child_name : request.name;
  return (
    <section className="lt-card" aria-labelledby="lt-pick-title">
      <header className="lt-step-head">
        <p className="lt-kicker">{t('pick.hello', { name: request.name.split(' ')[0] ?? request.name })}</p>
        <h1 id="lt-pick-title">{t('pick.title')}</h1>
        <p className="lt-muted">{t('pick.for', { learner, subject: subject.name })}</p>
      </header>
      <p className="lt-alert" role="alert">{alert ?? ''}</p>
      <TimePicker
        name="pick-time"
        state={times}
        chosen={chosen}
        onChoose={(time) => {
          setChosen(time);
          setAlert(null);
        }}
        onRetry={() => void load(times.status === 'ready' ? times.from : null)}
        onLater={() => void load(times.status === 'ready' ? nextFrom(byDay(times.times, prefs), times.from) : null)}
        onSoonest={times.status === 'ready' && times.from ? () => void load(null) : null}
        prefs={prefs}
        timeFormat={timeFormat}
        zone={zone}
        empty={<p className="lt-muted">{t('pick.empty')}</p>}
      />
      <div className="lt-nav">
        <span className="lt-privacy">{t('pick.noCode')}</span>
        <button type="button" className="ls-btn ls-btn-main" onClick={() => void book()} disabled={!chosen || busy}>
          {busy ? t('pick.booking') : chosen ? t('pick.confirm') : t('time.pickFirst')}
        </button>
      </div>
    </section>
  );
}

/** The trial as it stands: when, what, with whom, how long. */
function LessonFacts({ lesson, timeFormat }: { lesson: TrialLesson; timeFormat: TimeFormat }) {
  const t = useTranslations('site.trial');
  return (
    <div className="lt-lesson">
      {lesson.starts_at ? <BigTime time={lesson.starts_at} timeFormat={timeFormat} /> : null}
      <ul className="lt-facts">
        <li><Icon name="book" /><span>{lesson.subject}</span></li>
        {lesson.teacher ? <li><Icon name="user" /><span>{t.rich('booked.with', { teacher: lesson.teacher, name: (chunks) => <bdi>{chunks}</bdi> })}</span></li> : null}
        <li><Icon name="clock" /><span>{t('booked.minutes', { minutes: lesson.minutes })}</span></li>
      </ul>
    </div>
  );
}

/**
 * Changing the trial's time from its own link: the lesson now, until when
 * and how many more times it can move, the same teacher's free times, and
 * every reason it cannot move as its own calm card with the ways on.
 */
export function ChangeTrial({ lesson: first, token, academy, whatsapp, timeFormat, locale }: Common & { lesson: TrialLesson }) {
  const t = useTranslations('site.trial');
  const zone = useBrowserZone();
  const [lesson, setLesson] = useState(first);
  const [times, setTimes] = useState<TimesState>({ status: 'loading' });
  const [chosen, setChosen] = useState<LocalTime | null>(null);
  const [alert, setAlert] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [moved, setMoved] = useState(false);
  const [gone, setGone] = useState(false);

  const load = async (from: string | null) => {
    setTimes({ status: 'loading' });
    const found = await lessonTimes(token, from, zone);
    if (!found.ok && found.refusal.status === 404) setGone(true);
    setTimes(found.ok ? { status: 'ready', times: found.data, from } : { status: 'failed' });
    return found.ok ? found.data : null;
  };

  useEffect(() => {
    if (!zone) return;
    // The lesson and its times on this browser's clock.
    void (async () => {
      const fresh = await trialLesson(token, zone);
      if (fresh.ok) setLesson(fresh.data);
      else if (fresh.refusal.status === 404) return setGone(true);
      if (!fresh.ok || fresh.data.can_move) await load(null);
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- once per clock
  }, [zone, token]);

  const move = async () => {
    if (!chosen) return;
    setBusy(true);
    setAlert(null);
    const done = await moveTrial(token, chosen.utc, zone, newKey());
    setBusy(false);
    if (done.ok) {
      setLesson(done.data);
      setMoved(true);
      return;
    }
    const { refusal } = done;
    if (refusal.status === 404) return setGone(true);
    if (refusal.status === 429) return setAlert(refusal.message || t('errors.limited'));
    if (refusal.status === 422) {
      const fresh = await trialLesson(token, zone);
      if (fresh.ok) setLesson(fresh.data);
      if (fresh.ok && !fresh.data.can_move) return;
      await load(times.status === 'ready' ? times.from : null);
      setChosen(null);
      return setAlert(refusal.message || t('time.taken'));
    }
    setAlert(t('errors.failed'));
  };

  const cancelHref = `/${locale}/trial/lesson/${token}/cancel`;
  if (gone) {
    return <Gone title={t('lesson.gone.title')} text={t('lesson.gone.text')} whatsapp={whatsapp} home={`/${locale}#join`} homeLabel={t('lesson.gone.again')} />;
  }
  if (moved && lesson.starts_at) {
    return (
      <section className="lt-card">
        <BookedCard
          time={lesson.starts_at}
          subject={lesson.subject}
          teacher={lesson.teacher || undefined}
          minutes={lesson.minutes}
          academy={academy}
          timeFormat={timeFormat}
          title={t('lesson.change.moved.title')}
          lead={t('lesson.change.moved.text')}
          next={[t('lesson.change.moved.next.reminder'), lesson.moves_left > 0
            ? t('lesson.change.moved.next.again', { count: lesson.moves_left })
            : t('lesson.change.moved.next.last')]}
        />
      </section>
    );
  }
  const chat = whatsappLink(whatsapp, t('whatsappWords'));

  return (
    <section className="lt-card" aria-labelledby="lt-change-title">
      <header className="lt-step-head">
        <p className="lt-kicker">{t('lesson.kicker')}</p>
        <h1 id="lt-change-title">{t('lesson.change.title')}</h1>
      </header>
      <LessonFacts lesson={lesson} timeFormat={timeFormat} />
      {lesson.can_move ? (
        <>
          <ul className="lt-rules">
            {lesson.move_until ? <li><Icon name="clock" /><span>{t('lesson.change.until', { when: lesson.move_until.label })}</span></li> : null}
            <li><Icon name="refresh" /><span>{t('lesson.change.movesLeft', { count: lesson.moves_left })}</span></li>
          </ul>
          <h2 className="lt-subhead">{t('lesson.change.pickNew')}</h2>
          <p className="lt-alert" role="alert">{alert ?? ''}</p>
          <TimePicker
            name="move-time"
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
            empty={<p className="lt-muted">{t('lesson.change.noTimes')}</p>}
          />
          <div className="lt-nav">
            <a className="lt-link" href={cancelHref}>{t('lesson.change.toCancel')}</a>
            <button type="button" className="ls-btn ls-btn-main" onClick={() => void move()} disabled={!chosen || busy}>
              {busy ? t('lesson.change.moving') : chosen ? t('lesson.change.confirm') : t('time.pickFirst')}
            </button>
          </div>
        </>
      ) : (
        <div className="lt-calm lt-calm-inline" data-tone="attention">
          <span className="lt-calm-icon" aria-hidden="true"><Icon name="calendar" /></span>
          <FocusHeading level={2}>{t('lesson.change.cantTitle')}</FocusHeading>
          <p>{lesson.cannot_move}</p>
          <div className="lt-actions">
            <a className="ls-btn ls-btn-dark ls-btn-small" href={cancelHref}>{t('lesson.change.toCancel')}</a>
            {chat ? <a className="ls-btn lt-btn-soft ls-btn-small" href={chat} target="_blank" rel="noopener"><WhatsAppIcon />{t('whatsapp')}</a> : null}
          </div>
        </div>
      )}
    </section>
  );
}

/**
 * "We cannot come" from the trial's own link: the lesson, a confirmation with
 * an optional reason, keeping it instead, and what to do after.
 */
export function CancelTrial({ lesson: first, token, academy, whatsapp, timeFormat, locale }: Common & { lesson: TrialLesson }) {
  const t = useTranslations('site.trial');
  const pageLocale = useLocale();
  const zone = useBrowserZone();
  const [lesson, setLesson] = useState(first);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [alert, setAlert] = useState<string | null>(null);
  const [state, setState] = useState<'ask' | 'kept' | 'cancelled' | 'gone'>('ask');

  useEffect(() => {
    if (!zone) return;
    void trialLesson(token, zone).then((fresh) => {
      if (fresh.ok) setLesson(fresh.data);
      else if (fresh.refusal.status === 404) setState('gone');
    });
  }, [zone, token]);

  const cancel = async () => {
    setBusy(true);
    setAlert(null);
    const done = await cancelTrial(token, note, newKey());
    setBusy(false);
    if (done.ok) return setState('cancelled');
    if (done.refusal.status === 404) return setState('gone');
    if (done.refusal.status === 429) return setAlert(done.refusal.message || t('errors.limited'));
    setAlert(done.refusal.status === 422 ? done.refusal.message || t('errors.failed') : t('errors.failed'));
  };

  const home = `/${pageLocale || locale}`;
  if (state === 'gone') {
    return <Gone title={t('lesson.gone.title')} text={t('lesson.gone.text')} whatsapp={whatsapp} home={`${home}#join`} homeLabel={t('lesson.gone.again')} />;
  }
  if (state === 'cancelled') {
    const chat = whatsappLink(whatsapp, t('whatsappWords'));
    return (
      <section className="lt-card lt-calm" data-tone="done">
        <span className="lt-calm-icon" aria-hidden="true"><Icon name="check" /></span>
        <FocusHeading level={2}>{t('lesson.cancel.done.title')}</FocusHeading>
        <p>{t('lesson.cancel.done.text')}</p>
        <div className="lt-actions">
          <a className="ls-btn ls-btn-dark ls-btn-small" href={`${home}#join`}>{t('lesson.cancel.done.again')}</a>
          {chat ? <a className="ls-btn lt-btn-soft ls-btn-small" href={chat} target="_blank" rel="noopener"><WhatsAppIcon />{t('whatsapp')}</a> : null}
        </div>
      </section>
    );
  }
  if (state === 'kept') {
    return (
      <section className="lt-card">
        <span className="lt-booked-tick" aria-hidden="true"><Icon name="check" /></span>
        <FocusHeading level={2} className="lt-center">{t('lesson.cancel.kept.title')}</FocusHeading>
        <p className="lt-muted lt-center">{t('lesson.cancel.kept.text')}</p>
        <LessonFacts lesson={lesson} timeFormat={timeFormat} />
        {lesson.starts_at ? <AddToCalendar event={{ utc: lesson.starts_at.utc, minutes: lesson.minutes, title: t('booked.eventTitle', { subject: lesson.subject, academy }) }} /> : null}
      </section>
    );
  }

  return (
    <section className="lt-card" aria-labelledby="lt-cancel-title">
      <header className="lt-step-head">
        <p className="lt-kicker">{t('lesson.kicker')}</p>
        <h1 id="lt-cancel-title">{t('lesson.cancel.title')}</h1>
        <p className="lt-muted">{t('lesson.cancel.lead')}</p>
      </header>
      <LessonFacts lesson={lesson} timeFormat={timeFormat} />
      <p className="lt-alert" role="alert">{alert ?? ''}</p>
      <label className="ls-field">
        <span>{t('lesson.cancel.reason')}</span>
        <textarea rows={3} value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} aria-describedby="lt-reason-hint" />
        <small id="lt-reason-hint" className="lt-field-hint">{t('lesson.cancel.reasonHint')}</small>
      </label>
      {lesson.can_move ? (
        <p className="lt-hint" role="note">
          {t('lesson.cancel.tryChange')}{' '}
          <a className="lt-link" href={`${home}/trial/lesson/${token}/change`}>{t('lesson.cancel.changeLink')}</a>
        </p>
      ) : null}
      <div className="lt-nav">
        <button type="button" className="ls-btn lt-btn-soft" onClick={() => setState('kept')} disabled={busy}>{t('lesson.cancel.keep')}</button>
        <button type="button" className="ls-btn lt-btn-danger" onClick={() => void cancel()} disabled={busy}>
          {busy ? t('lesson.cancel.cancelling') : t('lesson.cancel.confirm')}
        </button>
      </div>
    </section>
  );
}
