import { mkdirSync, writeFileSync } from 'node:fs';
import { FIXTURES_FILE, artisan } from './backend';

/**
 * Before every run: the demo academy's state the tests use up (a week's
 * chest, cheers, homework, a receipt waiting, play time, an egg, the lesson
 * open now) is put back by the API's E2eResetSeeder, with the API's own
 * environment (E2E_API_DIR, E2E_ARTISAN), and the ids it printed are kept
 * for the tests. E2E_RESET=0 leaves the data as it is.
 */
export default async function globalSetup() {
  if (process.env.E2E_RESET === '0') return;
  const out = artisan(['db:seed', '--class=E2eResetSeeder', '--no-interaction']);
  const line = out.split('\n').find((l) => l.startsWith('E2E_FIXTURES '));
  if (!line) throw new Error(`E2eResetSeeder printed no fixtures:\n${out}`);
  mkdirSync('e2e/.auth', { recursive: true });
  writeFileSync(FIXTURES_FILE, line.slice('E2E_FIXTURES '.length));
}
