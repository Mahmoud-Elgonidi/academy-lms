import { useTranslations } from 'next-intl';
import { join } from '@/components/ui';
import { columnsOf, legendOf, squareClass } from '@/lib/hifz/hifz';
import type { HifzMapData } from '@/lib/hifz/types';

/**
 * The hifz map (the learner's, and read only on their teacher's page): the
 * counts, the mushaf's thirty parts as rows with each page a square in its
 * strength (the academy's colours), and the legend with how many of each.
 */
export function HifzMap({ map }: { map: HifzMapData }) {
  const t = useTranslations('hifz');
  const columns = columnsOf(map.juzs);
  const legend = legendOf(map);
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-3 gap-2">
        {(['memorized', 'learning', 'due'] as const).map((key) => (
          <p key={key} className="rounded-control bg-paper px-3 py-2.5">
            <span className="block font-heading text-2xl font-bold tabular-nums">{map.counts[key]}</span>
            <span className="block text-xs text-muted">{t(`counts.${key}`)}</span>
          </p>
        ))}
      </div>
      <div role="img" aria-label={t('mapLabel', { memorized: map.counts.memorized, total: map.pages_total })} className="appear space-y-[3px]">
        {map.juzs.map((juz) => (
          <div key={juz.juz} className="flex items-center gap-2">
            <span className="w-6 shrink-0 text-end text-[11px] font-semibold tabular-nums text-muted">{juz.juz}</span>
            <span className="grid min-w-0 flex-1 gap-[2px]" style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 0.8rem))` }}>
              {juz.pages.map((p) => (
                <span key={p.page} title={t('pageOf', { page: p.page, strength: map.strengths[p.strength] ?? p.strength })} className={join('block aspect-square rounded-[2px]', squareClass(p.strength))} />
              ))}
            </span>
          </div>
        ))}
      </div>
      <ul className="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-muted">
        {legend.map((item) => (
          <li key={item.strength} className="inline-flex items-center gap-1.5">
            <span aria-hidden="true" className={join('inline-block size-3 rounded-[2px]', squareClass(item.strength))} />
            {item.label}
            <span className="font-semibold tabular-nums text-ink">{item.count}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
