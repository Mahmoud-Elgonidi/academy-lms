'use client';

import { createContext, useContext, type ReactNode } from 'react';
import type { Audience } from '@/lib/audience';

/** The academy's audience for client components (provided once by the locale layout, from GET /v1/branding). */
const AudienceContext = createContext<Audience>('families');

export function AudienceProvider({ audience, children }: { audience: Audience; children: ReactNode }) {
  return <AudienceContext.Provider value={audience}>{children}</AudienceContext.Provider>;
}

/** Whether this academy trains adults: no child, no guardian, nothing a family does. */
export function useAdults(): boolean {
  return useContext(AudienceContext) === 'adults';
}
