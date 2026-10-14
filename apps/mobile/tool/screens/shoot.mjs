// Pictures of the app's looks from the real web build, for the people who
// review them (nothing here goes into git: pictures land in build/screens).
//
//   flutter build web --no-web-resources-cdn
//   flutter test tool/screens/data_test.dart
//   node tool/screens/shoot.mjs --look teen --look middle --lang ar
//   node tool/screens/shoot.mjs --all --lang en
//
// Each look signs in by code, then every place data_test.dart visited is
// opened and pictured at phone size (390 by 844, twice the pixels), the
// clock fixed at 5:20 PM in Riyadh today. With ffmpeg on the path, a look's
// pictures are also laid side by side in one sheet.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

import { answer, lookData, open, phone, root, serve, signIn } from './lib.mjs';

const args = process.argv.slice(2);
const all = ['guardian', 'teacher', 'kids', 'middle', 'teen'];
const looks = args.includes('--all') ? all : args.flatMap((a, i) => (args[i - 1] === '--look' ? [a] : []));
const lang = args[args.indexOf('--lang') + 1] === 'en' ? 'en' : 'ar';
if (looks.length === 0 || looks.some((l) => !all.includes(l))) {
  console.error(`Usage: node tool/screens/shoot.mjs (--look ${all.join('|')})... | --all  [--lang ar|en]`);
  process.exit(1);
}

const now = new Date();
now.setUTCHours(14, 20, 0, 0);
const site = await serve();
try {
  for (const look of looks) {
    const data = lookData(look, now);
    const dir = path.join(root, 'build/screens', `${look}-${lang}`);
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });
    const { browser, page } = await phone({ lang, now });
    const misses = await answer(page, data);
    await page.goto(site.url);
    await new Promise((done) => setTimeout(done, 3500));
    await signIn(page, lang);
    const shots = [];
    for (const [i, place] of data.places.entries()) {
      await open(page, site.url, place);
      const file = path.join(dir, `${String(i + 1).padStart(2, '0')}-${place.split('/').filter(Boolean).slice(1).join('-')}.png`);
      await page.screenshot({ path: file });
      shots.push(file);
    }
    await browser.close();
    console.log(`${look} (${lang}): ${shots.length} pictures in ${path.relative(root, dir)}${misses.size ? `; not answered: ${[...misses].join(', ')}` : ''}`);
    try {
      const inputs = shots.flatMap((f) => ['-i', f]);
      const scale = shots.map((_, i) => `[${i}]scale=390:-1[s${i}]`).join(';');
      const stack = shots.map((_, i) => `[s${i}]`).join('') + `hstack=inputs=${shots.length}`;
      execFileSync('ffmpeg', ['-loglevel', 'error', '-y', ...inputs, '-filter_complex', `${scale};${stack}`, path.join(root, 'build/screens', `${look}-${lang}.png`)]);
    } catch {
      // No ffmpeg: the pictures alone.
    }
  }
} finally {
  site.close();
}
