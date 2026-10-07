import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { MarkForm } from '@/components/teach/mark-form';
import { Badge, Card, Empty, Heading, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { Homework, Submission } from '@/lib/teach/types';

/** Homework handed in and waiting for a mark (or, on the other tab, marked): what was handed in, played or shown here. */
export default async function Marking({ searchParams }: { searchParams: Promise<{ status?: string }> }) {
  const { status: asked } = await searchParams;
  const status = asked === 'reviewed' ? 'reviewed' : 'submitted';
  const [t, nav, homework] = await Promise.all([
    getTranslations('teach'),
    getTranslations('nav'),
    get<{ data: Homework[] }>('/v1/teacher/homework', { query: { status } }),
  ]);

  const tab = (value: string, label: string) => (
    <Link
      href={value === 'submitted' ? '/teach/marking' : `/teach/marking?status=${value}`}
      aria-current={status === value ? 'page' : undefined}
      className={join('min-h-10 rounded-control px-4 py-2 text-sm font-semibold', status === value ? 'bg-white text-brand-strong shadow-sm' : 'text-muted hover:text-ink')}
    >
      {label}
    </Link>
  );

  return (
    <div>
      <Heading title={nav('marking')} />
      <nav className="mb-5 inline-flex gap-1 rounded-control bg-line/50 p-1">
        {tab('submitted', t('markWaiting'))}
        {tab('reviewed', t('markDone'))}
      </nav>
      {homework.data.length === 0 ? (
        <Empty>{status === 'submitted' ? t('noHomeworkToMark') : t('noHomeworkMarked')}</Empty>
      ) : (
        <ul className="stagger space-y-4">
          {homework.data.map((h) => (
            <li key={h.id}>
              <Card as="article">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-semibold">{h.student?.name}</p>
                  <Badge tone={h.status === 'reviewed' ? 'brand' : 'info'}>{h.status_label}</Badge>
                </div>
                <p className="mt-1">{h.title}</p>
                {h.instructions ? <p className="mt-1 whitespace-pre-line text-sm text-muted">{h.instructions}</p> : null}
                {h.due_at ? <p className="mt-1 text-xs text-muted">{t('dueAt', { when: h.due_at.label })}</p> : null}
                <div className="mt-4 space-y-3">
                  {h.submissions.length === 0 ? <p className="text-sm text-muted">{t('noAnswer')}</p> : h.submissions.map((s) => <Handed key={s.id} submission={s} />)}
                </div>
                <div className="mt-4 border-t border-line pt-4">
                  {h.status === 'reviewed' ? (
                    <div>
                      {h.grade ? <Badge tone="brand">{t('rate', { n: h.grade })}</Badge> : null}
                      {h.feedback ? <p className="mt-2 whitespace-pre-line">{h.feedback}</p> : null}
                    </div>
                  ) : (
                    <MarkForm id={h.id} />
                  )}
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/** One hand-in: the words, and the file (a recording plays, a picture shows, anything else downloads). */
async function Handed({ submission }: { submission: Submission }) {
  const t = await getTranslations('teach');
  const file = submission.file;
  const src = `/bff/submissions/${submission.id}/file`;
  return (
    <div className="rounded-control bg-paper p-3">
      {submission.submitted_at ? <p className="mb-1 text-xs text-muted">{t('handedInAt', { when: submission.submitted_at.label })}</p> : null}
      {submission.body ? <p className="whitespace-pre-line">{submission.body}</p> : null}
      {file?.is_audio ? (
        <audio controls preload="metadata" src={src} className="mt-2 w-full">
          <a href={src}>{t('downloadFile')}</a>
        </audio>
      ) : file?.mime.startsWith('image/') ? (
        // eslint-disable-next-line @next/next/no-img-element -- a picture the student handed in, through the portal
        <img src={src} alt={file.name} className="mt-2 max-h-96 rounded-control border border-line" />
      ) : file ? (
        <a href={src} className="mt-2 inline-flex items-center gap-2 font-semibold text-brand-strong underline underline-offset-4">
          <Icon name="file" size={18} />
          {file.name}
        </a>
      ) : null}
    </div>
  );
}
