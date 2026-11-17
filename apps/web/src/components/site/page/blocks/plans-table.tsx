'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import type { ShownPlan } from '../../academy-plans';

/**
 * The plans side by side (good for two to four): a column each, the same
 * facts on each row, prices in one region at a time. Every word is made on
 * the server; this only chooses the region.
 */
export function PlansTable({ plans, rows, regions, start, highlight, startLabel, href }: {
  plans: ShownPlan[];
  rows: { label: string; cells: string[] }[];
  regions: { id: string; name: string }[];
  start: string | null;
  highlight: { id: string; label: string } | null;
  startLabel: string;
  /** Where "start" leads (the form), or null when the page has none. */
  href: string | null;
}) {
  const t = useTranslations('site.pricing');
  const [region, setRegion] = useState(start ?? regions[0]?.id ?? '');
  return (
    <div className="sp-table-wrap" data-reveal="">
      {regions.length > 1 ? (
        <div className="sp-regions">
          <span id="sp-region-label">{t('region')}</span>
          <div role="radiogroup" aria-labelledby="sp-region-label">
            {regions.map((r) => (
              <button key={r.id} type="button" role="radio" aria-checked={region === r.id} onClick={() => setRegion(r.id)}>
                {r.name}
              </button>
            ))}
          </div>
        </div>
      ) : null}
      <div className="sp-table-scroll">
        <table className="sp-table">
          <thead>
            <tr>
              <td />
              {plans.map((plan) => (
                <th key={plan.id} scope="col" data-highlight={highlight?.id === plan.id ? '' : undefined}>
                  {highlight?.id === plan.id ? <span className="sp-mark">{highlight.label}</span> : null}
                  {plan.name}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            <tr className="sp-price-row">
              <th scope="row">{t('perBlock')}</th>
              {plans.map((plan) => {
                const here = plan.prices[region];
                return (
                  <td key={plan.id} data-highlight={highlight?.id === plan.id ? '' : undefined} aria-live="polite">
                    <b>{here ? here.price : t('ask')}</b>
                    {here?.perLesson ? <small>{t('perLesson', { price: here.perLesson })}</small> : null}
                  </td>
                );
              })}
            </tr>
            {rows.map((row) => (
              <tr key={row.label}>
                <th scope="row">{row.label}</th>
                {row.cells.map((cell, i) => (
                  <td key={plans[i]?.id ?? i} data-highlight={highlight?.id === plans[i]?.id ? '' : undefined}>{cell || '—'}</td>
                ))}
              </tr>
            ))}
            {href ? (
              <tr className="sp-start-row">
                <td />
                {plans.map((plan) => (
                  <td key={plan.id} data-highlight={highlight?.id === plan.id ? '' : undefined}>
                    <a className={`sp-btn ${highlight?.id === plan.id ? 'sp-btn-primary' : 'sp-btn-outline'}`} href={href}>{startLabel}</a>
                  </td>
                ))}
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  );
}
