import type { Metadata } from 'next';
import localFont from 'next/font/local';
import { notFound } from 'next/navigation';
import { hasLocale, NextIntlClientProvider } from 'next-intl';
import type { CSSProperties, ReactNode } from 'react';
import { routing } from '@/i18n/routing';
import { TIMEZONE_COOKIE } from '@/lib/session';
import { branding, neutralOf } from '@/lib/viewer';
import '../globals.css';

// IBM Plex Sans Arabic (OFL), served with the page, so text looks the same before any academy font loads.
const plexArabic = localFont({
  src: [
    { path: '../../fonts/ibm-plex-sans-arabic-arabic-400-normal.woff2', weight: '400' },
    { path: '../../fonts/ibm-plex-sans-arabic-arabic-500-normal.woff2', weight: '500' },
    { path: '../../fonts/ibm-plex-sans-arabic-arabic-600-normal.woff2', weight: '600' },
    { path: '../../fonts/ibm-plex-sans-arabic-arabic-700-normal.woff2', weight: '700' },
  ],
  variable: '--font-plex-arabic',
  display: 'swap',
});
const plexLatin = localFont({
  src: [
    { path: '../../fonts/ibm-plex-sans-arabic-latin-400-normal.woff2', weight: '400' },
    { path: '../../fonts/ibm-plex-sans-arabic-latin-500-normal.woff2', weight: '500' },
    { path: '../../fonts/ibm-plex-sans-arabic-latin-600-normal.woff2', weight: '600' },
    { path: '../../fonts/ibm-plex-sans-arabic-latin-700-normal.woff2', weight: '700' },
  ],
  variable: '--font-plex-latin',
  display: 'swap',
});

type Props = { children: ReactNode; params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const look = await branding();
  return { title: locale === 'ar' ? look.name.ar : look.name.en };
}

// The page writes the viewer's zone for the next requests (sent to the API as X-Timezone).
const zoneScript = `try{var z=Intl.DateTimeFormat().resolvedOptions().timeZone;if(z&&document.cookie.indexOf('${TIMEZONE_COOKIE}='+encodeURIComponent(z))<0){document.cookie='${TIMEZONE_COOKIE}='+encodeURIComponent(z)+';path=/;max-age=31536000;samesite=lax'}}catch(e){}`;

export default async function LocaleLayout({ children, params }: Props) {
  const { locale } = await params;
  if (!hasLocale(routing.locales, locale)) notFound();
  const look = await branding();
  const { tokens, radii } = look;
  const plex = (font: string) => font === 'IBM Plex Sans Arabic';
  const style = {
    '--brand': tokens.primary,
    '--attention': tokens.attention,
    '--danger': tokens.danger,
    '--info': tokens.info,
    '--neutral': neutralOf(tokens.neutral),
    '--radius-sm': radii.sm,
    '--radius-md': radii.md,
    '--radius-lg': radii.lg,
    '--radius-xl': radii.xl,
    '--font-head': plex(tokens.heading_font) ? 'var(--font-plex-latin)' : `'${tokens.heading_font}'`,
    '--font-body': plex(tokens.body_font) ? 'var(--font-plex-latin)' : `'${tokens.body_font}'`,
  } as CSSProperties;

  return (
    <html lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'} className={`${plexArabic.variable} ${plexLatin.variable}`} style={style}>
      <head>
        <script dangerouslySetInnerHTML={{ __html: zoneScript }} />
        {look.fonts_url && !(plex(tokens.heading_font) && plex(tokens.body_font)) ? (
          // The academy's own fonts, chosen in its branding (as on the panels).
          <link rel="stylesheet" href={look.fonts_url} />
        ) : null}
      </head>
      <body className="font-sans text-[15px] leading-relaxed">
        <NextIntlClientProvider>{children}</NextIntlClientProvider>
      </body>
    </html>
  );
}
