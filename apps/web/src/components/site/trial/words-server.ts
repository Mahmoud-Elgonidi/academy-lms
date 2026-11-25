import { getTranslations } from 'next-intl/server';
import { wordPath } from './words-path';

type Values = Record<string, string | number>;

/** `site.trial.<namespace>` for a server component, in the page's words (the adults' own where they have one). */
export async function trialWordsOnServer(adults: boolean, namespace?: string): Promise<(key: string, values?: Values) => string> {
  const t = await getTranslations('site');
  return (key, values) => t(wordPath((k) => t.has(k), adults, 'trial', namespace ? `${namespace}.${key}` : key), values);
}
