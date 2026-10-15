import { getTranslations } from 'next-intl/server';
import type { ReactNode } from 'react';
import { HifzMap } from '@/components/hifz/map';
import { Icon } from '@/components/icons';
import { HifzClearForm, HifzMarkForm } from '@/components/teach/hifz-forms';
import { Badge, Card } from '@/components/ui';
import { gradeTone } from '@/lib/hifz/hifz';
import type { TeacherHifz } from '@/lib/hifz/types';

/**
 * A student's hifz map on their teacher's page: the map (read only), the
 * last two weeks' reviews (the page, how it went, where it came from, when),
 * saying what the student already knows, and taking pages marked by mistake
 * off. Folded away while the map has no pages (a course of another subject
 * shows it closed).
 */
export async function TeacherHifzSection({ enrollment, hifz }: { enrollment: string; hifz: TeacherHifz }) {
  const t = await getTranslations('hifz');
  const empty = hifz.counts.memorized + hifz.counts.learning === 0;
  const body: ReactNode = (
    <div className="space-y-4">
      {empty ? null : (
        <Card>
          <HifzMap map={hifz} />
        </Card>
      )}
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h3 className="font-semibold">{t('teacher.tell')}</h3>
          <p className="mb-4 mt-1 text-sm text-muted">{t('teacher.tellLead')}</p>
          <HifzMarkForm enrollment={enrollment} juzs={hifz.juzs} surahs={hifz.surahs} marks={hifz.marks} />
        </Card>
        <div className="space-y-4">
          <Card>
            <h3 className="font-semibold">{t('teacher.recent')}</h3>
            {hifz.recent.length === 0 ? (
              <p className="mt-2 text-sm text-muted">{t('teacher.noRecent')}</p>
            ) : (
              <ul className="mt-2 max-h-96 divide-y divide-line overflow-y-auto">
                {hifz.recent.map((r, i) => (
                  <li key={`${r.page}-${r.source}-${r.at?.utc ?? i}`} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5">
                    <span className="font-semibold tabular-nums">{t('page', { page: r.page })}</span>
                    <Badge tone={gradeTone(r.grade)}>{r.grade_label}</Badge>
                    <span className="text-sm text-muted">{r.source_label}</span>
                    {r.at ? <span className="ms-auto text-sm text-muted">{r.at.label}</span> : null}
                  </li>
                ))}
              </ul>
            )}
          </Card>
          {empty ? null : (
            <Card>
              <h3 className="font-semibold">{t('teacher.clear')}</h3>
              <p className="mb-3 mt-1 text-sm text-muted">{t('teacher.clearLead')}</p>
              <HifzClearForm enrollment={enrollment} />
            </Card>
          )}
        </div>
      </div>
    </div>
  );

  if (empty) {
    return (
      <details className="group mt-8 rounded-card border border-line bg-white" aria-labelledby="hifz">
        <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 [&::-webkit-details-marker]:hidden">
          <span>
            <span id="hifz" className="block text-lg font-bold">
              {t('teacher.title')}
            </span>
            <span className="block text-sm text-muted">{t('teacher.emptyLead')}</span>
          </span>
          <Icon name="chevron" size={18} className="shrink-0 text-muted transition-transform group-open:rotate-90 rtl:-scale-x-100 rtl:group-open:-rotate-90" />
        </summary>
        <div className="border-t border-line p-4">{body}</div>
      </details>
    );
  }
  return (
    <section className="mt-8" aria-labelledby="hifz">
      <h2 id="hifz" className="mb-3 text-xl font-bold">
        {t('teacher.title')}
      </h2>
      {body}
    </section>
  );
}
