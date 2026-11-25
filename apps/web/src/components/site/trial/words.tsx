'use client';

import { createContext, useContext, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { wordPath } from './words-path';

/**
 * Whose words the trial flow speaks (site-builder.md, 9.2): a page for adults
 * (an academy that trains professionals) says its steps, hints, buttons and
 * confirmations in training words (`site.adults.trial.*`, `site.adults.join.*`:
 * a session, a trainer, an area), and every key it does not set falls back to
 * the usual `site.trial.*` / `site.join.*`. The pick and lesson pages reached
 * by link take the provider from the published page too (`speaksToAdults`);
 * without it (a families page) the usual words stand.
 */
const AdultWords = createContext(false);

export function TrialWords({ adults, children }: { adults: boolean; children: ReactNode }) {
  return <AdultWords.Provider value={adults}>{children}</AdultWords.Provider>;
}

type Values = Record<string, string | number>;
type RichValues = Record<string, string | number | ((chunks: ReactNode) => ReactNode)>;

export type TrialSay = {
  (key: string, values?: Values): string;
  rich(key: string, values?: RichValues): ReactNode;
};

/** The words under `site.<area>.<namespace>`, the adults' own where they have one. */
function useWords(area: 'trial' | 'join', namespace?: string): TrialSay {
  const adults = useContext(AdultWords);
  const t = useTranslations('site');
  const path = (key: string) => wordPath((k) => t.has(k), adults, area, namespace ? `${namespace}.${key}` : key);
  return Object.assign((key: string, values?: Values) => t(path(key), values), {
    rich: (key: string, values?: RichValues) => t.rich(path(key), values as never),
  });
}

/** `site.trial.<namespace>`, in the page's words. */
export function useTrialWords(namespace?: string): TrialSay {
  return useWords('trial', namespace);
}

/** `site.join.<namespace>`, in the page's words. */
export function useJoinWords(namespace?: string): TrialSay {
  return useWords('join', namespace);
}
