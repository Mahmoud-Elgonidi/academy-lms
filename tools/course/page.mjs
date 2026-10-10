#!/usr/bin/env node
// Builds the course page (out/site/index.html) from the series files and what
// has been filmed (out/<series>/index.json, the .srt files), with the videos and
// posters next to it under videos/. `node page.mjs` prints the files to publish.
import { copyFileSync, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadSeries } from './syllabus.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const site = join(here, 'out', 'site');
mkdirSync(join(site, 'videos'), { recursive: true });

const seconds = (stamp) => {
  const [h, m, rest] = stamp.split(':');
  return Number(h) * 3600 + Number(m) * 60 + Number(rest.replace(',', '.'));
};
const cuesOf = (srt) => srt.trim().split(/\n\s*\n/).map((block) => {
  const [, time, ...text] = block.split('\n');
  return { t: Math.round(seconds(time.split(' --> ')[0]) * 10) / 10, text: text.join(' ') };
});

const files = {};
const data = (await loadSeries()).map((s) => {
  const indexFile = join(here, 'out', s.key, 'index.json');
  const made = existsSync(indexFile) ? JSON.parse(readFileSync(indexFile, 'utf8')) : {};
  return {
    key: s.key, title: s.title, audience: s.audience, color: s.color, device: s.device,
    lessons: s.lessons.map((l) => {
      const m = made[l.n];
      const lesson = { n: l.n, title: l.title, goal: l.goal, learn: l.learn ?? [], who: l.who ?? null };
      if (!m) return lesson;
      const base = m.file.replace(/\.mp4$/, '');
      const video = `videos/${s.key}-${base}.mp4`;
      const poster = `videos/${s.key}-${base}.jpg`;
      copyFileSync(join(here, 'out', s.key, m.file), join(site, video));
      copyFileSync(join(here, 'out', s.key, `${base}.jpg`), join(site, poster));
      files[video] = `tools/course/out/site/${video}`;
      files[poster] = `tools/course/out/site/${poster}`;
      return { ...lesson, video, poster, seconds: m.seconds, cues: cuesOf(readFileSync(join(here, 'out', s.key, `${base}.srt`), 'utf8')) };
    }),
  };
});

const template = readFileSync(join(here, 'templates', 'page.html'), 'utf8');
writeFileSync(join(site, 'index.html'), template.replace('/*COURSE_DATA*/[]', JSON.stringify(data)));
writeFileSync(join(site, 'files.json'), JSON.stringify(files, null, 2));
const ready = data.reduce((n, s) => n + s.lessons.filter((l) => l.video).length, 0);
console.log(`out/site/index.html: ${ready} of ${data.reduce((n, s) => n + s.lessons.length, 0)} lessons filmed; files in out/site/files.json`);
