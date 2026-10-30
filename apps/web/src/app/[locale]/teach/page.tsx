import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { RefreshAt } from '@/components/live-refresh';
import { LessonCard } from '@/components/teach/lesson-card';
import { SectionTitle, join, type Tone } from '@/components/ui';
import { get } from '@/lib/api/server';
import { dayLabel, hoursOf } from '@/lib/format';
import { momentsOf } from '@/lib/live';
import type { Homework, Lesson, LessonDays, ReportSummary, Trials } from '@/lib/teach/types';
import { me } from '@/lib/viewer';

/**
 * The teacher's day on their own clock: how many lessons and hours, the next
 * one with its way in, the rest of the day, and what waits (reports to write,
 * homework to mark, trials to assess).
 */
export default async function Today({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const [t, common, person, day, reports, homework, trials] = await Promise.all([
    getTranslations('teach'),
    getTranslations('common'),
    me(),
    get<LessonDays>('/v1/teacher/lessons', { query: { days: 1 } }),
    get<{ data: ReportSummary[] }>('/v1/teacher/reports'),
    get<{ data: Homework[] }>('/v1/teacher/homework'),
    get<Trials>('/v1/teacher/trials'),
  ]);

  const lessons = day.data;
  const next = nextOf(lessons);
  const rest = lessons.filter((l) => l.id !== next?.id);
  const open = reports.data.filter((r) => r.status === 'due' || r.status === 'overdue');
  const first = open[0];

  const waiting: { href: string; count: number; label: string; caption?: string; tone: Tone; icon: 'report' | 'mark' | 'spark' }[] = [];
  if (open.length > 0) {
    waiting.push({
      href: open.length === 1 && first ? `/teach/reports/${first.id}` : '/teach/reports',
      count: open.length,
      label: t('reportsWaiting', { n: open.length }),
      caption: first ? `${first.student?.name ?? ''}${first.due_at ? ` · ${t('reportDueBy', { when: first.due_at.label })}` : ''}` : undefined,
      tone: open.some((r) => r.status === 'overdue') ? 'danger' : 'attention',
      icon: 'report',
    });
  }
  if (homework.data.length > 0) {
    waiting.push({ href: '/teach/marking', count: homework.data.length, label: t('homeworkToMark', { n: homework.data.length }), tone: 'info', icon: 'mark' });
  }
  if (trials.to_assess.length > 0) {
    waiting.push({ href: '/teach/trials', count: trials.to_assess.length, label: t('trialsToAssess', { n: trials.to_assess.length }), tone: 'brand', icon: 'spark' });
  }

  return (
    <div className="stagger space-y-4">
      <RefreshAt moments={momentsOf(lessons)} />
      <header>
        <p className="text-sm text-muted">{t('todayHeader', { date: dayLabel(day.from, locale), zone: person.timezone_label })}</p>
        <h1 className="mt-1 text-2xl font-bold sm:text-3xl">{t('todayCount', { n: day.count })}</h1>
        {day.minutes > 0 ? <p className="text-muted">{common('hoursAmount', { h: hoursOf(day.minutes) })}</p> : null}
      </header>

      {next ? (
        <section aria-labelledby="next">
          <SectionTitle>
            <span id="next">{next.status === 'in_progress' ? t('onNow') : t('nextLesson')}</span>
          </SectionTitle>
          <LessonCard lesson={next} featured />
        </section>
      ) : null}

      {rest.length > 0 ? (
        <section>
          <SectionTitle>{t('restOfDay')}</SectionTitle>
          <ul className="space-y-2.5">
            {rest.map((lesson) => (
              <li key={lesson.id}>
                <LessonCard lesson={lesson} />
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <section>
        <SectionTitle>{t('waiting')}</SectionTitle>
        {waiting.length === 0 ? (
          <p className="rounded-card border border-dashed border-line bg-white/60 p-5 text-center text-muted">{t('allClear')}</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {waiting.map((w) => (
              <li key={w.href}>
                <Link href={w.href} className="lift flex h-full items-start gap-3 rounded-card border border-line bg-white p-4">
                  <span className={join('grid size-10 shrink-0 place-items-center rounded-control font-heading text-lg font-bold', toneBox[w.tone])}>{w.count}</span>
                  <span className="min-w-0 flex-1">
                    <span className="block font-semibold">{w.label}</span>
                    {w.caption ? <span className="block truncate text-sm text-muted">{w.caption}</span> : null}
                  </span>
                  <Icon name="chevron" size={18} className="mt-1 shrink-0 text-muted rtl:-scale-x-100" />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

/** The lesson on now, or the next to come: booked, and not over by the clock. */
function nextOf(lessons: Lesson[]): Lesson | undefined {
  const now = Date.now();
  return lessons.find((l) => (l.status === 'scheduled' || l.status === 'in_progress') && Date.parse(l.ends_at.utc) > now);
}

const toneBox: Record<Tone, string> = {
  brand: 'bg-brand-soft text-brand-strong',
  attention: 'bg-attention-soft text-attention',
  danger: 'bg-danger-soft text-danger',
  info: 'bg-info-soft text-info',
  muted: 'bg-line/60 text-muted',
};
