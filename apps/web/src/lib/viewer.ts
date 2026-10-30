import 'server-only';
import { headers } from 'next/headers';
import { cache } from 'react';
import { call, get } from './api/server';
import { timeFormatOf, type TimeFormat } from './time';

export type Branding = {
  name: { ar: string; en: string };
  /** neutral is a palette's name (slate, gray, zinc, neutral, stone), as on the panels. */
  tokens: { primary: string; attention: string; danger: string; info: string; neutral: string; heading_font: string; body_font: string; radius: string };
  radii: { sm: string; md: string; lg: string; xl: string };
  fonts_url: string;
  logo_url?: string | null;
};

/** The product's own look, until (or unless) the academy's arrives. */
export const FALLBACK_BRANDING: Branding = {
  name: { ar: 'الأكاديمية', en: 'Academy' },
  tokens: {
    primary: '#0D735E', attention: '#B7791F', danger: '#B42318', info: '#1D5FB8', neutral: 'stone',
    heading_font: 'IBM Plex Sans Arabic', body_font: 'IBM Plex Sans Arabic', radius: 'soft',
  },
  radii: { sm: '0.5rem', md: '0.75rem', lg: '1rem', xl: '1.5rem' },
  fonts_url: '',
};

// The look changes rarely: kept five minutes per host, as GET /branding asks (Cache-Control 300).
const looks = new Map<string, { at: number; branding: Branding }>();

/** The academy's look for this host (its colours, corners and fonts). */
export const branding = cache(async (): Promise<Branding> => {
  const host = (await headers()).get('host') ?? '';
  const kept = looks.get(host);
  if (kept && Date.now() - kept.at < 300_000) return kept.branding;
  try {
    const response = await call('get', '/v1/branding');
    if (!response.ok) return kept?.branding ?? FALLBACK_BRANDING;
    const found = ((await response.json()) as { data: Branding }).data;
    looks.set(host, { at: Date.now(), branding: found });
    return found;
  } catch {
    return kept?.branding ?? FALLBACK_BRANDING;
  }
});

/** The neutral palettes an academy picks from, as one ink-grey each (Tailwind's 600). */
const NEUTRALS: Record<string, string> = { slate: '#475569', gray: '#4B5563', zinc: '#52525B', neutral: '#525252', stone: '#57534E' };

export function neutralOf(name: string): string {
  return NEUTRALS[name] ?? (/^#[0-9a-f]{6}$/i.test(name) ? name : NEUTRALS.stone!);
}

export type Me = {
  id: string;
  name: string;
  email: string | null;
  roles: string[];
  phone?: string | null;
  locale: string | null;
  /** The zone the person chose for their account, or null while they have none. */
  timezone?: string | null;
  /** The zone this answer's times are written in (the zone chosen on this browser, else the device's, else the account's, else the academy's). */
  display_timezone: string;
  /** display_timezone as people read it, in the reader's language: "القاهرة (UTC+2)". */
  timezone_label: string;
  /** The device's clock (X-Timezone) and the account's differ. */
  timezone_mismatch: boolean;
  /** How this person reads a clock: their own choice, else the academy's. */
  time_format: TimeFormat;
  /** Their own choice, or null while they follow the academy's. */
  time_format_choice: TimeFormat | null;
  /** Whether they show the floating zone chip (hidden from its card, shown again from My account). */
  zone_chip?: boolean;
  /** The areas the academy's plan includes (crm, online_payments, import, custom_branding, payroll). */
  plan_features?: string[];
  /** The academy's own clock is the one for everyone who has not chosen theirs. */
  academy: { id: string; slug: string; name_ar: string; name_en: string; default_timezone: string; timezone_label: string; time_format: TimeFormat } | null;
};

/** The signed-in person (their roles decide their portal). */
export const me = cache(async (): Promise<Me> => {
  return (await get<{ data: Me }>('/v1/me')).data;
});

/** How the signed-in person reads a clock, for a server component that writes one (client components ask `useTimeFormat`). */
export async function viewerTimeFormat(): Promise<TimeFormat> {
  return timeFormatOf((await me()).time_format);
}
