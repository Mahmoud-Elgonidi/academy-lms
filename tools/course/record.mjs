#!/usr/bin/env node
// Films the course: `node record.mjs <series> [lesson numbers…]` against a running
// API with fresh demo data (`--base http://localhost:8000` by default). With an
// ElevenLabs voice (lib/voice.mjs) each lesson is narrated and paced to it. Each
// lesson ends up in out/<series>/ as NN-slug.mp4 with .srt, .md (narration
// script) and .jpg (poster); out/<series>/index.json lists what was made.
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from 'playwright';
import { encodeLesson } from './lib/encode.mjs';
import { recordLesson } from './lib/recorder.mjs';
import { makeRenderer } from './lib/render.mjs';
import { narrate, voiceSettings } from './lib/voice.mjs';
import { USERS } from './users.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const flag = (name) => {
  const i = args.indexOf(name);
  return i >= 0 ? args.splice(i, 2)[1] : null;
};
const base = flag('--base') ?? 'http://localhost:8000';
const option = (name) => args.includes(name) && Boolean(args.splice(args.indexOf(name), 1));
const reencode = option('--reencode');
const keep = option('--keep-frames') || reencode;
const [key, ...only] = args;
if (!key) {
  console.error('node record.mjs <series> [lesson numbers…] [--base URL] [--keep-frames] [--reencode]');
  process.exit(1);
}
const series = (await import(pathToFileURL(join(here, 'series', `${key}.mjs`)).href)).default;
const lessons = series.lessons.filter((l) => (only.length ? only.includes(String(l.n)) : l.steps));
for (const l of lessons) if (!l.steps) throw new Error(`${series.key} ${l.n} has no steps yet.`);
const outDir = join(here, 'out', series.key);
mkdirSync(outDir, { recursive: true });
const indexFile = join(outDir, 'index.json');
const index = existsSync(indexFile) ? JSON.parse(readFileSync(indexFile, 'utf8')) : {};

const voiceCfg = voiceSettings(series.voice);
console.log(voiceCfg ? `narrator: ElevenLabs voice ${voiceCfg.voice} (${voiceCfg.model})${lessons.some((l) => l.voice) ? ', or the lesson\'s own' : ''}`
  : lessons.some((l) => l.voice) && process.env.COURSE_VOICE !== 'off' ? 'narrator: each lesson\'s own voice' : 'narrator: none (set ELEVENLABS_VOICE_ID for a voice); captions only');
// A fake camera and microphone, allowed without asking: the live class films with them. The camera
// shows a calm moving gradient (made once into .media/) rather than Chrome's green test pattern.
const camera = join(here, '.media', 'camera.y4m');
if (!existsSync(camera)) {
  mkdirSync(dirname(camera), { recursive: true });
  execFileSync('ffmpeg', ['-v', 'error', '-y', '-f', 'lavfi', '-i', 'gradients=s=320x240:c0=0x2f6f8f:c1=0x9fd6c8:speed=0.03:duration=6:rate=15', '-pix_fmt', 'yuv420p', camera]);
}
const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', `--use-file-for-fake-video-capture=${camera}`] });
const renderer = await makeRenderer(browser);
let failed = 0;
for (const lesson of lessons) {
  const name = `${String(lesson.n).padStart(2, '0')}-${lesson.slug}`;
  const dir = join(outDir, '.work', name);
  // --reencode puts a kept recording together again (after a change to the look), without filming it again.
  if (!reencode) {
    rmSync(dir, { recursive: true, force: true });
    mkdirSync(dir, { recursive: true });
  }
  const user = USERS[lesson.user ?? series.user];
  // A lesson can take its own look within the series (the kids' font, for one).
  const look = { ...series, font: lesson.font ?? series.font };
  const started = Date.now();
  try {
    const next = series.lessons[series.lessons.indexOf(lesson) + 1];
    // The voice is made first, so filming can wait for each line.
    const recording = reencode
      ? JSON.parse(readFileSync(join(dir, 'recording.json'), 'utf8'))
      : await recordLesson({ browser, series: look, lesson, user, base, dir, voice: await narrate(series, lesson, next) });
    const made = await encodeLesson({ renderer, series: look, lesson, next, dir, out: join(outDir, name), recording });
    index[lesson.n] = { n: lesson.n, slug: lesson.slug, title: lesson.title, file: `${name}.mp4`, seconds: Math.round(made.seconds), captions: made.captions, voiced: made.voiced };
    writeFileSync(indexFile, JSON.stringify(index, null, 2));
    console.log(`✓ ${series.key} ${name}: ${made.seconds.toFixed(1)}s, ${recording.frames.length} frames, ${made.captions} captions${made.voiced ? ', voiced' : ''} (${((Date.now() - started) / 1000).toFixed(0)}s)${recording.errors.length ? `\n  page errors: ${recording.errors.join(' | ')}` : ''}`);
    if (!keep) rmSync(dir, { recursive: true, force: true });
  } catch (e) {
    failed++;
    console.error(`✗ ${series.key} ${name}: ${e.message}\n  (work kept in ${dir})`);
  }
}
await renderer.close();
await browser.close();
process.exit(failed ? 1 : 0);
