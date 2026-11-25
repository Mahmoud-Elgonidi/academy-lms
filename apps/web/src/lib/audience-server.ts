import 'server-only';
import { cache } from 'react';
import { audienceOf, type Audience } from './audience';
import { branding } from './viewer';

/** This academy's audience (GET /v1/branding, kept five minutes per host); no academy here: families. */
export const academyAudience = cache(async (): Promise<Audience> => audienceOf((await branding()).audience));

/** Whether this academy trains adults (no guardians, no children, training words). */
export async function adultsAcademy(): Promise<boolean> {
  return (await academyAudience()) === 'adults';
}
