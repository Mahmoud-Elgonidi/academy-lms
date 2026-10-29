import { defineRouting } from 'next-intl/routing';

/** The portal speaks Arabic (Modern Standard Arabic, as on the panels and in the app) and English, always in the path. */
export const routing = defineRouting({
  locales: ['ar', 'en'],
  defaultLocale: 'ar',
  localePrefix: 'always',
});

export type Locale = (typeof routing.locales)[number];
