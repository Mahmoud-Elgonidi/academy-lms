import type { ReactNode } from 'react';
import { getTranslations } from 'next-intl/server';
import { initialOf } from '@/lib/site/initial';
import { formatMinor, hasPlan, itemsOf, offerStateAt, perLessonMinor, type Money, type TrialReport } from '@/lib/site/report';
import { whatsappLink } from '@/lib/site/trial';
import { Icon, WhatsAppIcon } from '../icons';
import { OfferTimer } from './offer-timer';
import '../site.css';
import './trial.css';
import './report.css';

/**
 * The family's report after a free trial (docs/architecture/02, section 27,
 * J2), the same on the family's own link and in their account: who and what,
 * when and with whom, the level the teacher found, strengths and what to work
 * on, the teacher's words; then, in the classic look, the plan they recommend
 * with its price, the academy's offer and the time left on it, the way to
 * start, and the free second trial. A student's own look reads the report
 * alone, in their own words. Written on the server, whole without script; the
 * countdown ticks once it runs.
 */
export async function ReportView({ report, look, locale, now, start, next }: {
  report: TrialReport;
  /** family: a guardian or an adult (classic); student: a child or teen reading their own report. */
  look: 'family' | 'student';
  locale: string;
  /** The server's clock now (the offer's first numbers). */
  now: number;
  /** The way to start (classic), or nothing. */
  start?: ReactNode;
  /** The second trial's card (classic), or nothing. */
  next?: ReactNode;
}) {
  const t = await getTranslations('site.report');
  const student = look === 'student';
  const strengths = itemsOf(report.strengths);
  const work = itemsOf(report.to_work_on);
  const card = report.teacher.card;
  const teacherName = card?.name || report.teacher.name;

  return (
    <div className="lr" data-look={look}>
      <article className="lt-card lr-sheet" aria-labelledby="lr-title">
        <header className="lr-hero">
          <p className="lt-kicker">{student ? t('student.title') : report.second ? t('kickerSecond') : t('kicker')}</p>
          <h1 id="lr-title">{t('title', { learner: report.learner, subject: report.subject.name })}</h1>
          <ul className="lt-facts lr-facts">
            {report.held_at ? <li><Icon name="calendar" /><span>{report.held_at.label}</span></li> : null}
            {report.teacher.name ? <li><Icon name="user" /><span>{t('with', { teacher: report.teacher.name })}</span></li> : null}
          </ul>
        </header>

        {report.teacher.name ? (
          <section className="lr-teacher" aria-label={t('teacherRole')}>
            {card?.photo_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- the teacher's own photo from the academy's page
              <img src={card.photo_url} alt="" className="lr-avatar" />
            ) : (
              <span className="lr-avatar lr-avatar-initial" aria-hidden="true">{initialOf(teacherName.replace(/^\S+\.\s+/u, ''))}</span>
            )}
            <div>
              <p className="lr-teacher-name"><bdi>{teacherName}</bdi></p>
              <p className="lr-teacher-role">{t('teacherRole')}</p>
              {card?.bio ? <p className="lr-teacher-bio">{card.bio}</p> : null}
            </div>
          </section>
        ) : null}

        <section className="lr-level" aria-labelledby="lr-level-title">
          <span className="lr-level-steps" aria-hidden="true"><i /><i /><i /></span>
          <div>
            <h2 id="lr-level-title" className="lr-label">{student ? t('student.level') : t('level.title')}</h2>
            <p className="lr-level-text">{report.level}</p>
            {student ? null : <p className="lr-hint">{t('level.hint')}</p>}
          </div>
        </section>

        {strengths.length || work.length ? (
          <div className="lr-lists">
            {strengths.length ? (
              <section className="lr-list" data-tone="good" aria-labelledby="lr-strengths">
                <h2 id="lr-strengths" className="lr-label"><Icon name="star" />{student ? t('student.strengths') : t('strengths')}</h2>
                <ul>{strengths.map((line, i) => <li key={i}><Icon name="check" /><span>{line}</span></li>)}</ul>
              </section>
            ) : null}
            {work.length ? (
              <section className="lr-list" data-tone="grow" aria-labelledby="lr-work">
                <h2 id="lr-work" className="lr-label"><Icon name="sparkle" />{student ? t('student.toWorkOn') : t('toWorkOn')}</h2>
                <ul>{work.map((line, i) => <li key={i}><Icon name="chevron" className="ls-flip" /><span>{line}</span></li>)}</ul>
              </section>
            ) : null}
          </div>
        ) : null}

        {report.note ? (
          <figure className="lr-quote">
            <figcaption className="lr-label">{student ? t('student.note') : t('note')}</figcaption>
            <blockquote><p>{report.note}</p></blockquote>
            <p className="lr-quote-by"><bdi>{t('noteBy', { teacher: report.teacher.name })}</bdi></p>
          </figure>
        ) : null}
        {!strengths.length && !work.length && !report.note ? <p className="lt-muted">{t('nothingWritten')}</p> : null}
      </article>

      {look === 'family' ? <Recommendation report={report} locale={locale} now={now} start={start} /> : null}
      {/* Once a course started, the academy changes the teacher itself: no second trial to offer. */}
      {look === 'family' && report.course === null && report.offer_state !== 'started' ? next : null}
    </div>
  );
}

