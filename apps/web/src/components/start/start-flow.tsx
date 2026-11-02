'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useEffect, useRef, useState, useTransition, type ReactNode } from 'react';
import { agreeFor, consentFor, startCourse, type StartBody } from '@/app/[locale]/family/start/actions';
import { Icon } from '@/components/icons';
import { Badge, Button, Notice, buttonLook, join } from '@/components/ui';
import { Link, useRouter } from '@/i18n/navigation';
import type { ChildConsent } from '@/lib/family/types';
import {
  INVOICE_COURSE_KEY,
  STARTED_KEY,
  choose,
  chooseGroup,
  firstLessonOf,
  inWeekOrder,
  isDone,
  keepFree,
  initialPicks,
  nextStep,
  previousStep,
  queryOf,
  refusalOf,
  skippedOf,
  stepsFor,
  timesByKey,
  timesReady,
  togglePick,
  type Choice,
  type Refusal,
  type Step,
} from '@/lib/start/flow';
import type { StartOptions, StartPlan, StartSlots, StartedCourse } from '@/lib/start/types';
import { CancelStart } from './invoice-actions';
import { usePressKey } from './press-key';
import { GroupStep, LearnerStep, PlanStep, SubjectStep, TeacherStep, TimesStep } from './start-steps';
import { Summary, type SummaryFacts } from './start-summary';
import './start.css';

/**
 * The family starts a course on its own (docs/architecture/02, section 27,
 * J3): whose course, the subject, the plan, the teacher or a group, the
 * weekly times, then a review of everything and of the first invoice before
 * confirming. Each choice lives in the address, so going back, refreshing or
 * sending the link keep it; the server reads the options and times for it
 * each time. The summary stays in sight (beside the steps, or a sheet on a
 * phone). Every refusal of the API gets its own calm answer with the next
 * step: times taken meanwhile are chosen again from the fresh ones keeping
 * the rest, a changed price is confirmed again, the guardian's consent is
 * asked for here and the start sent again by itself.
 */
