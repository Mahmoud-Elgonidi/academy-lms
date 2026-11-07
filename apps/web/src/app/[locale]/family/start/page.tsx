import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { StartFlow } from '@/components/start/start-flow';
import { buttonLook, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { read } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { choiceFrom, learnerOf, stepToShow, type Choice } from '@/lib/start/flow';
import type { StartOptions, StartSlots } from '@/lib/start/types';
import { academySite } from '@/lib/site/server';
import { hasModule, modulesOf } from '@/lib/modules';
import { me } from '@/lib/viewer';

type Props = { searchParams: Promise<Record<string, string | string[] | undefined>> };

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('start');
  return { title: t('metaTitle') };
}

/**
 * Starting a course on one's own (docs/architecture/02, section 27, J3), in
 * the classic look: the choice comes from the address (from the trial's
 * report, or step by step here), the options and, once a plan and a teacher
 * are chosen, the weekly times or the open groups are read for it each time,
 * and the flow (components/start) walks the rest. A student's own look and an
 * academy that enrols families itself get a calm card in the API's words.
 */
export default async function StartCourse({ searchParams }: Props) {
  const [params, t, fam, person, site] = await Promise.all([searchParams, getTranslations('start'), family(), me(), academySite()]);
  const asked = choiceFrom(params);
  const learnerId = asked.learner ?? (fam.learners.length === 1 ? fam.learners[0]!.id : undefined);
  const found = await read<{ data: StartOptions }>('/v1/family/start/options', { query: { learner_id: learnerId, subject_id: asked.subject } });
  const whatsapp = site?.whatsapp ?? null;

  if (!found.ok) {
    // The kids', middle school's and teens' own look (family_only), or a learner not theirs: a calm word, never an error page.
    const words = found.problem.status === 403 && found.problem.title ? found.problem.title : t('closed.text');
    return <Closed title={found.problem.status === 403 ? t('closed.familyOnly') : t('closed.title')} text={words} whatsapp={null} />;
  }
  const options = found.data.data;
  if (!options.self_serve) {
    return <Closed title={t('closed.academyTitle')} text={options.why_off ?? t('closed.text')} whatsapp={whatsapp} />;
  }

  const choice: Choice = { ...asked, learner: learnerOf(options, asked) };
  // A subject that cannot start (a course in it already) goes back to choosing one; a plan no longer offered too.
  if (choice.subject && !options.subjects.some((s) => s.id === choice.subject && s.can_start)) {
    Object.assign(choice, { subject: undefined, plan: undefined, teacher: undefined, group: undefined, times: [] });
  }
  if (choice.plan && !options.plans.some((p) => p.id === choice.plan)) Object.assign(choice, { plan: undefined, teacher: undefined, group: undefined, times: [] });
  const plan = options.plans.find((p) => p.id === choice.plan);
  if (choice.teacher && !options.teachers.some((x) => x.id === choice.teacher && x.private)) Object.assign(choice, { teacher: undefined, times: [] });
  const wanted = typeof params.step === 'string' ? params.step : undefined;
  let step = stepToShow(wanted, choice, options.learners.length, plan);

  // The times or groups for the plan (and teacher), read when a step shows them or the review needs them.
  let slots: StartSlots | null = null;
  let slotsProblem: string | null = null;
  const needsSlots = plan && choice.subject && (plan.format === 'group' ? ['teacher', 'review'].includes(step) : choice.teacher && ['times', 'review'].includes(step));
  if (needsSlots) {
    const got = await read<{ data: StartSlots }>('/v1/family/start/slots', {
      query: { learner_id: choice.learner, subject_id: choice.subject, plan_id: plan.id, teacher_id: plan.format === 'group' ? undefined : choice.teacher },
    });
    if (got.ok) slots = got.data.data;
    else slotsProblem = got.problem.title || t('refused.problem');
  }
  // A group that is no longer open is chosen again.
  if (plan?.format === 'group' && choice.group && slots && !slots.groups.some((g) => g.id === choice.group)) {
    choice.group = undefined;
    step = stepToShow(wanted, choice, options.learners.length, plan);
  }

  const recommended = typeof params.rec === 'string' && /^[0-9a-f-]{36}$/i.test(params.rec) ? params.rec : null;
  const trial = typeof params.trial === 'string' && /^[0-9a-f-]{36}$/i.test(params.trial) ? params.trial : null;
  return (
    <StartFlow
      options={options}
      choice={choice}
      step={step}
      slots={slots}
      slotsProblem={slotsProblem}
      zoneLabel={person.timezone_label}
      help={hasModule(modulesOf(person), 'support') ? '/family/help' : null}
      whatsapp={whatsapp}
      recommended={recommended}
      trial={trial}
    />
  );
}

/** Starting here is not for this person or this academy now: why, in the API's words, and the way to the academy. */
async function Closed({ title, text, whatsapp }: { title: string; text: string; whatsapp: string | null }) {
  const t = await getTranslations('start');
  const chat = whatsapp ? `https://wa.me/${whatsapp.replace(/\D/g, '')}` : null;
  return (
    <section className="mx-auto max-w-xl rounded-card border border-line bg-white p-6 text-center" aria-labelledby="st-closed" data-closed>
      <span className="mx-auto grid size-14 place-items-center rounded-full bg-info-soft text-info" aria-hidden="true"><Icon name="chat" size={26} /></span>
      <h1 id="st-closed" className="mt-3 text-xl font-bold">{title}</h1>
      <p className="mt-2 leading-7 text-ink/80">{text}</p>
      <div className="mt-5 flex flex-wrap justify-center gap-2">
        <Link href="/family" className={join(buttonLook('quiet'), 'min-h-10 px-4 text-sm')}>{t('closed.home')}</Link>
        {chat ? <a href={chat} target="_blank" rel="noopener" className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('refused.whatsapp')}</a> : null}
      </div>
    </section>
  );
}
