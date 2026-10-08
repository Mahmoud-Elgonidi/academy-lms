import { getTranslations } from 'next-intl/server';
import { HandIn } from '@/components/family/hand-in';
import { Handed } from '@/components/handed';
import { Icon } from '@/components/icons';
import { Badge, Card, Empty, Heading, Notice, type Tone } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { Homework } from '@/lib/family/types';

const tone: Record<string, Tone> = { assigned: 'info', missed: 'attention', submitted: 'brand', reviewed: 'muted' };

/**
 * The learner's homework, open first (as the API orders it): hand it in here
 * (words, a recording, a picture or PDF) until the teacher marks it, then the
 * grade and the teacher's words. A guardian may hand in for a child.
 */
export default async function HomeworkPage() {
  const [t, fam] = await Promise.all([getTranslations('family'), family()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const homework = await get<{ data: Homework[] }>('/v1/learning/homework', { query: { student_id: learner.id } });
  const kids = fam.stage === 'kids';
  const title = (await getTranslations(`nav.${fam.stage}`))('homework');

  return (
    <div>
      <Heading title={fam.stage === 'classic' && !learner.is_self ? `${title} · ${learner.name}` : title} />
      {kids && homework.data.some((h) => h.status === 'assigned') ? (
        <div className="mb-4">
          <Notice tone="brand">
            <span className="inline-flex items-center gap-2">
              <Icon name="star" size={18} fill="var(--color-reward)" className="text-reward" />
              {t('kidsHomeworkStar')}
            </span>
          </Notice>
        </div>
      ) : null}
      {homework.data.length === 0 ? (
        <Empty>{t('noHomework')}</Empty>
      ) : (
        <ul className="stagger space-y-4">
          {homework.data.map((h) => (
            <li key={h.id}>
              <Card as="article">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <h2 className="font-heading text-lg font-bold">{h.title}</h2>
                  <Badge tone={tone[h.status] ?? 'muted'}>{h.status_label}</Badge>
                </div>
                {h.instructions ? <p className="mt-1 whitespace-pre-line text-muted">{h.instructions}</p> : null}
                {h.due_at ? <p className="mt-1 text-sm text-muted">{t('dueBy', { when: h.due_at.label })}</p> : null}

                {h.submissions.length > 0 ? (
                  <section className="mt-4 space-y-2" aria-label={t('yourSubmission')}>
                    <p className="text-sm font-semibold">{t('yourSubmission')}</p>
                    {h.submissions.map((s) => (
                      <Handed key={s.id} submission={s} />
                    ))}
                  </section>
                ) : null}

                {h.status === 'reviewed' ? (
                  <section className="mt-4 rounded-control border border-brand-line bg-brand-soft p-4">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="font-semibold text-brand-strong">{t('teacherFeedback')}</p>
                      {h.grade ? (
                        <span className="inline-flex items-center gap-0.5" role="img" aria-label={t('grade', { g: h.grade })}>
                          {[1, 2, 3, 4, 5].map((n) => (
                            <Icon key={n} name="star" size={16} fill={n <= h.grade! ? 'var(--color-reward)' : 'none'} className={n <= h.grade! ? 'text-reward' : 'text-line'} />
                          ))}
                        </span>
                      ) : null}
                    </div>
                    {h.feedback ? <p className="mt-1 whitespace-pre-line">{h.feedback}</p> : null}
                  </section>
                ) : (
                  <div className="mt-4 border-t border-line pt-4">
                    <HandIn id={h.id} accepts={h.accepts} kids={kids} />
                  </div>
                )}
              </Card>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