export function StartFlow({ options, choice, step, slots, slotsProblem, zoneLabel, help, whatsapp, recommended, trial }: {
  options: StartOptions;
  choice: Choice;
  step: Step;
  slots: StartSlots | null;
  /** Why the times or groups cannot be read for this choice (the API's words). */
  slotsProblem: string | null;
  zoneLabel: string;
  help: string;
  whatsapp: string | null;
  /** The plan the trial's teacher recommended (from the report). */
  recommended: string | null;
  /** The free trial the family came from (back to its report). */
  trial: string | null;
}) {
  const t = useTranslations('start');
  const locale = useLocale();
  const router = useRouter();
  const [navigating, startNavigation] = useTransition();
  const [picked, setPicked] = useState<string[] | null>(null);
  const [refusal, setRefusal] = useState<Refusal | null>(null);
  const [consent, setConsent] = useState<ChildConsent | null>(null);
  const [sending, setSending] = useState(false);
  const [joined, setJoined] = useState<StartedCourse | null>(null);
  const [full, setFull] = useState(false);
  const key = usePressKey();
  const heading = useRef<HTMLHeadingElement>(null);
  const shownStep = useRef(step);

  const plan = options.plans.find((p) => p.id === choice.plan);
  const learner = options.learners.find((l) => l.id === choice.learner);
  const subject = options.subjects.find((s) => s.id === choice.subject);
  const teacher = options.teachers.find((x) => x.id === choice.teacher);
  const group = slots?.groups.find((g) => g.id === choice.group) ?? null;
  const steps = stepsFor(options.learners.length, plan);
  const free = timesByKey(slots);
  // The times chosen: what was pressed here (still free), else those in the address or the suggested pattern.
  const picks = keepFree(picked ?? initialPicks(choice.times, slots), [...free.keys()]);
  const extra = { rec: recommended ?? undefined, trial: trial ?? undefined };

  // The strip of steps keeps the current one in sight on a narrow screen.
  const strip = useRef<HTMLOListElement>(null);
  useEffect(() => {
    strip.current?.querySelector('[aria-current="step"]')?.scrollIntoView({ block: 'nearest', inline: 'center' });
  }, [step]);

  // A new step: its heading takes the focus (a screen reader hears where it is), the page scrolls to it.
  useEffect(() => {
    if (shownStep.current === step) return;
    shownStep.current = step;
    heading.current?.focus({ preventScroll: true });
    heading.current?.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }, [step]);

  const go = (to: Step, next: Choice = { ...choice, times: picks }, keep?: Refusal | null) => {
    setRefusal(keep ?? null);
    setFull(false);
    startNavigation(() => router.push(`/family/start?${queryOf(next, to, extra)}`, { scroll: false }));
  };
  const advance = (next: Choice, from: Step) => {
    const nextPlan = options.plans.find((p) => p.id === next.plan);
    go(nextStep(from, options.learners.length, nextPlan) ?? 'review', next);
  };

  // The weekly times: one press each; kept in the address as they change (no new history entry).
  const press = (timeKey: string) => {
    if (!plan) return;
    const turned = togglePick(picks, timeKey, plan.sessions_per_week);
    setPicked(turned.picks);
    setFull(Boolean(turned.full));
    if (refusal?.kind === 'times') setRefusal(null);
    window.history.replaceState(window.history.state, '', `${window.location.pathname}?${queryOf({ ...choice, times: turned.picks }, 'times', extra)}`);
  };

  const body = (): StartBody | null => {
    if (!choice.learner || !choice.subject || !plan) return null;
    const base = { learner_id: choice.learner, subject_id: choice.subject, plan_id: plan.id, price_minor: plan.price.amount_minor, currency: plan.price.currency };
    return plan.format === 'group' ? { ...base, group_id: choice.group } : { ...base, teacher_id: choice.teacher, times: picks };
  };

  /** Confirms: one key per confirmation (a lost answer is sent again with it); a refusal is answered for good, so the next try takes a new key. */
  const confirm = async () => {
    const sent = body();
    if (!sent || sending) return;
    setSending(true);
    setRefusal(null);
    const answer = await startCourse(sent, key.take());
    if (answer.ok) {
      key.answered();
      const course = answer.course;
      try {
        window.sessionStorage.setItem(STARTED_KEY(course.enrollment_id), JSON.stringify(course));
        if (course.invoice) window.sessionStorage.setItem(INVOICE_COURSE_KEY(course.invoice.id), course.enrollment_id);
      } catch {
        // The welcome then shows what the invoice says.
      }
      if (course.invoice) {
        router.push(`/family/invoices/${course.invoice.id}?course=${course.enrollment_id}&started=1`);
        return;
      }
      setJoined(course);
      setSending(false);
      return;
    }
    setSending(false);
    if (answer.problem.status === 0 || answer.problem.status >= 500) {
      setRefusal({ kind: 'say', message: t('refused.offline') });
      return;
    }
    key.answered();
    const found = refusalOf(answer.problem, t('refused.problem'));
    switch (found.kind) {
      case 'consent': {
        const child = await consentFor(found.studentId);
        if (child && child.state === 'waiting') {
          setConsent(child);
          setRefusal(found);
        } else {
          setRefusal({ kind: 'say', message: found.message });
        }
        return;
      }
      case 'times': {
        const kept = found.free ? keepFree(picks, found.free) : [];
        setPicked(kept);
        go('times', { ...choice, times: kept }, found);
        return;
      }
      case 'price':
        setRefusal(found);
        startNavigation(() => router.refresh());
        return;
      case 'choose': {
        // The choice the API refused is made again; what came before it stays.
        const again: Record<typeof found.step, Choice> = {
          subject: { learner: choice.learner, times: [] },
          plan: { learner: choice.learner, subject: choice.subject, times: [] },
          teacher: { ...choice, teacher: undefined, group: undefined, times: [] },
          times: { ...choice, times: picks },
        };
        go(found.step, again[found.step], found);
        return;
      }
      default:
        setRefusal(found);
    }
  };

  /** The guardian agreed: the same start goes again by itself. */
  const agreed = async (child: ChildConsent): Promise<string | undefined> => {
    const done = await agreeFor(child.student.id, child.text.version);
    if (!done.ok) return done.message ?? t('refused.problem');
    setConsent(null);
    await confirm();
    return undefined;
  };

  if (joined) return <Joined course={joined} />;

  const first = plan?.format === 'group' ? null : firstLessonOf(picks, slots);
  const facts: SummaryFacts = {
    learner: learner ?? null,
    subject: subject?.name ?? null,
    plan: plan ?? null,
    teacher: plan?.format === 'group' ? (group ? `${group.name} · ${group.teacher.name}` : null) : teacher?.name ?? null,
    times: plan?.format === 'group' ? (group?.times.map((x) => x.label) ?? []) : inWeekOrder(picks, slots).map((x) => x.label),
    first: first?.first ?? (plan?.format === 'group' && group?.status === 'running' ? group.next : null),
    skips: plan?.format === 'group' ? [] : skippedOf(picks, slots),
    offer: options.offer,
  };

  const at = steps.indexOf(step);
  const ready = step === 'times' ? (plan ? timesReady(picks, plan.sessions_per_week) : false) : step === 'review';
  const back = previousStep(step, options.learners.length, plan);
  const priceChanged = refusal?.kind === 'price';
  const primary = step === 'times'
    ? { label: t('continue'), disabled: !ready || navigating, onClick: () => go('review', { ...choice, times: picks }) }
    : step === 'review' && !consent
      ? { label: sending ? t('confirming') : priceChanged ? t('review.confirmNew') : t('review.confirm'), disabled: sending || navigating, onClick: confirm }
      : null;

  const panelTitle = step === 'teacher' && plan?.format === 'group' ? t('steps.group') : t(`steps.${step}`);

  return (
    <div className="space-y-5" data-step={step}>
      <header className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="text-sm font-semibold text-brand-strong">{t('kicker')}</p>
          {trial ? (
            <Link href={`/family/trials/${trial}`} className="inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-muted hover:text-ink">
              <Icon name="report" size={16} />
              {t('backToReport')}
            </Link>
          ) : null}
        </div>
        <h1 className="text-2xl font-bold">{learner ? (learner.is_self ? t('titleSelf') : t('title', { learner: learner.name })) : t('titleAny')}</h1>
        <div className="st-progress">
          <ol ref={strip} className="st-steps" aria-label={t('progress', { step: at + 1, count: steps.length })}>
            {steps.map((s, n) => {
              const state = n < at ? 'done' : n === at ? 'current' : 'next';
              const label = s === 'teacher' && plan?.format === 'group' ? t('short.group') : t(`short.${s}`);
              return (
                <li key={s}>
                  {state === 'done' ? (
                    <button type="button" className="st-step" data-state="done" onClick={() => go(s)} aria-label={t('goBackTo', { step: label })}>
                      <span className="st-step-n"><Icon name="check" size={14} /></span>
                      {label}
                    </button>
                  ) : (
                    <span className="st-step" data-state={state} aria-current={state === 'current' ? 'step' : undefined}>
                      <span className="st-step-n">{n + 1}</span>
                      {label}
                    </span>
                  )}
                </li>
              );
            })}
          </ol>
          <div className="st-bar" aria-hidden="true">
            <span style={{ transform: `scaleX(${(at + 1) / steps.length})` }} />
          </div>
          <p className="text-xs text-muted">{t('progress', { step: at + 1, count: steps.length })}</p>
        </div>
      </header>

      <div className="st-layout">
        <section className={join('st-panel min-w-0 space-y-4', navigating && 'opacity-60 transition-opacity')} aria-labelledby="st-title" aria-busy={navigating} key={step}>
          <div className="flex flex-wrap items-end justify-between gap-2">
            <div>
              <h2 id="st-title" ref={heading} tabIndex={-1} className="scroll-mt-24 text-xl font-bold outline-none">{panelTitle}</h2>
              <p className="mt-1 text-sm text-muted">{stepHint(step, plan, t, learner?.name, slots)}</p>
            </div>
            {back ? (
              <button type="button" onClick={() => go(back)} className="inline-flex min-h-10 items-center gap-1 rounded-control px-2 text-sm font-semibold text-muted hover:bg-paper hover:text-ink">
                <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
                {t('back')}
              </button>
            ) : null}
          </div>

          {refusal && refusal.kind !== 'consent' ? <RefusalCard refusal={refusal} step={step} help={help} whatsapp={whatsapp} onSubject={() => go('subject', { learner: choice.learner, times: [] })} /> : null}

          {step === 'learner' ? (
            <LearnerStep learners={options.learners} chosen={choice.learner} onChoose={(id) => advance(choose(choice, 'learner', id), 'learner')} />
          ) : null}
          {step === 'subject' ? (
            <SubjectStep subjects={options.subjects} chosen={choice.subject} learner={learner?.name ?? options.learner.name} onChoose={(id) => advance(choose(choice, 'subject', id), 'subject')} />
          ) : null}
          {step === 'plan' ? (
            <PlanStep plans={options.plans} chosen={choice.plan} recommended={recommended} offer={options.offer} region={options.region} onChoose={(id) => advance(choose(choice, 'plan', id, options.plans), 'plan')} />
          ) : null}
          {step === 'teacher' && plan?.format === 'group' ? (
            <GroupStep groups={slots?.groups ?? []} problem={slotsProblem} chosen={choice.group} onChoose={(id) => advance(chooseGroup(choice, id), 'teacher')}
              onPrivate={() => go('plan', { learner: choice.learner, subject: choice.subject, times: [] })} help={help} />
          ) : null}
          {step === 'teacher' && plan?.format !== 'group' ? (
            <TeacherStep teachers={options.teachers} chosen={choice.teacher} learner={learner} trial={options.trial} onChoose={(id) => advance(choose(choice, 'teacher', id), 'teacher')} />
          ) : null}
          {step === 'times' && plan ? (
            <TimesStep slots={slots} problem={slotsProblem} plan={plan} picks={picks} full={full} zoneLabel={zoneLabel} teacher={teacher?.name ?? ''} locale={locale}
              onPress={press} onTeacher={() => go('teacher', { ...choice, teacher: undefined, times: [] })} onPlan={() => go('plan', { learner: choice.learner, subject: choice.subject, times: [] })} />
          ) : null}
          {step === 'review' && plan ? (
            consent ? (
              <ConsentPanel child={consent} onAgree={agreed} />
            ) : (
              <Review plan={plan} facts={facts} options={options} learnerName={learner?.name ?? ''} isSelf={learner?.is_self ?? false} consentWaiting={learner?.consent_waiting ?? false}
                group={group} priceChanged={priceChanged} />
            )
          ) : null}

          {primary ? (
            <div className="hidden lg:flex lg:justify-end">
              <Button onClick={primary.onClick} disabled={primary.disabled} className="min-w-52">{primary.label}</Button>
            </div>
          ) : null}
        </section>

        <Summary facts={facts} primary={primary} step={step} isDone={(s) => isDone(s, { ...choice, times: picks }, plan)} />
      </div>
    </div>
  );
}

