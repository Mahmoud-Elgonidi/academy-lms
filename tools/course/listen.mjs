#!/usr/bin/env node
// Listens to a lesson's narration before (or after) filming: `node listen.mjs <series> [n…]`.
// Each spoken line (made, or taken from .voice/) is turned back into words by
// ElevenLabs' speech to text, and the words that came out different from the
// script are listed, so a slip ("بيضته" said as "بيته") is caught and the line
// is reworded or given its vowels in `voice:`. Speech to text writes some words
// its own way (numbers as digits, "يأخذ" for "ياخد"): a listed word is one to
// listen to, not always a mistake. Needs ELEVENLABS_VOICE_ID, like record.mjs.
import { join } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname } from 'node:path';
import { narrate, transcribe, voiceSettings } from './lib/voice.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const [key, ...only] = process.argv.slice(2);
if (!key || !voiceSettings()) {
  console.error('ELEVENLABS_VOICE_ID=… node listen.mjs <series> [lesson numbers…]');
  process.exit(1);
}
const series = (await import(pathToFileURL(join(here, 'series', `${key}.mjs`)).href)).default;

// Numbers the narrator says in words and speech to text writes in digits.
const NUMBERS = { واحد: 1, اتنين: 2, تلاته: 3, تلات: 3, اربعه: 4, اربع: 4, خمسه: 5, خمس: 5, سته: 6, ست: 6, سبعه: 7, سبع: 7, تمنيه: 8, تمن: 8, تسعه: 9, تسع: 9, عشره: 10, عشر: 10, حداشر: 11, اتناشر: 12, عشرين: 20 };

// Letters only: no vowels, punctuation or quotes, and the letters people write either way made one.
const words = (t) => t
  .replace(/[ً-ْٰـ]/g, '')
  .replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي')
  .replace(/[^\p{L}\p{N}\s]/gu, ' ')
  .replace(/(^|\s)و\s+/g, '$1و')   // و«كلّم مدرسك» is one word once the quote goes
  .split(/\s+/).filter(Boolean)
  .map((w) => String(NUMBERS[w] ?? w));

/** The words of `said` that `heard` lacks, and those it has instead (a word-level diff). */
function differences(said, heard) {
  const a = words(said), b = words(heard);
  const t = Array.from({ length: a.length + 1 }, () => new Array(b.length + 1).fill(0));
  for (let i = a.length - 1; i >= 0; i--) for (let j = b.length - 1; j >= 0; j--) t[i][j] = a[i] === b[j] ? t[i + 1][j + 1] + 1 : Math.max(t[i + 1][j], t[i][j + 1]);
  const out = [];
  let i = 0, j = 0, gone = [], came = [];
  const flush = () => { if (gone.length || came.length) out.push(`«${gone.join(' ')}» ← «${came.join(' ')}»`); gone = []; came = []; };
  while (i < a.length || j < b.length) {
    if (i < a.length && j < b.length && a[i] === b[j]) { flush(); i++; j++; }
    else if (j < b.length && (i === a.length || t[i][j + 1] >= t[i + 1][j])) came.push(b[j++]);
    else gone.push(a[i++]);
  }
  flush();
  return out;
}

let flagged = 0;
for (const lesson of series.lessons.filter((l) => l.steps && (!only.length || only.includes(String(l.n))))) {
  const next = series.lessons[series.lessons.indexOf(lesson) + 1];
  const voice = await narrate(series, lesson, next);
  const clips = [voice.intro, ...voice.lines, voice.outro];
  console.log(`\n${series.key} ${lesson.n}: ${lesson.title}`);
  clips.forEach((c, n) => {
    const diff = differences(c.text, transcribe(c.file));
    flagged += diff.length ? 1 : 0;
    console.log(`  ${String(n).padStart(2)} ${diff.length ? '✗ ' + diff.join('  ') : '✓'}`);
  });
}
console.log(flagged ? `\n${flagged} line(s) to listen to (said ← heard).` : '\nEvery line came back as written.');
