'use client';

import { useTranslations } from 'next-intl';
import { useTransition } from 'react';
import { chooseLearner } from '@/app/[locale]/family/actions';
import type { Learner } from '@/lib/family/types';

/** A guardian with more than one child picks whose pages they see; the choice stays for the next visits. */
export function LearnerSwitch({ learners, current }: { learners: Learner[]; current: string }) {
  const t = useTranslations('family');
  const [pending, start] = useTransition();
  if (learners.length < 2) return null;
  return (
    <label className="flex items-center gap-2 text-sm">
      <span className="sr-only">{t('switchLearner')}</span>
      <select
        value={current}
        disabled={pending}
        onChange={(e) => {
          const id = e.target.value;
          start(async () => {
            await chooseLearner(id);
          });
        }}
        className="min-h-10 rounded-control border border-line bg-white px-3 font-semibold"
      >
        {learners.map((l) => (
          <option key={l.id} value={l.id}>
            {l.name}
          </option>
        ))}
      </select>
    </label>
  );
}