/** What a step asks, in a sentence. */
function stepHint(step: Step, plan: StartPlan | undefined, t: (key: string, values?: Record<string, string | number>) => string, learner: string | undefined, slots: StartSlots | null): string {
  switch (step) {
    case 'learner':
      return t('hints.learner');
    case 'subject':
      return t('hints.subject', { learner: learner ?? '' });
    case 'plan':
      return t('hints.plan');
    case 'teacher':
      return plan?.format === 'group' ? t('hints.group') : t('hints.teacher');
    case 'times':
      return t('hints.times', { count: slots?.per_week ?? plan?.sessions_per_week ?? 1 });
    case 'review':
      return t('hints.review');
  }
}

/** A refusal in its own calm card, with what to do next. */
function RefusalCard({ refusal, step, help, whatsapp, onSubject }: { refusal: Refusal; step: Step; help: string; whatsapp: string | null; onSubject: () => void }) {
  const t = useTranslations('start.refused');
  if (refusal.kind === 'consent') return null;
  if (refusal.kind === 'course') {
    return (
      <div role="alert" className="rounded-card border border-attention/40 bg-attention-soft p-4" data-refusal="course">
        <p className="flex items-start gap-2 font-semibold text-ink"><Icon name="calendar" className="mt-0.5 shrink-0 text-attention" />{refusal.message}</p>
        <div className="mt-3 flex flex-wrap gap-2">
          {refusal.pending ? <Link href="/family/money" className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('payIt')}</Link> : <Link href="/family/lessons" className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('openCourse')}</Link>}
          {refusal.pendingOwn && refusal.enrollmentId ? <CancelStart enrollment={refusal.enrollmentId} compact /> : null}
          <button type="button" onClick={onSubject} className={join(buttonLook('quiet'), 'min-h-10 px-4 text-sm')}>{t('otherSubject')}</button>
        </div>
      </div>
    );
  }
  if (refusal.kind === 'contact') {
    const chat = whatsapp ? `https://wa.me/${whatsapp.replace(/\D/g, '')}` : null;
    return (
      <div role="alert" className="rounded-card border border-info/30 bg-info-soft p-4" data-refusal="contact">
        <p className="flex items-start gap-2 font-semibold text-ink"><Icon name="chat" className="mt-0.5 shrink-0 text-info" />{refusal.message}</p>
        <div className="mt-3 flex flex-wrap gap-2">
          <Link href={help} className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('writeToUs')}</Link>
          {chat ? <a href={chat} target="_blank" rel="noopener" className={join(buttonLook('quiet'), 'min-h-10 px-4 text-sm')}>{t('whatsapp')}</a> : null}
        </div>
      </div>
    );
  }
  if (refusal.kind === 'price') {
    return (
      <div role="alert" className="rounded-card border border-attention/40 bg-attention-soft p-4" data-refusal="price">
        <p className="flex items-start gap-2 font-semibold text-ink"><Icon name="alert" className="mt-0.5 shrink-0 text-attention" />{refusal.message}</p>
        <p className="mt-2 text-sm">{t('checkPrice')}</p>
      </div>
    );
  }
  if (refusal.kind === 'times' || refusal.kind === 'choose') {
    if (refusal.step !== step) return <Notice tone="attention">{refusal.message}</Notice>;
    return (
      <div role="alert" className="rounded-card border border-attention/40 bg-attention-soft p-4" data-refusal={refusal.kind === 'times' ? 'time_taken' : 'choose'}>
        <p className="flex items-start gap-2 font-semibold text-ink"><Icon name={refusal.kind === 'times' ? 'clock' : 'alert'} className="mt-0.5 shrink-0 text-attention" />{refusal.message}</p>
        {refusal.kind === 'times' ? <p className="mt-1 text-sm text-ink/80">{t('keptPicks')}</p> : null}
      </div>
    );
  }
  return <div role="alert" className="rounded-card bg-danger-soft px-4 py-3 text-sm font-semibold text-danger" data-refusal="say">{refusal.message}</div>;
}

