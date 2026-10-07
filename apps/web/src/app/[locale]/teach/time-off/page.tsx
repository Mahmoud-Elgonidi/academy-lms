import { getTranslations } from 'next-intl/server';
import { TimeOffForm, WithdrawButton } from '@/components/teach/time-off-form';
import { Badge, Card, Empty, Heading, SectionTitle, type Tone } from '@/components/ui';
import { get } from '@/lib/api/server';
import { zoneName } from '@/lib/format';
import type { TimeOffList } from '@/lib/teach/types';
import { me } from '@/lib/viewer';

const tone: Record<string, Tone> = { pending: 'attention', approved: 'brand', rejected: 'danger', withdrawn: 'muted' };

/** Asking for time off on the teacher's own clock, the requests and their answers, and the academy's holidays. */
export default async function TimeOff({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const [t, nav, person, list] = await Promise.all([getTranslations('teach'), getTranslations('nav'), me(), get<TimeOffList>('/v1/teacher/time-off')]);
  return (
    <div>
      <Heading title={nav('timeOff')} hint={t('timeOffLead', { zone: zoneName(person.display_timezone, locale) })} />
      <Card>
        <h2 className="mb-4 font-semibold">{t('requestTimeOff')}</h2>
        <TimeOffForm />
      </Card>

      <SectionTitle>{t('yourRequests')}</SectionTitle>
      {list.data.length === 0 ? (
        <Empty>{t('noTimeOff')}</Empty>
      ) : (
        <ul className="stagger space-y-2.5">
          {list.data.map((r) => (
            <li key={r.id}>
              <Card as="article" className="flex flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-semibold">{r.period}</p>
                    <Badge tone={tone[r.status] ?? 'muted'}>{r.status_label}</Badge>
                  </div>
                  <p className="text-sm text-muted">
                    {r.reason_label}
                    {r.note ? ` · ${r.note}` : ''}
                  </p>
                  {r.decision_note ? <p className="mt-1 text-sm">{r.decision_note}</p> : null}
                  {r.cover && r.cover.total > 0 ? <p className="mt-1 text-sm text-muted">{t('coverState', { covered: r.cover.covered, total: r.cover.total })}</p> : null}
                </div>
                {r.can_withdraw ? <WithdrawButton id={r.id} /> : null}
              </Card>
            </li>
          ))}
        </ul>
      )}

      {list.holidays.length > 0 ? (
        <>
          <SectionTitle>{t('academyHolidays')}</SectionTitle>
          <ul className="space-y-2">
            {list.holidays.map((h) => (
              <li key={`${h.name}-${h.period}`} className="flex flex-wrap justify-between gap-2 rounded-card border border-line bg-white px-4 py-3">
                <span className="font-semibold">{h.name}</span>
                <span className="text-muted">{h.period}</span>
              </li>
            ))}
          </ul>
        </>
      ) : null}
    </div>
  );
}