/** The plan in plain words: lessons a week and their length, lessons in the plan and how long it lasts, one-to-one or a group. */
async function PlanShape({ report }: { report: TrialReport }) {
  const t = await getTranslations('site.report.plan');
  const plan = report.recommendation.plan!;
  const per = report.recommendation.per_week;
  return (
    <>
      <p className="lr-plan-name">{plan.name}</p>
      <ul className="lr-shape">
        <li><Icon name="calendar" /><span>{t('perWeek', { count: plan.sessions_per_week })}</span></li>
        <li><Icon name="clock" /><span>{t('minutes', { minutes: plan.session_minutes })}</span></li>
        <li><Icon name="book" /><span>{t('block', { count: plan.sessions_per_block })} · {t('validity', { days: plan.validity_days })}</span></li>
        <li><Icon name={plan.format === 'group' ? 'users' : 'user'} /><span>{plan.format === 'group' ? t('group') : t('private')}</span></li>
      </ul>
      {per && per !== plan.sessions_per_week ? <p className="lr-hint">{t('teacherPerWeek', { count: per })}</p> : null}
    </>
  );
}

/** A price, big, with what one lesson comes to. */
function Price({ money, lessons, locale, perLessonWords, className }: { money: Money; lessons: number | null; locale: string; perLessonWords: (price: string) => string; className?: string }) {
  const one = lessons ? perLessonMinor(money, lessons) : null;
  return (
    <div className={`lr-price ${className ?? ''}`}>
      <b>{money.price}</b>
      {one !== null && lessons && lessons > 1 ? <small>{perLessonWords(formatMinor(one, money.currency, locale))}</small> : null}
    </div>
  );
}

/**
 * The plan the teacher recommends and its price in the family's region, the
 * offer (open with the time left, ended, used), a course already started, a
 * plan no longer offered or no price known, and the way to start.
 */
async function Recommendation({ report, locale, now, start }: { report: TrialReport; locale: string; now: number; start?: ReactNode }) {
  const t = await getTranslations('site.report');
  const { recommendation: rec, offer } = report;
  const state = offerStateAt(report.offer_state, offer, now);
  const lessons = rec.plan?.sessions_per_block ?? null;
  const perLesson = (price: string) => t('plan.perLesson', { price });
  const plan = hasPlan(report);

  if (state === 'started' || report.course) {
    const status = report.course?.status;
    return (
      <section className="lt-card lr-plan" data-state="started" aria-labelledby="lr-plan-title">
        <span className="lr-plan-icon" aria-hidden="true"><Icon name="check" /></span>
        <h2 id="lr-plan-title">{t('started.title', { learner: report.learner })}</h2>
        <p className="lt-muted">{t('started.text', { learner: report.learner, subject: report.subject.name })}</p>
        {status && ['pending_payment', 'active', 'paused'].includes(status) ? (
          <p className="lr-chip"><Icon name="bolt" />{t(`started.status.${status as 'active'}`)}</p>
        ) : null}
        {start}
      </section>
    );
  }

  const plain = rec.price ? <Price money={rec.price} lessons={lessons} locale={locale} perLessonWords={perLesson} /> : null;
  const region = rec.price && rec.region ? <p className="lr-hint">{t('plan.region', { region: rec.region.name, currency: rec.region.currency })}</p> : null;
  const noPrice = plan && !rec.price ? <p className="lr-note">{t('plan.noPrice')}</p> : null;
  const endedNote = (
    <div className="lr-offer" data-state="ended">
      <p className="lr-offer-title"><Icon name="clock" />{t('offer.ended.title')}</p>
      <p>{t('offer.ended.text')}</p>
    </div>
  );

  let offerPart: ReactNode = null;
  if (state === 'open' && offer) {
    const bigger = offer.family_discount_bigger;
    const open = (
      <div className="lr-offer" data-state="open">
        <p className="lr-offer-ribbon">{t('offer.badge', { percent: offer.percent })}</p>
        <p className="lr-offer-title"><Icon name="sparkles" />{t('offer.title')}</p>
        {plan && offer.price ? (
          <>
            <Price money={offer.price} lessons={lessons} locale={locale} perLessonWords={perLesson} className="lr-price-offer" />
            {!bigger && rec.price ? <p className="lr-was">{t.rich('offer.was', { price: rec.price.price, s: (c) => <s>{c}</s> })}</p> : null}
            {!bigger && offer.saving ? <p className="lr-saving"><Icon name="check" />{t('offer.saving', { price: offer.saving.price })}</p> : null}
          </>
        ) : (
          <p>{plan ? t('offer.lead', { percent: offer.percent }) : t('offer.anyPlan', { percent: offer.percent })}</p>
        )}
        {bigger ? <p className="lr-note">{t('offer.familyBigger')}</p> : null}
      </div>
    );
    offerPart = offer.ends_at ? (
      <OfferTimer
        endsUtc={offer.ends_at.utc}
        serverNow={now}
        until={offer.ends_at.label}
        ended={<>{endedNote}{plain}{region}</>}
      >
        {open}
        {region}
      </OfferTimer>
    ) : <>{open}{region}</>;
  }

  return (
    <section className="lt-card lr-plan" data-state={state} aria-labelledby="lr-plan-title">
      <h2 id="lr-plan-title" className="lr-plan-title"><Icon name="academic" />{t('plan.title')}</h2>
      {plan ? <PlanShape report={report} /> : (
        <p className="lr-note">{rec.plan_withdrawn ? t('plan.withdrawn') : rec.per_week ? t('plan.noPlan', { count: rec.per_week }) : t('plan.noPlanAtAll')}</p>
      )}
      {state === 'open' ? offerPart : (
        <>
          {state === 'ended' ? endedNote : null}
          {state === 'used' ? <p className="lr-note" data-tone="done"><Icon name="heart" />{t('offer.used')}</p> : null}
          {plan ? plain : null}
          {plan ? region : null}
        </>
      )}
      {noPrice}
      {start}
    </section>
  );
}

