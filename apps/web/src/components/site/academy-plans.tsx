'use client';

import { useState, type CSSProperties } from 'react';
import { useTranslations } from 'next-intl';
import { Icon } from './icons';

/** A plan as the page shows it: every word made on the server, its price per region. */
export type ShownPlan = {
  id: string;
  name: string;
  facts: string[];
  prices: Record<string, { price: string; perLesson: string | null }>;
};

/**
 * The academy's plans with their prices in one region at a time: the
 * default region first, the others a tap away. Prices are written on the
 * server; this only chooses which to show.
 */
export function AcademyPlans({ plans, regions, start }: {
  plans: ShownPlan[];
  regions: { id: string; name: string }[];
  start: string | null;
}) {
  const t = useTranslations('site.pricing');
  const [region, setRegion] = useState(start ?? regions[0]?.id ?? '');

  return (
    <>
      {regions.length > 1 ? (
        <div className="ls-regions" data-reveal="">
          <span id="ls-region-label">{t('region')}</span>
          <div role="radiogroup" aria-labelledby="ls-region-label">
            {regions.map((r) => (
              <button key={r.id} type="button" role="radio" aria-checked={region === r.id} onClick={() => setRegion(r.id)}>
                {r.name}
              </button>
            ))}
          </div>
        </div>
      ) : null}
      <div className="ls-plans">
        {plans.map((plan, i) => {
          const here = plan.prices[region];
          return (
            <article key={plan.id} className="ls-card ls-plan" data-reveal="" style={{ '--i': i } as CSSProperties}>
              <h3>{plan.name}</h3>
              <div className="ls-price" aria-live="polite">
                {here ? (
                  <>
                    <b key={`${plan.id}-${region}`} className="ls-price-value">{here.price}</b>
                    <span>{t('perBlock')}</span>
                  </>
                ) : (
                  <b>{t('ask')}</b>
                )}
              </div>
              {here?.perLesson ? <p className="ls-tagline">{t('perLesson', { price: here.perLesson })}</p> : null}
              <ul>
                {plan.facts.map((fact) => (
                  <li key={fact}><Icon name="check" /><span>{fact}</span></li>
                ))}
              </ul>
              <a className="ls-btn ls-btn-dark" href="#join">{t('start')}</a>
            </article>
          );
        })}
      </div>
    </>
  );
}

/** The academy's code for the app, with a button that copies it. */
export function AcademyCode({ code, label }: { code: string; label: string }) {
  const t = useTranslations('site.app');
  const [copied, setCopied] = useState(false);
  return (
    <div className="ls-code">
      <span>{label}</span>
      <b dir="ltr">{code}</b>
      <button
        type="button"
        onClick={() => {
          void navigator.clipboard?.writeText(code).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1800);
          }, () => undefined);
        }}
      >
        <Icon name={copied ? 'check' : 'copy'} />
        <span aria-live="polite">{copied ? t('copied') : t('copy')}</span>
      </button>
    </div>
  );
}
