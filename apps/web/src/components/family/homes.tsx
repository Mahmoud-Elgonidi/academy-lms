import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { HifzCard } from '@/components/hifz/card';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Card, Empty, Progress, SectionTitle, buttonLook, join } from '@/components/ui';
import type { FamilyLesson, Homework, Invoices, Learner } from '@/lib/family/types';
import { hasStars, hereIsland } from '@/lib/journey/journey';
import type { JourneyMap } from '@/lib/journey/types';
import { courseGroup } from '@/lib/lesson-format';
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

async function HomeworkDue({ homework }: { homework: Homework | undefined }) {
  const t = await getTranslations('family');
  if (!homework) return null;
  return (
    <Link href="/family/homework" className="lift flex items-center gap-3 rounded-card border border-line bg-white p-4">
      <span className="grid size-11 shrink-0 place-items-center rounded-control bg-info-soft text-info">
        <Icon name="book" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-semibold">{t('homeworkLine', { title: homework.title })}</span>
        <span className="block text-sm text-muted">{homework.due_at ? t('dueBy', { when: homework.due_at.label }) : null}</span>
      </span>
      <span className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm')}>{t('handIn')}</span>
    </Link>
  );
}

/**
 * The journey on the classic home ("Yousef's journey", or "my journey" for an
 * adult): each course's level and how far, the stars, and the missions open.
 */
async function JourneyCard({ journey, learner }: { journey: JourneyMap; learner: Learner }) {
  const [t, f] = await Promise.all([getTranslations('journey'), getTranslations('format')]);
  const open = journey.missions.filter((m) => m.state !== 'done').length;
  return (
    <Link href="/family/journey" className="lift block rounded-card border border-brand-line bg-white p-4">
      <span className="flex items-center gap-3">
        <span className="grid size-11 shrink-0 place-items-center rounded-control bg-brand-soft text-brand-strong" aria-hidden="true">
          <Icon name="map" />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block font-semibold">{learner.is_self ? t('mine') : t('journeyOf', { name: `\u2068${learner.name.split(' ')[0] ?? learner.name}\u2069` })}</span>
          <span className="block text-sm text-muted">{t('openMissions', { n: open })}</span>
        </span>
        {hasStars(journey) ? (
          <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-reward-soft px-3 py-1 text-sm font-bold tabular-nums">
            <Icon name="star" size={16} fill="var(--color-reward)" className="text-reward" />
            {journey.stars.total}
          </span>
        ) : null}
        <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
      </span>
      {journey.courses.length > 0 ? (
        <span className="mt-3 grid gap-3 sm:grid-cols-2">
          {journey.courses.slice(0, 4).map((c) => {
            const island = hereIsland(c);
            const group = courseGroup(c);
            return (
              <span key={c.enrollment_id} className="block rounded-control bg-paper p-3">
                <span className="flex items-baseline justify-between gap-2">
                  <span className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-semibold">{c.program ?? c.subject}</span>
                    {group ? <FormatBadge format="group" seats={{ taken: group.taken, capacity: group.capacity }} className="shrink-0" /> : null}
                  </span>
                  {c.percent !== null ? <span className="shrink-0 text-sm tabular-nums text-muted">{t('percent', { p: c.percent })}</span> : null}
                </span>
                {c.percent !== null ? (
                  <span className="mt-2 block">
                    <Progress value={c.percent} label={c.program ?? c.subject ?? ''} />
                  </span>
                ) : (
                  <span className="mt-1 block text-sm text-muted">{t('noCurriculum')}</span>
                )}
                {group?.seat ? <span className="mt-1.5 block text-sm font-semibold text-group-strong">{f('seatNumber', { seat: group.seat, capacity: group.capacity })}</span> : null}
                {island ? <span className="mt-1.5 block truncate text-sm text-muted">{t('levelOf', { n: island.index, total: c.islands.length, title: island.title })}</span> : null}
              </span>
            );
          })}
        </span>
      ) : null}
    </Link>
  );
}

function openHomework(list: Homework[]): Homework | undefined {
  return list.find((h) => h.status === 'assigned' || h.status === 'missed');
}

/** A guardian's (or an adult student's) home: the next lesson, lessons left and what is owed, the journey's card (and today's hifz review), homework, and notices. */
export async function ClassicHome({ name, learner, lessons, notices, balanceLessons, invoices, homework, journey }: {
  name: string;
  learner: Learner;
  lessons: FamilyLesson[];
  notices: string[];
  balanceLessons: number;
  invoices: Invoices | null;
  homework: Homework[];
  journey: JourneyMap;
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
      <JourneyCard journey={journey} learner={learner} />
      {journey.hifz ? <HifzCard hifz={journey.hifz} /> : null}
      <HomeworkDue homework={openHomework(homework)} />
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