/**
 * The way to start, until J3's own flow: a WhatsApp chat with the academy
 * that already names the learner, the subject, the plan and the offer, so its
 * team completes the enrolment (and counts the offer while it is open); else
 * signing in (the family's link) or writing to the academy from Help (their
 * account). A course already started leads to the family's lessons instead.
 */
export async function StartWays({ report, whatsapp, signIn, help, lessons }: {
  report: TrialReport;
  whatsapp: string | null;
  /** The family's link: signing in, back to this report in their account. */
  signIn?: string;
  /** Their account: writing to the academy. */
  help?: string;
  /** Their account: the learner's lessons, once a course started. */
  lessons?: string;
}) {
  const t = await getTranslations('site.report');
  const started = report.offer_state === 'started' || report.course !== null;
  if (started) {
    const href = lessons ?? signIn;
    return href ? (
      <div className="lr-start">
        <a className="ls-btn ls-btn-dark" href={href}>{lessons ? t('started.openFamily', { learner: report.learner }) : t('started.open')}<Icon name="chevron" className="ls-flip" /></a>
      </div>
    ) : null;
  }
  const plan = hasPlan(report) ? report.recommendation.plan : null;
  const open = report.offer_state === 'open' && report.offer ? report.offer : null;
  const words = (plan
    ? t('start.words', { learner: report.learner, subject: report.subject.name, plan: plan.name })
    : t('start.wordsAny', { learner: report.learner, subject: report.subject.name }))
    + (open ? t('start.wordsOffer', { percent: open.percent }) : '');
  const chat = whatsapp ? `https://wa.me/${whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent(words)}` : null;
  const label = plan ? t('start.cta') : t('start.ctaAny');

  if (chat) {
    return (
      <div className="lr-start">
        <a className="ls-btn ls-btn-main" href={chat} target="_blank" rel="noopener" data-start="whatsapp">{label}<Icon name="chevron" className="ls-flip" /></a>
        <p>{open ? t('start.how') : t('start.howNoOffer')}</p>
        {signIn ? <a className="lt-link" href={signIn}>{t('start.signIn')}</a> : null}
      </div>
    );
  }
  const href = help ?? signIn;
  if (!href) return null;
  return (
    <div className="lr-start">
      <a className="ls-btn ls-btn-main" href={href} data-start={help ? 'help' : 'sign-in'}>{label}<Icon name="chevron" className="ls-flip" /></a>
      <p>{help ? t('start.howHelp') : t('start.howSignIn')}</p>
    </div>
  );
}

/** The second trial or a new time that cannot be booked now: why, in the API's words (what to do instead), and the academy's WhatsApp. */
export async function NotNow({ title, text, whatsapp }: { title: string; text: string; whatsapp: string | null }) {
  const t = await getTranslations('site.trial');
  const chat = whatsappLink(whatsapp, t('whatsappWords'));
  return (
    <section className="lt-card lt-calm" data-tone="attention" aria-labelledby="lr-notnow">
      <span className="lt-calm-icon" aria-hidden="true"><Icon name="chat" /></span>
      <h2 id="lr-notnow">{title}</h2>
      <p>{text}</p>
      {chat ? (
        <div className="lt-actions">
          <a className="ls-btn ls-btn-dark ls-btn-small" href={chat} target="_blank" rel="noopener"><WhatsAppIcon />{t('whatsapp')}</a>
        </div>
      ) : null}
    </section>
  );
}
