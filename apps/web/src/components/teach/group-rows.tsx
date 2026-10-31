'use client';

import { useTranslations } from 'next-intl';
import { Icon } from '@/components/icons';
import { Badge, Input, Textarea, join } from '@/components/ui';
import type { GroupRow, RowProblem } from '@/lib/teach/group-report';

/**
 * A group report's rows, one compact line per student: who came (as the
 * lesson recorded it) with focus, overall and a note for them and their
 * family, or who was absent with the note for their family. A row with
 * something missing, or that the API refused, is marked and says what.
 */
export function GroupRows({
  rows,
  onChange,
  problems,
  errors,
}: {
  rows: GroupRow[];
  onChange: (studentId: string, change: Partial<GroupRow>) => void;
  /** What each row is missing, once the teacher tried to send (by student). */
  problems: Record<string, RowProblem[]>;
  /** The API's words for a row, by student and field. */
  errors: Record<string, Record<string, string>>;
}) {
  const t = useTranslations('teach');
  return (
    <ol className="divide-y divide-line">
      {rows.map((row) => {
        const missing = problems[row.studentId] ?? [];
        const said = errors[row.studentId] ?? {};
        const wrong = missing.length > 0 || Object.keys(said).length > 0;
        const named = (what: string) => t('forStudent', { what, name: row.name });
        return (
          <li
            key={row.studentId}
            data-student={row.studentId}
            data-kind={row.kind}
            data-invalid={wrong || undefined}
            className={join('py-3.5 first:pt-0 last:pb-0', wrong && '-mx-3 rounded-control bg-danger-soft/60 px-3 first:pt-3 last:pb-3')}
          >
            <div className="flex flex-wrap items-center gap-2">
              <span aria-hidden="true" className="grid size-8 shrink-0 place-items-center rounded-full bg-group-soft font-heading text-sm font-bold text-group-strong">
                {Array.from(row.name.trim())[0] ?? '·'}
              </span>
              <span className="min-w-0 flex-1 truncate font-semibold">{row.name}</span>
              <Badge tone={row.kind === 'lesson' ? 'brand' : 'attention'}>
                {row.kind === 'lesson' ? <Icon name="check" size={12} /> : null}
                {row.kind === 'lesson' ? t('attended') : t('absent')}
              </Badge>
            </div>

            {row.locked ? (
              <p className="mt-2 flex items-center gap-1.5 text-sm text-muted">
                <Icon name="lock" size={14} />
                {t('rowReviewed')}
              </p>
            ) : row.kind === 'lesson' ? (
              <div className="mt-2.5 space-y-2.5">
                <div className="flex flex-wrap gap-x-5 gap-y-2">
                  <MiniScale label={t('rowFocus')} name={named(t('engagement'))} value={row.engagement} onChange={(engagement) => onChange(row.studentId, { engagement })} />
                  <MiniScale label={t('rowOverall')} name={named(t('overall'))} value={row.overall} onChange={(overall) => onChange(row.studentId, { overall })} />
                </div>
                {missing.includes('ratings') ? <p className="text-sm text-danger">{t('rowRatingsMissing')}</p> : null}
                {said.engagement ? <p className="text-sm text-danger">{said.engagement}</p> : null}
                {said.overall ? <p className="text-sm text-danger">{said.overall}</p> : null}
                <Input
                  value={row.note}
                  onChange={(e) => onChange(row.studentId, { note: e.target.value })}
                  maxLength={3000}
                  placeholder={t('rowNote')}
                  aria-label={named(t('rowNote'))}
                  className="min-h-10 py-2 text-sm"
                />
                {said.notes_for_student ? <p className="text-sm text-danger">{said.notes_for_student}</p> : null}
              </div>
            ) : (
              <div className="mt-2.5">
                <Textarea
                  value={row.absenceNote}
                  onChange={(e) => onChange(row.studentId, { absenceNote: e.target.value })}
                  rows={2}
                  maxLength={1000}
                  placeholder={t('rowAbsenceNote')}
                  aria-label={named(t('rowAbsenceNote'))}
                  className="text-sm"
                />
                {missing.includes('absence_note') ? <p className="mt-1 text-sm text-danger">{t('rowAbsenceMissing')}</p> : null}
                {said.absence_note ? <p className="mt-1 text-sm text-danger">{said.absence_note}</p> : null}
              </div>
            )}
          </li>
        );
      })}
    </ol>
  );
}

/** 1 to 5 in a small row (a radio group named for the student, so each row reads on its own). */
function MiniScale({ label, name, value, onChange }: { label: string; name: string; value: number | null; onChange: (v: number) => void }) {
  const t = useTranslations('teach');
  return (
    <div className="flex items-center gap-2">
      <span className="w-16 shrink-0 text-xs font-semibold text-muted">{label}</span>
      <div role="radiogroup" aria-label={name} className="flex gap-1">
        {[1, 2, 3, 4, 5].map((n) => (
          <button
            key={n}
            type="button"
            role="radio"
            aria-checked={value === n}
            aria-label={t('rate', { n })}
            onClick={() => onChange(n)}
            className={join(
              'grid size-8 place-items-center rounded-control border text-xs font-semibold tabular-nums transition-colors',
              value !== null && n <= value ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line bg-white text-muted hover:border-brand-line',
              value === n && 'bg-brand text-white',
            )}
          >
            {n}
          </button>
        ))}
      </div>
    </div>
  );
}
