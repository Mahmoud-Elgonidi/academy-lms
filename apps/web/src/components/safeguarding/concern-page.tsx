import { getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { Badge, Empty, Heading, Notice } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { ConcernOptions, MyConcern } from '@/lib/safeguarding/types';
import { RaiseConcern } from './raise-concern';

/**
 * "Tell us" (families, every look) and "Raise a concern" (teachers): what to
 * do first when someone is in danger, the concern itself, and the ones this
 * person raised with where each stands and the academy's reply. Only the
 * academy's safeguarding leads read them. Live through LiveRefresh
 * (concerns.changed).
 */
export async function ConcernPage({ side, learnerId }: { side: 'family' | 'teacher'; learnerId?: string }) {
  const query = learnerId ? { learner_id: learnerId } : undefined;
  const [t, options, mine] = await Promise.all([
    getTranslations('safeguarding'),
    get<{ data: ConcernOptions }>('/v1/safeguarding/new', { query }),
    get<{ data: MyConcern[] }>('/v1/safeguarding/concerns', { query }),
  ]);
  const look = side === 'teacher' ? 'teacher' : options.data.look;
  return (
    <div>
      <Heading title={t(`title.${look}`)} hint={t(`lead.${look}`)} />
      <div className="space-y-5">
        <Notice tone="danger">
          <span className="flex items-start gap-2 font-semibold">
            <Icon name="alert" size={20} className="mt-0.5 shrink-0" />
            {options.data.danger}
          </span>
        </Notice>
        <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
          <RaiseConcern options={options.data} />
          <section>
            <h2 className="mb-2 font-heading font-semibold">{t('mine')}</h2>
            {mine.data.length === 0 ? (
              <Empty>{t('none')}</Empty>
            ) : (
              <ul className="space-y-2">
                {mine.data.map((c) => (
                  <li key={c.id} className="space-y-1 rounded-card border border-line bg-white p-3 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <strong>{c.category.label}</strong>
                      <Badge tone={c.status.key === 'closed' ? 'muted' : c.status.key === 'open' ? 'brand' : 'attention'}>{c.status.label}</Badge>
                    </div>
                    <p className="text-xs text-muted">
                      {t('number', { number: c.number })} · {c.created_at?.label}
                    </p>
                    {c.text ? <p className="line-clamp-3 whitespace-pre-line text-ink">{c.text}</p> : null}
                    {c.reply ? (
                      <div className="rounded-control bg-paper p-2">
                        <p className="text-xs text-muted">{t('reply')}</p>
                        <p className="whitespace-pre-line">{c.reply}</p>
                      </div>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      </div>
    </div>
  );
}
