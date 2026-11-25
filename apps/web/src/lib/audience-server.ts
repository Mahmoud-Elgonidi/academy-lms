import 'server-only';
import { cache } from 'react';
import { audienceOf, type Audience } from './audience';
import { branding } from './viewer';

/** This academy's audience (GET /v1/branding, kept five minutes per host); no academy here: families. The request config passes its locale (see branding). */
export const academyAudience = cache(async (locale?: string): Promise<Audience> => audienceOf((await branding(locale)).audience));

/** Whether this academy trains adults (no guardians, no children, training words). */
export async function adultsAcademy(): Promise<boolean> {
  return (await academyAudience()) === 'adults';
}
