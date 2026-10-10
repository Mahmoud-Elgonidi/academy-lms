import { spawnSync } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { CARD_SECONDS, DESKTOP, FRAME, PHONE } from './layout.mjs';
import { backgroundHtml, captionHtml, endHtml, titleHtml } from './render.mjs';

/**
 * Puts a recorded lesson together: title card, the screen inside the lesson's
 * frame with its captions, end card; one H.264 MP4 (1080p, 30 fps, a silent
 * stereo track so every player treats it as a video), the captions as SRT and
 * the narration script for a voice-over.
 */
const ffmpeg = (args, label) => {
  const r = spawnSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args], { encoding: 'utf8', maxBuffer: 1 << 26 });
  if (r.status !== 0) throw new Error(`ffmpeg (${label}) failed:\n${r.stderr}`);
};
const X264 = ['-c:v', 'libx264', '-preset', 'medium', '-crf', '21', '-tune', 'stillimage', '-pix_fmt', 'yuv420p', '-r', String(FRAME.fps), '-g', String(FRAME.fps * 4), '-profile:v', 'high'];

const stamp = (t, sep = ',') => {
  const ms = Math.round(t * 1000);
  const h = Math.floor(ms / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  const s = Math.floor((ms % 60000) / 1000);
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}${sep}${String(ms % 1000).padStart(3, '0')}`;
};
const mmss = (t) => `${Math.floor(t / 60)}:${String(Math.floor(t % 60)).padStart(2, '0')}`;

/** A concat list where each still lasts until the next one; the last is listed twice so its own time counts. */
function concatList(items) {
  const lines = ['ffconcat version 1.0'];
  for (const { file, duration } of items) lines.push(`file '${file}'`, `duration ${Math.max(duration, 0.001).toFixed(4)}`);
  lines.push(`file '${items[items.length - 1].file}'`);
  return lines.join('\n') + '\n';
}

export async function encodeLesson({ renderer, series, lesson, next, dir, out, recording }) {
  const stills = join(dir, 'stills');
  rmSync(stills, { recursive: true, force: true });
  mkdirSync(stills, { recursive: true });
  const phone = series.device === 'phone';
  const layout = phone ? PHONE : DESKTOP;
  const { app } = layout;
  const length = recording.length;

  // The frame around the screen, drawn with a hole where the screen shows, so the screen's corners come out round.
  await renderer.png(backgroundHtml(series, lesson, { hole: true }), join(stills, 'cover.png'), { transparent: true });
  await renderer.png(titleHtml(series, lesson), join(stills, 'title.png'));
  await renderer.png(endHtml(series, lesson, next), join(stills, 'end.png'));

  // Captions: one transparent still each, a blank one in the gaps.
  const cap = phone ? PHONE.caption : DESKTOP.caption;
  await renderer.png('<!doctype html><html><body style="margin:0;background:transparent"></body></html>', join(stills, 'blank.png'), { width: cap.width, height: cap.height, transparent: true });
  const track = [];
  let at = 0;
  for (const [i, c] of recording.captions.entries()) {
    const file = `cap-${String(i + 1).padStart(3, '0')}.png`;
    await renderer.png(captionHtml(series, c.text, i + 1, recording.captions.length), join(stills, file), { width: cap.width, height: cap.height, transparent: true });
    if (c.start > at) track.push({ file: 'blank.png', duration: c.start - at });
    track.push({ file, duration: c.end - c.start });
    at = c.end;
  }
  if (length > at) track.push({ file: 'blank.png', duration: length - at });
  writeFileSync(join(stills, 'captions.txt'), concatList(track));

  const frames = recording.frames.map((f, i, all) => ({ file: `../frames/${f.file}`, duration: (all[i + 1]?.t ?? length) - f.t }));
  writeFileSync(join(stills, 'frames.txt'), concatList(frames));

  // The lesson itself.
  const fade = 0.35;
  const main = join(dir, 'main.mp4');
  ffmpeg([
    '-f', 'lavfi', '-i', `color=c=0x0b1220:s=${FRAME.width}x${FRAME.height}:r=${FRAME.fps}:d=${length.toFixed(3)}`,
    '-f', 'concat', '-safe', '0', '-i', join(stills, 'frames.txt'),
    '-loop', '1', '-framerate', String(FRAME.fps), '-t', length.toFixed(3), '-i', join(stills, 'cover.png'),
    '-f', 'concat', '-safe', '0', '-i', join(stills, 'captions.txt'),
    '-filter_complex', [
      `[1:v]fps=${FRAME.fps},scale=${app.width}:${app.height}:flags=lanczos,setsar=1[app]`,
      `[0:v][app]overlay=${app.x}:${app.y}:eof_action=repeat[a]`,
      `[a][2:v]overlay=0:0:shortest=1[b]`,
      `[3:v]fps=${FRAME.fps},format=rgba[cap]`,
      `[b][cap]overlay=${cap.x}:${cap.y}:eof_action=pass,fade=t=in:st=0:d=${fade},fade=t=out:st=${(length - fade).toFixed(3)}:d=${fade},format=yuv420p[v]`,
    ].join(';'),
    '-map', '[v]', '-t', length.toFixed(3), ...X264, main,
  ], 'lesson');

  // Cards.
  const card = (png, seconds, file) => ffmpeg([
    '-loop', '1', '-framerate', String(FRAME.fps), '-t', String(seconds), '-i', png,
    '-vf', `fade=t=in:st=0:d=0.45,fade=t=out:st=${seconds - 0.45}:d=0.45,format=yuv420p`,
    ...X264, file,
  ], 'card');
  card(join(stills, 'title.png'), CARD_SECONDS.title, join(dir, 'title.mp4'));
  card(join(stills, 'end.png'), CARD_SECONDS.end, join(dir, 'end.mp4'));

  writeFileSync(join(dir, 'parts.txt'), ['title.mp4', 'main.mp4', 'end.mp4'].map((f) => `file '${f}'`).join('\n') + '\n');
  const video = `${out}.mp4`;
  ffmpeg([
    '-f', 'concat', '-safe', '0', '-i', join(dir, 'parts.txt'),
    '-f', 'lavfi', '-i', 'anullsrc=r=48000:cl=stereo',
    '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '64k', '-shortest', '-movflags', '+faststart', video,
  ], 'final');
  ffmpeg(['-i', join(stills, 'title.png'), '-vf', 'scale=960:-2', '-q:v', '3', `${out}.jpg`], 'poster');

  // Captions and script, timed on the finished video (after the title card).
  const offset = CARD_SECONDS.title;
  writeFileSync(`${out}.srt`, recording.captions.map((c, i) => `${i + 1}\n${stamp(c.start + offset)} --> ${stamp(c.end + offset)}\n${c.text}\n`).join('\n'));
  const total = offset + length + CARD_SECONDS.end;
  writeFileSync(`${out}.md`, [
    `# ${series.title} — الدرس ${lesson.n}: ${lesson.title}`,
    '',
    `المدة: ${mmss(total)} · لمين: ${lesson.who ?? series.audience}`,
    '',
    lesson.goal ? `**الهدف:** ${lesson.goal}\n` : '',
    lesson.learn?.length ? `**هتتعلم:**\n${lesson.learn.map((l) => `- ${l}`).join('\n')}\n` : '',
    '## نص التعليق الصوتي',
    '',
    `**[0:00]** (كارت العنوان) ${lesson.title}. ${lesson.goal ?? ''}`.trim(),
    '',
    ...recording.captions.map((c) => `**[${mmss(c.start + offset)}]** ${c.text}\n`),
    `**[${mmss(offset + length)}]** (كارت النهاية) ${next ? `الدرس الجاي: ${next.title}.` : `ده آخر درس في ${series.title}.`}`,
    '',
  ].filter((l) => l !== null).join('\n'));

  return { video, seconds: total, captions: recording.captions.length };
}
