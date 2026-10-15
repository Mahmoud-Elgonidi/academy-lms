import { execFileSync, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, renameSync, rmSync, writeFileSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * The narrator: each line a lesson says is spoken by an ElevenLabs voice before
 * the lesson is filmed, so the film can wait for the voice and the voice can be
 * laid on the film at the moment its caption shows.
 *
 * Settings come from the environment only: ELEVENLABS_API_KEY (the key; never
 * in a file or a log), ELEVENLABS_VOICE_ID (the voice), ELEVENLABS_MODEL
 * (eleven_v4 by default: listened back with listen.mjs, both Gamal and Seham
 * speak Egyptian there with no word added, while eleven_multilingual_v2 reads
 * Gamal as Modern Standard Arabic and adds words to Seham's lines),
 * ELEVENLABS_SPEED and ELEVENLABS_API_BASE
 * (https://api.eu.residency.elevenlabs.io for an account kept in the EU). Without a voice the
 * course is captions only. Spoken lines are kept in .voice/ by what was said and
 * how, so filming a lesson again costs nothing for the lines that did not change.
 */
const here = dirname(fileURLToPath(import.meta.url));
const cacheDir = join(here, '../.voice');
const API = `${(process.env.ELEVENLABS_API_BASE || 'https://api.elevenlabs.io').replace(/\/+$/, '')}/v1`;

let settings;
export function voiceSettings() {
  const voice = process.env.ELEVENLABS_VOICE_ID;
  if (!voice || process.env.COURSE_VOICE === 'off') return null;
  return (settings ??= {
    voice,
    model: process.env.ELEVENLABS_MODEL || 'eleven_v4',
    settings: { stability: 0.45, similarity_boost: 0.8, style: 0.2, use_speaker_boost: true, speed: Number(process.env.ELEVENLABS_SPEED || 1) },
  });
}

/**
 * Calls the API through curl (it goes through the environment's proxy and its
 * certificates). The key goes in a config read from stdin, never on the command line.
 */
function call(path, { body, form, out } = {}) {
  mkdirSync(cacheDir, { recursive: true });
  const bodyFile = body ? join(cacheDir, `.body-${process.pid}.json`) : null;
  const target = out ?? join(cacheDir, `.answer-${process.pid}`);
  if (bodyFile) writeFileSync(bodyFile, JSON.stringify(body));
  const config = [
    `url = "${API}${path}"`,
    ...(process.env.ELEVENLABS_API_KEY ? [`header = "xi-api-key: ${process.env.ELEVENLABS_API_KEY}"`] : []),
    'header = "Accept: */*"',
    ...(bodyFile ? ['request = "POST"', 'header = "Content-Type: application/json"', `data-binary = "@${bodyFile}"`] : []),
    ...Object.entries(form ?? {}).map(([k, v]) => `form = "${k}=${v}"`),
    `output = "${target}"`,
    'silent',
    'show-error',
    'write-out = "%{http_code}"',
  ].join('\n');
  const r = spawnSync('curl', ['-K', '-'], { input: config, encoding: 'utf8' });
  if (bodyFile) rmSync(bodyFile, { force: true });
  const status = Number(r.stdout.trim());
  if (status !== 200) {
    const detail = existsSync(target) ? readFileSync(target, 'utf8').slice(0, 400) : r.stderr.trim();
    rmSync(target, { force: true });
    throw new Error(`ElevenLabs ${path.split('?')[0]} answered ${status || 'nothing'}: ${detail}`);
  }
  if (!out) {
    const text = readFileSync(target, 'utf8');
    rmSync(target, { force: true });
    return JSON.parse(text);
  }
  return out;
}

/** What a listener hears in a spoken line (ElevenLabs' speech to text, held to Arabic), kept next to the clip. */
export function transcribe(file) {
  const kept = file.replace(/\.mp3$/, '.heard.txt');
  if (existsSync(kept)) return readFileSync(kept, 'utf8');
  const { text } = call('/speech-to-text', { form: { model_id: 'scribe_v2', language_code: 'ara', file: `@${file}` } });
  writeFileSync(kept, text);
  return text;
}

export const secondsOf = (file) =>
  Number(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file]).toString().trim());

/** One spoken line as an MP3 (from the cache when it was said the same way before), with its length. */
/**
 * What is sent to be spoken: the line without its «quote marks», which some
 * models fill with a word of their own («اللي حصل» came out "إزاي اللي حصل").
 */
export const spokenText = (text) => text.replace(/[«»]/g, '').replace(/\s{2,}/g, ' ').trim();

export function speak(text, { previous, next } = {}, cfg = voiceSettings()) {
  text = spokenText(text);
  previous &&= spokenText(previous);
  next &&= spokenText(next);
  const key = createHash('sha256').update(JSON.stringify([cfg.voice, cfg.model, cfg.settings, text])).digest('hex').slice(0, 24);
  const file = join(cacheDir, `${key}.mp3`);
  if (!existsSync(file)) {
    const part = `${file}.part`;
    call(`/text-to-speech/${encodeURIComponent(cfg.voice)}?output_format=mp3_44100_128`, {
      body: {
        text,
        model_id: cfg.model,
        voice_settings: cfg.settings,
        // The lines around it, so the voice keeps one tone through the lesson.
        ...(previous ? { previous_text: previous } : {}),
        ...(next ? { next_text: next } : {}),
      },
      out: part,
    });
    renameSync(part, file);
  }
  return { file, seconds: secondsOf(file), text };
}

/**
 * What a lesson will say, in order, found by playing its steps against a stand-in
 * that does nothing (every locator and action is a no-op), so the voice can be
 * made before filming starts.
 */
export async function linesOf(lesson) {
  const lines = [];
  const sink = new Proxy(function () {}, { get: (_, k) => (k === 'then' ? undefined : sink), apply: () => sink });
  const s = new Proxy({}, {
    get: (_, k) => {
      if (k === 'say') return async (text, o = {}) => { lines.push({ caption: text, spoken: o.voice ?? text }); };
      if (k === 'page' || k === 'then') return k === 'then' ? undefined : sink;
      return (..._args) => sink;
    },
  });
  await lesson.steps(s);
  return lines;
}

/**
 * A lesson's number as the narrator says it: «الدرس الأوّل», then «الدرس رقم
 * اتنين», «رقم تمانية»... (listened back, an ordinal like التاني can come out
 * as the formal الثاني; a number after رقم stays Egyptian).
 */
const NUMBERS = ['', 'واحد', 'اتنين', 'تلاتة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'تمانية', 'تسعة', 'عشرة', 'حداشر', 'اتناشر', 'تلاتاشر', 'أربعتاشر', 'خمستاشر', 'ستّاشر', 'سبعتاشر', 'تمنتاشر', 'تسعتاشر', 'عشرين'];
export const spokenLesson = (n) => (n === 1 ? 'الدرس الأوّل' : `الدرس رقم ${NUMBERS[n] ?? n}`);

/** The lesson's whole narration: a welcome over the title card, one line per caption, a goodbye over the end card. */
export async function narrate(series, lesson, next, cfg = voiceSettings()) {
  if (!cfg || !lesson.steps) return null;
  const steps = await linesOf(lesson);
  const intro = lesson.intro ?? `أهلًا بيك في ${series.title}، ${spokenLesson(lesson.n)}: ${lesson.title}. ${lesson.goal ?? ''}`.trim();
  const outro = lesson.outro ?? `وكده خلّصنا درس «${lesson.title}». ${next ? `في الدرس الجاي: ${next.title}.` : `وده كان آخر درس في ${series.title}.`}`;
  const all = [intro, ...steps.map((l) => l.spoken), outro];
  const clips = all.map((text, i) => speak(text, { previous: all[i - 1], next: all[i + 1] }, cfg));
  return { intro: clips[0], lines: clips.slice(1, -1).map((c, i) => ({ ...c, caption: steps[i].caption })), outro: clips[clips.length - 1] };
}

// `node lib/voice.mjs voices [search]`: your voices and Arabic ones from the voice library, to pick ELEVENLABS_VOICE_ID.
if (import.meta.url === `file://${process.argv[1]}` && process.argv[2] === 'voices') {
  try {
    const search = process.argv[3] ?? '';
    const mine = call('/voices');
    console.log('Your voices:');
    for (const v of mine.voices ?? []) console.log(`  ${v.voice_id}  ${v.name}  ${JSON.stringify(v.labels ?? {})}`);
    const shared = call(`/shared-voices?page_size=30&language=ar${search ? `&search=${encodeURIComponent(search)}` : ''}`);
    console.log('Arabic voices in the library (add one to your voices first, then use its id):');
    for (const v of shared.voices ?? []) console.log(`  ${v.voice_id}  ${v.name}  ${v.accent ?? ''} ${v.gender ?? ''} ${v.age ?? ''}  ${v.preview_url ?? ''}`);
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
}
