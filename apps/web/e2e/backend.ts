import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * The API's side of the end-to-end tests: artisan in apps/api (E2E_API_DIR),
 * with the environment the API runs with (the same DB_DATABASE and the
 * rest); E2E_ARTISAN replaces `php artisan` (say, `docker compose exec -T api
 * php artisan`).
 */
export function artisan(args: string[]): string {
  const [command = 'php', ...prefix] = (process.env.E2E_ARTISAN ?? 'php artisan').split(' ').filter(Boolean);
  try {
    return execFileSync(command, [...prefix, ...args], {
      cwd: resolve(process.env.E2E_API_DIR ?? '../api'),
      env: process.env,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
      timeout: 120_000,
    });
  } catch (error) {
    const { stdout = '', stderr = '' } = error as { stdout?: string; stderr?: string };
    throw new Error(`artisan ${args.join(' ')} failed:\n${stdout}\n${stderr}`.trim());
  }
}

export const FIXTURES_FILE = 'e2e/.auth/fixtures.json';

/** What E2eResetSeeder set up for this run. */
export type Fixtures = {
  /** Lessons open now, in rooms on the teacher's outside link: Khaled's, and Ziad's (the same one when both were free). */
  class: { teacher_lesson: string; family_lesson: string };
  /** Ziad's homework: one to hand in in words, one handed in as a recording for Khaled to mark. */
  homework: { hand_in: string; to_mark: string };
  /** The demo academy's own host ({slug}.{platform domain}, with the API's port here). */
  academy_host: string;
};

export function fixtures(): Fixtures {
  return JSON.parse(readFileSync(FIXTURES_FILE, 'utf8')) as Fixtures;
}

/** Where the API answers this machine (the panels, Laravel's public pages). */
export const API = process.env.E2E_API_URL ?? 'http://127.0.0.1:8000';

/** A request to the API for this path as the academy's own host would make it (its host may have no DNS here). */
export function onAcademyHost(path: string): [string, { headers: Record<string, string> }] {
  return [`${API}${path}`, { headers: { Host: fixtures().academy_host } }];
}

/** Staff approve the receipt Ziad's guardian sent with this reference, as the back office would (ApprovePaymentProof); the invoice's number. */
export function approveProof(reference: string): string {
  if (!/^[\w-]+$/.test(reference)) throw new Error(`Not a reference: ${reference}`);
  const out = artisan(['tinker', `--execute=echo Database\\Seeders\\E2eResetSeeder::approveProof('${reference}');`]);
  return out.trim().split('\n').pop() ?? '';
}