/** The review before confirming: the course, its times, the first invoice and how paying goes, in plain words. */
function Review({ plan, facts, options, learnerName, isSelf, consentWaiting, group, priceChanged }: {
  plan: StartPlan;
  facts: SummaryFacts;
  options: StartOptions;
  learnerName: string;
  isSelf: boolean;
  consentWaiting: boolean;
  group: StartSlots['groups'][number] | null;
  priceChanged: boolean;
}) {
  const t = useTranslations('start.review');
  const s = useTranslations('start');
  const hours = options.payment?.unpaid_cancel_hours ?? 0;
  const discounted = plan.first_invoice.total && plan.first_invoice.discounts.length > 0;
  const offer = options.offer;
  const forming = plan.format === 'group' && group?.status === 'forming';
  return (
    <div className="space-y-4">
      <section className="rounded-card border border-line bg-white p-4" aria-labelledby="rv-course">
        <h3 id="rv-course" className="text-sm font-semibold text-muted">{t('course')}</h3>
        <dl className="mt-2 grid gap-2 text-[15px] sm:grid-cols-2">
          <Fact label={s('summary.learner')}><bdi>{isSelf ? s('summary.me', { name: learnerName }) : learnerName}</bdi></Fact>
          <Fact label={s('summary.subject')}>{facts.subject}</Fact>
          <Fact label={s('summary.plan')}>{s('shape.line', { perWeek: plan.sessions_per_week, minutes: plan.session_minutes })}</Fact>
          <Fact label={plan.format === 'group' ? s('summary.group') : s('summary.teacher')}>{facts.teacher ? <bdi>{facts.teacher}</bdi> : null}</Fact>
        </dl>
      </section>

      <section className="rounded-card border border-line bg-white p-4" aria-labelledby="rv-times">
        <h3 id="rv-times" className="text-sm font-semibold text-muted">{t('times')}</h3>
        <ul className="mt-2 flex flex-wrap gap-2">
          {facts.times.map((label) => (
            <li key={label} className="inline-flex items-center gap-1.5 rounded-full bg-brand-soft px-3 py-1 text-sm font-semibold text-brand-strong"><Icon name="calendar" size={15} />{label}</li>
          ))}
        </ul>
        <p className="mt-3 text-[15px]">
          {forming ? t('formingFirst') : facts.first ? t.rich('first', { when: facts.first.label, b: (c) => <b>{c}</b> }) : null}
        </p>
        {facts.skips.length > 0 ? (
          <div className="mt-3 rounded-control bg-info-soft p-3" data-review-skips>
            <h4 className="flex items-center gap-1.5 text-sm font-semibold text-ink"><Icon name="calendar" size={15} className="text-info" />{t('skipped')}</h4>
            <ul className="mt-1.5 space-y-1 text-sm">
              {facts.skips.map((skip) => <li key={skip.text} data-skip={skip.reason}>{skip.text}</li>)}
            </ul>
            <p className="mt-1.5 text-xs text-ink/75">{t('skippedNote')}</p>
          </div>
        ) : null}
        <p className="mt-1 text-xs text-muted">{t('zone')}</p>
      </section>

      <section className="rounded-card border border-line bg-white p-4" aria-labelledby="rv-money" data-money>
        <h3 id="rv-money" className="text-sm font-semibold text-muted">{t('money')}</h3>
        <dl className="st-lines mt-2 text-[15px]">
          <div><dt>{s('shape.block', { count: plan.sessions_per_block })} · {s('shape.minutes', { minutes: plan.session_minutes })}</dt><dd className={priceChanged ? 'st-flash font-bold' : undefined}>{plan.price.amount}</dd></div>
          {plan.first_invoice.discounts.map((d) => (
            <div key={d.label} data-kind="discount"><dt>{d.label}</dt><dd>{d.amount}</dd></div>
          ))}
          <div data-kind="total"><dt>{t('total')}</dt><dd>{discounted ? plan.first_invoice.total!.amount : plan.price.amount}</dd></div>
        </dl>
        <p className="mt-2 text-xs text-muted">{t('vat')}</p>
        {offer?.state === 'open' && plan.first_invoice.offer_applied ? (
          <Notice tone="brand">{offer.ends_at ? t('offerOn', { percent: offer.percent, until: offer.ends_at.label }) : t('offerOnAny', { percent: offer.percent })}</Notice>
        ) : null}
        {offer?.state === 'open' && !plan.first_invoice.offer_applied && plan.first_invoice.discounts.length > 0 ? <Notice tone="info">{t('siblingBigger')}</Notice> : null}
        {offer?.state === 'ended' ? <Notice tone="muted">{t('offerEnded')}</Notice> : null}
      </section>

      <section className="rounded-card border border-brand-line bg-brand-soft/60 p-4" aria-labelledby="rv-pay">
        <h3 id="rv-pay" className="text-sm font-semibold text-brand-strong">{t('howPaying')}</h3>
        <ol className="mt-2 space-y-2 text-[15px]">
          <li className="flex gap-2"><span className="st-step-n shrink-0 bg-white">1</span><span>{forming ? t('pay1Group') : t('pay1')}</span></li>
          <li className="flex gap-2"><span className="st-step-n shrink-0 bg-white">2</span><span>{forming ? t('pay2Group') : hours > 0 ? t('pay2', { hours }) : t('pay2Any')}</span></li>
          <li className="flex gap-2"><span className="st-step-n shrink-0 bg-white">3</span><span>{t('pay3')}</span></li>
        </ol>
        <p className="mt-3 text-sm text-ink/80">{t('renews', { count: plan.renews_at_lessons_left })}</p>
      </section>

      {consentWaiting ? <Notice tone="info">{t('consentAhead', { learner: learnerName })}</Notice> : null}
    </div>
  );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="font-semibold">{children ?? '—'}</dd>
    </div>
  );
}

