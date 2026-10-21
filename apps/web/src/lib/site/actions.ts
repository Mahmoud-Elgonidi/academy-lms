'use server';

import { send } from '@/lib/api/server';

/**
 * A form's answer for the page: sent, or what to put right (by field), or a
 * word for the whole form; with what was typed, so the form shows it again
 * (React empties a form once its action is done).
 */
export type SiteFormState = { status: 'idle' | 'sent' | 'failed'; errors: Record<string, string>; message?: string; values?: Record<string, string | string[]> };

/** What a visitor typed, to put back (never the form's token or its hidden field). */
function typed(form: FormData): Record<string, string | string[]> {
  const values: Record<string, string | string[]> = {};
  for (const key of new Set(form.keys())) {
    if (key === 'form_token' || key === 'website' || key.startsWith('$')) continue;
    const all = form.getAll(key).map(String);
    values[key] = all.length > 1 || key.endsWith('_ids') ? all : (all[0] ?? '');
  }
  return values;
}

const FIELDS = ['academy_name', 'contact_name', 'email', 'phone', 'country', 'students', 'plan', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'form_token', 'website'] as const;

/** An academy asks to join the platform (the platform's page). */
export async function askToJoinPlatform(_previous: SiteFormState, form: FormData): Promise<SiteFormState> {
  const body: Record<string, string> = {};
  for (const field of FIELDS) body[field] = String(form.get(field) ?? '');
  const done = await send('post', '/v1/site/platform/requests', { body });
  if (done.ok) return { status: 'sent', errors: {} };
  const errors = Object.fromEntries(Object.entries(done.problem.errors).map(([field, messages]) => [field, messages[0] ?? '']));
  return { status: 'failed', errors, message: done.problem.status === 422 ? undefined : done.problem.title || undefined, values: typed(form) };
}

const ACADEMY_FIELDS = ['kind', 'name', 'phone', 'email', 'country', 'timezone', 'subject_id', 'child_name', 'child_birth_year', 'years_teaching', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'form_token', 'website'] as const;

/** A family asks the academy for a free trial lesson, or a teacher asks to teach there (the academy's page). */
export async function askTheAcademy(_previous: SiteFormState, form: FormData): Promise<SiteFormState> {
  const body: Record<string, unknown> = {};
  for (const field of ACADEMY_FIELDS) {
    const value = String(form.get(field) ?? '').trim();
    if (value !== '' || field === 'website' || field === 'form_token') body[field] = value;
  }
  if (body.kind === 'trial') body.for_child = form.get('for_child') === 'on';
  if (body.kind === 'teach') body.subject_ids = form.getAll('subject_ids').map(String);
  const done = await send('post', '/v1/site/requests', { body });
  if (done.ok) return { status: 'sent', errors: {} };
  const errors = Object.fromEntries(Object.entries(done.problem.errors).map(([field, messages]) => [field, messages[0] ?? '']));
  return { status: 'failed', errors, message: done.problem.status === 422 ? undefined : done.problem.title || undefined, values: typed(form) };
}
