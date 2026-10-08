import { useTranslations } from 'next-intl';
import { Icon } from '@/components/icons';
import type { ReportAnswer } from '@/lib/report-answers';

/** The answers to the academy's own questions, as small tiles: stars drawn, the rest as written by the API. */
export function ReportAnswers({ answers }: { answers: ReportAnswer[] }) {
  const t = useTranslations('common');
  if (answers.length === 0) return null;
  return (
    <ul className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
      {answers.map((a) => (
        <li key={a.key} className="min-w-0 rounded-control bg-brand-soft px-3 py-2">
          <p className="flex items-center gap-1 text-xs text-muted">
            {!a.family ? <Icon name="lock" size={12} /> : null}
            <span className="truncate">{a.label}</span>
          </p>
          {a.type === 'rating' && typeof a.value === 'number' ? (
            <p className="font-semibold text-attention" aria-label={a.text}>
              {'★'.repeat(a.value)}
              <span className="text-line">{'★'.repeat(Math.max(0, (a.max ?? 5) - a.value))}</span>
            </p>
          ) : (
            <p className="break-words font-semibold">{a.text}</p>
          )}
          {!a.family ? <span className="sr-only">{t('academyOnly')}</span> : null}
        </li>
      ))}
    </ul>
  );
}
