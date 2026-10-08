import 'server-only';
import { get } from '@/lib/api/server';
import type { SocialProvider } from '@/lib/social';

/** Google and Apple, where the platform has them for the web (none if the API cannot say). */
export async function webProviders(): Promise<SocialProvider[]> {
  try {
    return (await get<{ providers: SocialProvider[] }>('/v1/auth/social')).providers.filter((p) => p.web);
  } catch {
    return [];
  }
}
