import { hasLocale } from 'next-intl';
import { getRequestConfig } from 'next-intl/server';
import { overlaid, type Audience, type MessageTree } from '@/lib/audience';
import { academyAudience } from '@/lib/audience-server';
import { routing, type Locale } from './routing';

// One merged set per language and audience for the process (the files never change while it runs).
const merged = new Map<string, MessageTree>();

/**
 * The words for a language: an academy for adults reads them with
 * `messages/adults/{locale}.json` (only the keys that differ: a trainee, a
 * trainer, a session, the learner addressed as "you") laid over the base;
 * families read the base as it is (lib/audience.ts).
 */
async function messagesFor(locale: Locale, audience: Audience): Promise<MessageTree> {
  const key = `${locale}:${audience}`;
  const kept = merged.get(key);
  if (kept) return kept;
  const base = (await import(`../../messages/${locale}.json`)).default as MessageTree;
  const words = audience === 'adults' ? overlaid(base, (await import(`../../messages/adults/${locale}.json`)).default as MessageTree) : base;
  merged.set(key, words);
  return words;
}

export default getRequestConfig(async ({ requestLocale }) => {
  const requested = await requestLocale;
  const locale = hasLocale(routing.locales, requested) ? requested : routing.defaultLocale;

  return {
    locale,
    messages: await messagesFor(locale, await academyAudience(locale)),
    // Times come from the API already written for the viewer ({label}); nothing is converted here.
    timeZone: 'UTC',
  };
});
