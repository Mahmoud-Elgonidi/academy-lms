import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Card, Empty, Progress, SectionTitle, buttonLook, join } from '@/components/ui';
import { dayLabel } from '@/lib/format';
import type { Course, FamilyLesson, Homework, Invoices, Journey, Learner } from '@/lib/family/types';
import { FamilyLessonCard } from './lesson-card';

/** The lesson on now or next to come: booked, and not over by the clock. */
export function nextOf(lessons: FamilyLesson[]): FamilyLesson | undefined {
  const now = Date.now();
  return lessons.find((l) => (l.status === 'scheduled' || l.status === 'in_progress') && Date.parse(l.ends_at.utc) > now);
}

async function NextLesson({ lesson }: { lesson: FamilyLesson | undefined }) {
  const t = await getTranslations('family');
  return (
    <section>
      <SectionTitle trailing={<Link href="/family/lessons" className="font-semibold text-brand-strong">{t('allLessons')}</Link>}>{t('nextLesson')}</SectionTitle>
      {lesson ? <FamilyLessonCard lesson={lesson} featured /> : <Empty>{t('noNextLesson')}</Empty>}
    </section>
  );
}

async function HomeworkDue({ homework, kids = false }: { homework: Homework | undefined; kids?: boolean }) {
  const t = await getTranslations('family');
  if (!homework) return null;
  return (
    <Link href="/family/homework" className="lift flex items-center gap-3 rounded-card border border-line bg-white p-4">
      <span className="grid size-11 shrink-0 place-items-center rounded-control bg-info-soft text-info">
        <Icon name="book" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-semibold">{t('homeworkLine', { title: homework.title })}</span>
        <span className="block text-sm text-muted">{kids ? t('kidsHomeworkStar') : homework.due_at ? t('dueBy', { when: homework.due_at.label }) : null}</span>
      </span>
      <span className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('handIn')}</span>
    </Link>
  );
}

function openHomework(list: Homework[]): Homework | undefined {
  return list.find((h) => h.status === 'assigned' || h.status === 'missed');
}

