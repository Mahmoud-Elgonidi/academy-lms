import { getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import type { Submission } from '@/lib/teach/types';

/**
 * One hand-in, as the teacher marking it and the family who sent it both see
 * it: the words, and the file through the portal (a recording plays, a picture
 * shows, anything else downloads).
 */
export async function Handed({ submission }: { submission: Submission }) {
  const t = await getTranslations('common');
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
        <img src={src} alt={file.name} className="mt-2 max-h-96 max-w-full rounded-control border border-line" />
      ) : file ? (
        <a href={src} className="mt-2 inline-flex items-center gap-2 font-semibold text-brand-strong underline underline-offset-4">
          <Icon name="file" size={18} />
          {file.name}
        </a>
      ) : null}
    </div>
  );
}
