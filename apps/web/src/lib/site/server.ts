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

/** Whether this address is an academy's (its own host, or the platform's while it serves a home academy). */
export const academyHere = cache(async (): Promise<boolean> => {
  try {
    return (await call('get', '/v1/branding')).ok;
  } catch {
    return false;
  }
});
