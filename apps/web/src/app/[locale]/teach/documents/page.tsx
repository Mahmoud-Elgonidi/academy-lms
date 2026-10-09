import { getLocale, getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { SendDocument } from '@/components/teach/send-document';
import { Badge, Empty, Heading, Notice } from '@/components/ui';
import { get } from '@/lib/api/server';
import { dayLabel } from '@/lib/format';
import { documentTone, type MyDocuments } from '@/lib/safeguarding/documents';

/**
 * "My documents": the teacher's vetting documents (a criminal record check
 * above all) and where each stands, whether the academy asks for a valid
 * check before they teach under-18s, and sending a new one. Live through
 * LiveRefresh (checks.changed).
 */
export default async function Documents() {
  const [t, mine, locale] = await Promise.all([getTranslations('documents'), get<{ data: MyDocuments }>('/v1/teacher/checks'), getLocale()]);
  const d = mine.data;
  const day = (value: string) => dayLabel(value, locale, { day: 'numeric', month: 'short', year: 'numeric' });
  return (
    <div>
      <Heading title={t('title')} hint={t('lead')} />
      <div className="space-y-5">
        {d.required && d.state_label ? (
          <Notice tone={documentTone(d.state)}>
            <span className="flex items-start gap-2">
              <Icon name={d.state === 'verified' ? 'shield' : 'alert'} size={20} className="mt-0.5 shrink-0" />
              {d.state_label}
            </span>
          </Notice>
        ) : null}
        <SendDocument kinds={d.kinds} />
        <section>
          <h2 className="mb-2 font-heading font-semibold">{t('list')}</h2>
          {d.checks.length === 0 ? (
            <Empty>{t('none')}</Empty>
          ) : (
            <ul className="space-y-2">
              {d.checks.map((c) => (
                <li key={c.id} className="space-y-1 rounded-card border border-line bg-white p-3 text-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <strong>{c.kind.label}</strong>
                    <Badge tone={c.status.key === 'verified' ? 'brand' : c.status.key === 'pending' ? 'attention' : 'danger'}>{c.status.label}</Badge>
                  </div>
                  <p className="text-xs text-muted">
                    {[c.reference, c.issued_on ? t('issued', { day: day(c.issued_on) }) : null, c.expires_on ? t('until', { day: day(c.expires_on) }) : c.status.key === 'verified' ? t('noEnd') : null]
                      .filter(Boolean)
                      .join(' · ')}
                  </p>
                  {c.reason ? <p className="rounded-control bg-danger-soft p-2 text-danger">{c.reason}</p> : null}
                  {c.file ? (
                    <a href={`/bff/checks/${c.id}/file`} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-brand-strong underline">
                      <Icon name="file" size={16} />
                      {c.file.name}
                    </a>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}
