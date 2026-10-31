import 'server-only';
import { cache } from 'react';
import { call } from '@/lib/api/server';

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
  content: {
    hero: { title: string; lead: string };
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