/** A guardian's (or an adult student's) home: the next lesson, lessons left and what is owed, homework, and notices. */
export async function ClassicHome({ name, learner, lessons, notices, balanceLessons, invoices, homework, courses }: {
  name: string;
  learner: Learner;
  lessons: FamilyLesson[];
  notices: string[];
  balanceLessons: number;
  invoices: Invoices | null;
  homework: Homework[];
  courses: Course[];
}) {
  const t = await getTranslations('family');
  const due = invoices?.data.find((i) => i.outstanding_minor > 0);
  return (
    <div className="stagger space-y-5">
      <header>
        <h1 className="text-2xl font-bold sm:text-3xl">{t('greeting', { name: name.split(' ')[0] ?? name })}</h1>
        {!learner.is_self ? <p className="text-muted">{t('progressOf', { name: learner.name })}</p> : null}
      </header>
      <NextLesson lesson={nextOf(lessons)} />
      <div className="grid gap-3 sm:grid-cols-2">
        <Card>
          <p className="text-sm text-muted">{t('balance')}</p>
          <p className="mt-1 font-heading text-2xl font-bold">{t('lessonsLeft', { n: balanceLessons })}</p>
        </Card>
        {invoices ? (
          <Card className={due ? 'border-attention/40' : undefined}>
            <p className="text-sm text-muted">{t('owed')}</p>
            <p className="mt-1 font-heading text-2xl font-bold">{due ? (invoices.owed ?? due.outstanding) : t('nothingOwed')}</p>
            {due ? (
              <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                {due.due_at ? <span className="text-sm text-muted">{t('dueBy', { when: due.due_at.label })}</span> : <span />}
                <Link href="/family/money" className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>
                  {t('payNow')}
                </Link>
              </div>
            ) : null}
          </Card>
        ) : null}
      </div>
      <HomeworkDue homework={openHomework(homework)} />
      {courses.length > 0 ? (
        <section>
          <SectionTitle>{t('progressOf', { name: learner.name })}</SectionTitle>
          <ul className="grid gap-3 sm:grid-cols-2">
            {courses.map((c) => (
              <li key={c.enrollment_id}>
                <Link href={`/family/progress/${c.enrollment_id}`} className="lift block rounded-card border border-line bg-white p-4">
                  <span className="flex items-baseline justify-between gap-2">
                    <span className="font-semibold">{c.program ?? c.subject}</span>
                    {c.percent !== null ? <span className="text-sm tabular-nums text-muted">{c.percent}%</span> : null}
                  </span>
                  {c.percent !== null ? (
                    <span className="mt-2 block">
                      <Progress value={c.percent} label={c.program ?? c.subject} />
                    </span>
                  ) : (
                    <span className="mt-1 block text-sm text-muted">{t('noProgram')}</span>
                  )}
                  {c.next[0]?.title ? <span className="mt-1.5 block truncate text-sm text-muted">{t('hereNow', { title: c.next[0].title })}</span> : null}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
      {notices.length > 0 ? (
        <section>
          <SectionTitle>{t('notices')}</SectionTitle>
          <ul className="space-y-2">
            {notices.map((n) => (
              <li key={n} className="rounded-card bg-info-soft px-4 py-3 text-sm text-info">
                {n}
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}

/** A child's home: their stars, the next lesson, and the course as a path of stops with "you're here". */
export async function KidsHome({ learner, journey, lessons, homework }: { learner: Learner; journey: Journey; lessons: FamilyLesson[]; homework: Homework[] }) {
  const t = await getTranslations('family');
  const course = journey.courses[0];
  return (
    <div className="stagger space-y-5">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-muted">{t('kidsHello')}</p>
          <h1 className="text-3xl font-extrabold">{t('kidsHelloName', { name: learner.name.split(' ')[0] ?? learner.name })}</h1>
        </div>
        <span className="pop inline-flex items-center gap-2 rounded-full bg-reward-soft px-4 py-2 font-bold text-ink">
          <Icon name="star" size={22} fill="var(--color-reward)" className="text-reward" />
          {t('stars', { n: journey.stars })}
        </span>
      </header>
      {course ? (
        <Card className="overflow-hidden">
          <h2 className="text-xl font-extrabold">{t('kidsJourney', { program: course.program ?? course.subject })}</h2>
          <p className="mt-1 text-muted">
            {t('kidsStations', { n: course.worlds.reduce((n, w) => n + w.done, 0) })} {course.next[0]?.title ? t('kidsNext', { title: course.next[0].title }) : ''}
          </p>
          <JourneyPath course={course} initial={learner.name.trim().charAt(0)} />
        </Card>
      ) : (
        <Empty>{t('noCourses')}</Empty>
      )}
      <NextLesson lesson={nextOf(lessons)} />
      <HomeworkDue homework={openHomework(homework)} kids />
    </div>
  );
}

/**
 * The course as a winding path: done stops with a star, the current one with
 * the child's initial (it bobs, when motion is allowed), the rest waiting.
 */
async function JourneyPath({ course, initial }: { course: Course; initial: string }) {
  const t = await getTranslations('family');
  const stops = course.worlds.flatMap((w) => w.lessons);
  const here = Math.max(0, stops.findIndex((s) => s.state === 'here'));
  const from = Math.max(0, Math.min(here - 2, stops.length - 6));
  const shown = stops.slice(from, from + 6);
  return (
    <ol className="relative mt-6 grid grid-cols-3 gap-y-8 sm:grid-cols-6">
      {shown.map((stop, i) => {
        const index = from + i;
        const done = stop.state === 'done' || stop.state === 'review';
        const now = index === here;
        return (
          <li key={stop.id ?? index} className={join('flex flex-col items-center gap-2 text-center', i % 2 === 1 && 'sm:translate-y-6')}>
            <span
              className={join(
                'grid size-14 place-items-center rounded-full border-4 font-heading text-lg font-extrabold',
                done && 'border-brand bg-brand text-white',
                now && 'bob border-reward bg-reward-soft text-ink',
                !done && !now && 'border-dashed border-line bg-white text-muted',
              )}
            >
              {done ? <Icon name="star" size={22} fill="currentColor" /> : now ? initial : index + 1}
            </span>
            <span className={join('text-sm leading-tight', now ? 'font-bold' : 'text-muted')}>{stop.title}</span>
            {now ? <Badge tone="attention">{t('youAreHere')}</Badge> : null}
          </li>
        );
      })}
    </ol>
  );
}

/** A middle-school student's home: rank and points to the next, the streak, the world and stage, badges, homework. */
export async function MiddleHome({ learner, journey, lessons, homework }: { learner: Learner; journey: Journey; lessons: FamilyLesson[]; homework: Homework[] }) {
  const t = await getTranslations('family');
  const course = journey.courses[0];
  const world = course?.worlds.find((w) => w.state === 'here') ?? course?.worlds[0];
  const hereIndex = world ? world.lessons.findIndex((l) => l.state === 'here') : -1;
  const earned = journey.badges.filter((b) => b.earned).length;
  return (
    <div className="stagger space-y-5">
      <h1 className="text-2xl font-bold">{t('greeting', { name: learner.name.split(' ')[0] ?? learner.name })}</h1>
      <Card className="bg-gradient-to-br from-brand-soft to-white">
        <p className="text-sm text-muted">{t('levelRank', { index: course?.level?.index ?? 1, rank: journey.rank.label })}</p>
        <p className="mt-1 font-heading text-3xl font-extrabold tabular-nums">{t('points', { n: journey.points })}</p>
        <div className="mt-3">
          <div className="mb-1.5 flex justify-between text-sm">
            <span className="font-semibold">{journey.rank.next_label ? t('pointsTo', { rank: journey.rank.next_label }) : t('topRank')}</span>
            {journey.rank.next ? <span className="text-muted">{t('pointsLeft', { n: Math.max(0, journey.rank.next - journey.points) })}</span> : null}
          </div>
          <Progress value={journey.rank.progress} label={journey.rank.label} />
        </div>
        <div className="mt-3 flex flex-wrap gap-2">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-attention-soft px-3 py-1 text-sm font-semibold text-attention">
            <Icon name="flame" size={16} />
            {t('streak', { n: journey.streak })}
          </span>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-reward-soft px-3 py-1 text-sm font-semibold text-ink">
            <Icon name="star" size={16} fill="var(--color-reward)" className="text-reward" />
            {t('stars', { n: journey.stars })}
          </span>
        </div>
      </Card>
      {course && world ? (
        <Card>
          <p className="text-sm font-semibold text-muted">{t('worldStage', { index: world.index, title: world.title })}</p>
          <p className="mt-1 text-sm text-muted">{t('stageOf', { i: hereIndex < 0 ? world.done : hereIndex + 1, n: world.lessons.length })}</p>
          <ol className="mt-3 flex gap-1.5">
            {world.lessons.map((l, i) => (
              <li key={l.id ?? i} title={l.title ?? undefined} className={join('h-2.5 flex-1 rounded-full', l.state === 'done' || l.state === 'review' ? 'bg-brand' : l.state === 'here' ? 'bg-reward' : 'bg-line')} />
            ))}
          </ol>
          {course.next[0]?.title ? <p className="mt-3 font-semibold">{t('hereNow', { title: course.next[0].title })}</p> : null}
        </Card>
      ) : null}
      <NextLesson lesson={nextOf(lessons)} />
      <section>
        <SectionTitle trailing={<Link href="/family/challenges" className="font-semibold text-brand-strong">{t('badgesCount', { earned, total: journey.badges.length })}</Link>}>{t('badges')}</SectionTitle>
        <ul className="grid grid-cols-3 gap-2 sm:grid-cols-6">
          {journey.badges.map((b) => (
            <li key={b.key} title={b.hint} className={join('rounded-card border p-3 text-center text-xs', b.earned ? 'border-brand-line bg-white' : 'border-dashed border-line text-muted opacity-70')}>
              <Icon name="trophy" size={22} className={join('mx-auto', b.earned ? 'text-reward' : 'text-line')} />
              <span className="mt-1 block font-semibold">{b.name}</span>
            </li>
          ))}
        </ul>
      </section>
      <HomeworkDue homework={openHomework(homework)} />
    </div>
  );
}

/** A high-school student's plan: the goal, how far along, the finish at this pace, the milestones, and what is next. */
export async function TeenHome({ learner, journey, lessons, homework }: { learner: Learner; journey: Journey; lessons: FamilyLesson[]; homework: Homework[] }) {
  const [t, locale] = await Promise.all([getTranslations('family'), getLocale()]);
  return (
    <div className="stagger space-y-5">
      <h1 className="text-2xl font-bold">{t('planFor', { name: learner.name.split(' ')[0] ?? learner.name })}</h1>
      {journey.courses.length === 0 ? <Empty>{t('noCourses')}</Empty> : null}
      {journey.courses.map((course) => (
        <Card key={course.enrollment_id}>
          <p className="text-sm text-muted">{t('goal')}</p>
          <p className="font-heading text-xl font-bold">{course.program ?? course.subject}</p>
          {course.percent !== null ? (
            <div className="mt-3">
              <Progress value={course.percent} label={course.program ?? course.subject} />
              <p className="mt-1 text-sm text-muted">{t('percentDone', { p: course.percent })}</p>
            </div>
          ) : null}
          {course.finished ? (
            <p className="mt-3 font-semibold text-brand-strong">{t('programDone')}</p>
          ) : (
            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div>
                <dt className="text-muted">{t('finishOn')}</dt>
                <dd className="font-semibold">{course.finish_on ? dayLabel(course.finish_on.local.slice(0, 10), locale, { day: 'numeric', month: 'long' }) : '—'}</dd>
              </div>
              <div>
                <dt className="text-muted">{t('pace')}</dt>
                <dd className="font-semibold">{course.lessons_per_week ? t('perWeek', { n: course.lessons_per_week }) : '—'}</dd>
              </div>
            </dl>
          )}
          <h2 className="mb-2 mt-5 text-sm font-semibold text-muted">{t('milestones')}</h2>
          <ol className="space-y-2 border-s-2 border-line ps-4">
            {course.worlds.map((w) => (
              <li key={w.index} className="relative">
                <span className={join('absolute -start-[1.4rem] top-1.5 size-3 rounded-full', w.state === 'done' ? 'bg-brand' : w.state === 'here' ? 'bg-reward' : 'bg-line')} aria-hidden="true" />
                <p className={join('font-semibold', w.state === 'ahead' && 'text-muted')}>{w.title}</p>
                <p className="text-xs text-muted">
                  {w.percent}%
                  {w.state === 'here' && course.level?.weeks_left !== null && course.level?.weeks_left !== undefined ? ` · ${t('weeksLeftLevel', { n: course.level.weeks_left })}` : ''}
                </p>
              </li>
            ))}
          </ol>
        </Card>
      ))}
      <NextLesson lesson={nextOf(lessons)} />
      <HomeworkDue homework={openHomework(homework)} />
    </div>
  );
}
