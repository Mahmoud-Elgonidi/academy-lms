'use server';

import { send } from '@/lib/api/server';

/** A form's answer for the page: sent, or what to put right (by field), or a word for the whole form. */
export type SiteFormState = { status: 'idle' | 'sent' | 'failed'; errors: Record<string, string>; message?: string };

const FIELDS = ['academy_name', 'contact_name', 'email', 'phone', 'country', 'students', 'plan', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'form_token', 'website'] as const;

/** An academy asks to join the platform (the platform's page). */
export async function askToJoinPlatform(_previous: SiteFormState, form: FormData): Promise<SiteFormState> {
  const body: Record<string, string> = {};
  for (const field of FIELDS) body[field] = String(form.get(field) ?? '');
  const done = await send('post', '/v1/site/platform/requests', { body });
  if (done.ok) return { status: 'sent', errors: {} };
  const errors = Object.fromEntries(Object.entries(done.problem.errors).map(([field, messages]) => [field, messages[0] ?? '']));
  return { status: 'failed', errors, message: done.problem.status === 422 ? undefined : done.problem.title || undefined };
}
