import { getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { Badge, buttonLook, Empty, Heading, type Tone } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import { trialBadge, type FamilyTrial } from '@/lib/site/report';

const TONES: Record<ReturnType<typeof trialBadge>, Tone> = {
  report: 'brand',
  booked: 'info',
  waiting: 'muted',
  missed: 'attention',
  teacherMissed: 'muted',
  cancelled: 'muted',
  academyCancelled: 'muted',
};

/**
 * The learner's free trials (docs/architecture/02, section 27, J2), latest
 * first: the subject, when, with whom and how it went, the report once the
 * teacher wrote it, and (the classic look) one more trial it allows: the
 * second with another teacher, or a new time after one that did not take
 * place. A student's own look reads their reports alone.
 */
export default async function FamilyTrials() {
  const fam = await family();
  const { current, stage } = fam;
  const [t, nav] = await Promise.all([getTranslations('site.report.family'), getTranslations(`nav.${stage}`)]);
  const list = current ? await get<{ data: FamilyTrial[] }>('/v1/family/trials', { query: { learner_id: current.id } }) : { data: [] };
  const own = current?.is_self ?? false;
  const classic = seesMoney(stage);

  return (
    <div>
      <Heading title={nav('trials')} hint={own ? t('leadStudent') : t('lead', { name: current?.name ?? '' })} />
      {list.data.length === 0 ? (
        <Empty>{own ? t('emptyStudent') : t('empty', { name: current?.name ?? '' })}</Empty>
      ) : (
        <ul className="lms-stagger grid gap-3">
          {list.data.map((trial) => {
            const badge = trialBadge(trial);
            const next = classic && trial.next?.available ? trial.next : null;
            return (
              <li key={trial.id} className="rounded-card border border-line bg-white p-4" data-trial={trial.id}>
                <div className="flex items-start gap-3">
                  <span className="grid size-11 flex-none place-items-center rounded-control bg-brand-soft text-brand" aria-hidden="true">
                    <Icon name={trial.report_ready ? 'report' : 'calendar'} />
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="text-lg font-bold">{trial.subject.name}</h2>
                      {trial.kind === 'second' || trial.kind === 'rebook' ? <Badge tone="info">{t(`kind.${trial.kind}`)}</Badge> : null}
                    </div>
                    <p className="mt-0.5 text-sm text-muted">
                      {trial.starts_at?.label}{trial.teacher ? <> · <bdi>{trial.teacher}</bdi></> : null}
                    </p>
                    <p className="mt-2"><Badge tone={TONES[badge]}>{t(`badge.${badge}`)}</Badge></p>
                  </div>
                </div>
                {trial.report_ready || next ? (
                  <div className="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                    {trial.report_ready ? (
                      <Link href={`/family/trials/${trial.id}`} className={buttonLook('primary')}>{t('read')}<Icon name="chevron" size={18} className="rtl:-scale-x-100" /></Link>
                    ) : null}
                    {next ? (
                      <Link href={`/family/trials/${trial.id}${next.kind === 'second' ? '#next' : ''}`} className={buttonLook('quiet')}>
                        {next.kind === 'second' ? t('nextSecond') : t('nextRebook')}
                      </Link>
                    ) : null}
                  </div>
                ) : null}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
