import 'server-only';
import { cache } from 'react';
import { call, type PathFor } from '@/lib/api/server';
import type { SitePageData, SitePreviewInfo } from './page';
import type { TrialSettings } from './trial';

export type { SitePageData, SitePreviewInfo } from './page';

/** What the platform's page says, as GET /v1/site/platform answers it (in the visitor's language). */
export type PlatformSite = {
  content: {
    hero: { title: string; lead: string };
    stats: { value: string; label: string }[];
    testimonials: { name: string; role: string; quote: string }[];
    faqs: { q: string; a: string }[];
    sections: string[];
    contact: { whatsapp: string | null; email: string | null };
    demo_url: string | null;
    seo: { title: string; description: string };
  };
  plans: {
    code: string;
    name: string;
    tagline: string | null;
    price: string | null;
    highlight: boolean;
    limits: { key: string; label: string; value: string; unlimited: boolean }[];
    features: { key: string; label: string; on: boolean }[];
  }[];
  app: { android_url: string | null; ios_url: string | null };
  legal_name: string;
  form_token: string;
};

/** The platform's page, or null on an academy's address (its own page is there). */
export const platformSite = cache(async (): Promise<PlatformSite | null> => {
  try {
    const response = await call('get', '/v1/site/platform');
    if (!response.ok) return null;
    return ((await response.json()) as { data: PlatformSite }).data;
  } catch {
    return null;
  }
});

/** An academy's own page, as GET /v1/site answers it (in the visitor's language). */
export type AcademySite = {
  academy: { name: string; code: string };
  /** The page the academy built (blocks, settings), localized; missing from an older API (the old page is drawn then). */
  page?: SitePageData;
  /** Only in the builder's preview (GET /v1/site/preview): which draft this is, and whether the page is on. */
  preview?: SitePreviewInfo;
  /** The old words, derived from the page for one release (deprecated). */
  content: {
    hero: { title: string; lead: string };
    /** Whom the page speaks to: families (children, "your child") or adults ("you"). */
    audience?: 'families' | 'adults';
    /** The heading over the features; empty, the page says its own. */
    why?: { title: string; lead: string };
    features: { title: string; text: string }[];
    testimonials: { name: string; role: string; quote: string }[];
    faqs: { q: string; a: string }[];
    sections: string[];
    whatsapp: string | null;
    seo: { title: string; description: string };
  };
  subjects: { id: string; name: string; levels: string[]; categories: string[]; programs: { name: string; description: string | null }[] }[];
  teachers: {
    id: string;
    name: string;
    bio: string | null;
    photo_url: string | null;
    gender: string | null;
    languages: string[];
    age_groups: string[];
    subjects: { name: string; level_from: string | null; level_to: string | null }[];
    accepts_trials: boolean;
    /** Teaches group lessons too. */
    accepts_groups: boolean;
  }[];
  plans: {
    id: string;
    name: string;
    session_minutes: number;
    sessions_per_week: number;
    sessions_per_block: number;
    validity_days: number;
    shared: boolean;
    /** private (one student a lesson) or group. */
    format: 'private' | 'group';
    /** A group plan: the most students in one of its lessons (the largest group any of the academy's teachers takes); null for a private plan. */
    max_students: number | null;
    prices: { region_id: string; price: string; amount_minor: number; currency: string }[];
  }[];
  regions: { id: string; name: string; currency: string; default: boolean; countries: string[] }[];
  app: { android_url: string | null; ios_url: string | null };
  whatsapp: string | null;
  /** How the academy takes a free trial asked for on its page (families pick their own time, or its staff book it). */
  trials?: TrialSettings;
  form_token: string;
};

/** The academy's page on this address, or null (no academy here, or it keeps its page off). */
export const academySite = cache(async (): Promise<AcademySite | null> => {
  try {
    const response = await call('get', '/v1/site');
    if (!response.ok) return null;
    return ((await response.json()) as { data: AcademySite }).data;
  } catch {
    return null;
  }
});

/** Whether this address is an academy's (its own host, or the platform's while it serves a home academy). */
export const academyHere = cache(async (): Promise<boolean> => {
  try {
    return (await call('get', '/v1/branding')).ok;
  } catch {
    return false;
  }
});

/** Why a preview could not be shown: the link expired (410), or it is not a link to this academy's draft. */
export type PreviewRefusal = 'expired' | 'invalid';

/**
 * The academy's draft for the builder's preview (GET /v1/site/preview): the
 * same shape as its page, even while the page is off. The token is the proof
 * (a two-hour link from /admin); the portal holds no admin session.
 */
export async function academySitePreview(token: string): Promise<AcademySite | PreviewRefusal> {
  // Until the API ships the preview (8.4): a fixture page in development only. Removed before merge.
  if (token.startsWith('fixture') && process.env.NODE_ENV === 'development') {
    const [{ fixtureSite }, { getLocale }] = await Promise.all([import('./fixtures/site'), import('next-intl/server')]);
    return fixtureSite(token.slice('fixture'.length).replace(/^-/, '') || 'all', await getLocale());
  }
  if (!/^[A-Za-z0-9._~-]{8,512}$/.test(token)) return 'invalid';
  try {
    const response = await call('get', '/v1/site/preview' as PathFor<'get'>, { query: { token } });
    if (response.status === 410) return 'expired';
    if (!response.ok) return 'invalid';
    return ((await response.json()) as { data: AcademySite }).data;
  } catch {
    return 'invalid';
  }
}
