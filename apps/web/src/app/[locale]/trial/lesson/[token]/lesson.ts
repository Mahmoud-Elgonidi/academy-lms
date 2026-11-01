import 'server-only';
import { read } from '@/lib/api/server';
import type { TrialLesson } from '@/lib/site/actions';

/** A coming free trial from its own link (GET site/trials/lesson/{token}), or null: over, cancelled, or not this academy's. */
export async function lessonOf(token: string): Promise<TrialLesson | null> {
  const found = await read<{ data: TrialLesson }>('/v1/site/trials/lesson/{token}', { params: { token } });
  return found.ok ? found.data.data : null;
}