/** The guardian's consent, asked for when the API needs it before the course starts: the wording, "I agree", and the start goes on by itself. */
function ConsentPanel({ child, onAgree }: { child: ChildConsent; onAgree: (child: ChildConsent) => Promise<string | undefined> }) {
  const t = useTranslations('start.consent');
  const title = useRef<HTMLHeadingElement>(null);
  // It takes the place of the review: its title takes the focus and comes into sight.
  useEffect(() => {
    title.current?.focus({ preventScroll: true });
    title.current?.scrollIntoView({ block: 'center' });
  }, []);
  const [ticked, setTicked] = useState(false);
  const [error, setError] = useState<string>();
  const [busy, setBusy] = useState(false);
  const agree = async () => {
    if (!ticked) {
      setError(t('tickFirst'));
      return;
    }
    setBusy(true);
    setError(undefined);
    const why = await onAgree(child);
    setBusy(false);
    if (why) setError(why);
  };
  return (
    <section className="rounded-card border-2 border-info/40 bg-white p-5" aria-labelledby="st-consent" data-consent>
      <p className="flex items-center gap-2 text-sm font-semibold text-info"><Icon name="shield" size={18} />{t('kicker')}</p>
      <h3 id="st-consent" ref={title} tabIndex={-1} className="mt-1 text-lg font-bold outline-none">{child.text.title}</h3>
      <p className="mt-2 leading-7">{t('why', { learner: child.student.name })}</p>
      <p className="mt-2 leading-7">{child.text.intro}</p>
      <ul className="mt-3 grid gap-2">
        {child.text.sections.map((part) => (
          <li key={part.title} className="rounded-control bg-info-soft px-3 py-2 text-sm">
            <strong className="block">{part.title}</strong>
            {part.body}
          </li>
        ))}
      </ul>
      <label className="mt-3 flex cursor-pointer items-start gap-3 rounded-control border border-line p-3 text-sm font-semibold">
        <input type="checkbox" className="mt-1 size-4 accent-[var(--color-brand)]" checked={ticked} onChange={(e) => setTicked(e.target.checked)} />
        <span>{child.text.agree}</span>
      </label>
      {error ? <p role="alert" className="mt-2 text-sm font-semibold text-danger">{error}</p> : null}
      <Button className="mt-3 w-full sm:w-auto" onClick={agree} disabled={busy}>{busy ? t('going') : t('agree')}</Button>
      <p className="mt-2 text-xs text-muted">{t('after')}</p>
    </section>
  );
}

/** A course that joined the learner's shared package: no invoice; its lessons draw on that balance. */
function Joined({ course }: { course: StartedCourse }) {
  const t = useTranslations('start.joined');
  return (
    <section className="st-panel mx-auto max-w-xl rounded-card border border-brand-line bg-white p-6 text-center" aria-labelledby="st-joined">
      <span className="st-celebrate mx-auto grid size-14 place-items-center rounded-full bg-brand text-white"><Icon name="check" size={28} /></span>
      <h1 id="st-joined" className="mt-3 text-2xl font-bold">{t('title', { learner: course.learner.name, subject: course.subject.name })}</h1>
      <p className="mt-2 text-muted">{course.offer.message ?? t('text')}</p>
      <ul className="mt-4 flex flex-wrap justify-center gap-2">
        {course.weekly_times.map((w) => <li key={`${w.weekday}${w.start}`}><Badge tone="brand">{w.label}</Badge></li>)}
      </ul>
      <p className="mt-3">{course.payment.text}</p>
      <Link href="/family/lessons" className={join(buttonLook('primary'), 'mt-5')}>{t('lessons')}</Link>
    </section>
  );
}
