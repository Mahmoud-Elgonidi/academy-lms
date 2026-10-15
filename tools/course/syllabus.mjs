#!/usr/bin/env node
// Writes the course's syllabus (docs/course/README.md) from the series files, so
// the lessons that are filmed and the syllabus people read never drift apart.
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
export const ORDER = ['academy', 'teacher', 'guardian', 'student', 'platform'];
const PLACES = { desktop: 'شاشة الكمبيوتر', phone: 'الموبايل' };

export async function loadSeries() {
  return Promise.all(ORDER.map(async (key) => (await import(pathToFileURL(join(here, 'series', `${key}.mjs`)).href)).default));
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  const all = await loadSeries();
  const total = all.reduce((n, s) => n + s.lessons.length, 0);
  const lines = [
    '# كورس استخدام المنصة',
    '',
    `خمس سلاسل فيديو (${total} درس) بتشرح المنصة خطوة بخطوة لكل واحد حسب دوره. كل درس فيديو قصير (من دقيقتين لخمسة) متصوّر من الشاشات الحقيقية على بيانات الأكاديمية التجريبية، وفيه معلّق بيشرح بالتفصيل وهو شغال (صوت من ElevenLabs)، وعليه ترجمة قصيرة بالعامية المصرية، ومعاه ملف ترجمة (SRT) بالكلام اللي اتقال ونص التعليق.`,
    '',
    'الفيديوهات بتتعمل بأداة `tools/course` (شوف [الطريقة](#إزاي-الفيديوهات-بتتعمل) تحت)، والفهرس ده بيتكتب من نفس ملفات السلاسل (`node tools/course/syllabus.mjs`)، فاللي في الفيديو واللي هنا دايمًا واحد.',
    '',
    '| السلسلة | لمين | الدروس | بتتصوّر على |',
    '|---|---|---|---|',
    ...all.map((s) => `| [${s.title}](#${s.title.replace(/ /g, '-')}) | ${s.audience} | ${s.lessons.length} | ${PLACES[s.device]} |`),
    '',
  ];
  for (const s of all) {
    lines.push(`## ${s.title}`, '', `${s.audience} · ${PLACES[s.device]}`, '');
    for (const l of s.lessons) {
      lines.push(`### ${l.n}. ${l.title}${l.steps ? '' : ' *(لسه هيتصوّر)*'}`, '');
      if (l.who) lines.push(`*${l.who}*`, '');
      lines.push(l.goal, '');
      for (const item of l.learn ?? []) lines.push(`- ${item}`);
      lines.push('');
    }
  }
  lines.push(
    '## إزاي الفيديوهات بتتعمل',
    '',
    '- كل درس سكريبت خطوات في `tools/course/series/<السلسلة>.mjs`: «قول» (ترجمة على الشاشة)، و«دوس»، و«اكتب»، و«اختار»، و«انزل لتحت». الأداة بتشغّل الخطوات دي على الشاشات الحقيقية في متصفح، وبتصوّرها من Chrome نفسه (فريم مع كل تغيير، بوقته)، وبتظهر مؤشر وحلقة حوالين الحاجة اللي بنتكلم عنها.',
    '- بعد كده `ffmpeg` بيركّب: كارت العنوان، والشاشة جوه إطار السلسلة بزوايا مدوّرة، والترجمة تحتها (أو جنب الموبايل)، وكارت النهاية، والصوت. الناتج MP4 بدقة 1080p و30 فريم، ومعاه ملف SRT، ونص التعليق الصوتي بالتوقيتات، وصورة غلاف.',
    '- التشغيل: API شغال على بيانات تجريبية جديدة (`php artisan migrate:fresh --database=pgsql_owner --force && php artisan db:seed --force`)، وبعدين من `tools/course`:',
    '',
    '  ```sh',
    '  npm ci',
    '  export COURSE_PASSWORD=…          # كلمة سر حسابات الديمو',
    '  export COURSE_TOTP_OWNER=…        # مفتاح التحقق بخطوتين لصاحب الأكاديمية التجريبية',
    '  export ELEVENLABS_API_KEY=… ELEVENLABS_VOICE_ID=…   # المعلّق (اختياري)',
    '  node record.mjs academy 7          # سلسلة ودرس (أو أكتر)، أو السلسلة كلها من غير أرقام',
    '  ```',
    '',
    '  الناتج في `tools/course/out/<السلسلة>/` (برّه git).',
    '- التعليق الصوتي: كل خطوة ليها جملتين، «قول» القصيرة اللي بتظهر على الشاشة، و`voice:` اللي المعلّق بيقولها بالتفصيل (من غيرها بيقول نفس الترجمة)، ومعاهم ترحيب على كارت العنوان ووداع على كارت النهاية. الأداة بتطلب الصوت من ElevenLabs قبل التصوير، فكل خطوة بتستنى جملتها لحد ما تخلص، وبعدين كل جملة بتتحط على الفيديو في نفس لحظة ترجمتها، والصوت كله بيتظبط على مستوى واحد.',
    '- إعدادات الصوت من متغيرات البيئة: `ELEVENLABS_API_KEY` (المفتاح)، و`ELEVENLABS_VOICE_ID` (الصوت؛ `node lib/voice.mjs voices` بيعرض أصواتك والأصوات العربي في المكتبة)، و`ELEVENLABS_MODEL` (الافتراضي `eleven_v4`، لأن الصوت بيتكلم مصري عليه؛ على `eleven_multilingual_v2` بيتكلم فصحى)، و`ELEVENLABS_SPEED`، و`ELEVENLABS_API_BASE` (لحساب متخزّن في أوروبا: `https://api.eu.residency.elevenlabs.io`). من غير صوت، الفيديو بالترجمة بس. البيئة لازم تسمح بالدومين ده (`api.elevenlabs.io` لو مش متحدد)، والمفتاح بيتبعت في header اسمه `xi-api-key`.\n' +
    '- النطق: جمل `voice:` بالمصري زي ما بتتقال، والأرقام بالحروف (اتناشر، مش 12). الكلمة اللي ممكن تتقري فصحى أو بتشكيل غلط تتشكّل في `voice:` بس (بِيذاكِرها، قِفل)، والترجمة اللي على الشاشة تفضل من غير تشكيل، وعلامات «» مابتتبعتش للصوت (بتخلّيه يزوّد كلام). كل درس ممكن يبقى ليه صوت (`voice:` في الدرس، أو في السلسلة، وإلا `ELEVENLABS_VOICE_ID`)، وجملة `voice:` ممكن تبدأ بإحساس للمعلّق زي `[warmly]` أو `[cheerful]` أو `[excited]`: الموديل بيمشي عليه ومابيقولوش، وبيتشال من الترجمة ونص الدرس. قبل التصوير: `node listen.mjs <السلسلة> [أرقام]` بيرجّع كل جملة لكلام مكتوب (speech to text من ElevenLabs) ويطلّع الكلمات اللي اتسمعت غير المكتوب، فالغلطة (زي «بيضته» اتقالت «بيته») تتصلّح بتشكيل أو بكلمة تانية. بعض اللي بيطلّعه كتابة بس (أرقام، «ياخذ» بدل «ياخد»).',
    '- كل جملة اتقالت بتتحفظ في `tools/course/.voice/` (برّه git)، فإعادة تصوير درس مش بتتحاسب على الجمل اللي ما اتغيرتش.',
    '- كلمات السر ومفاتيح التحقق والمفاتيح بتيجي من متغيرات البيئة بس، عمرها ما تتكتب في الملفات.',
    '- الدروس اللي بتغيّر البيانات (تسجيل طالب مثلًا) بتتصوّر على بيانات تجريبية جديدة، بالترتيب.',
    '- درس الحصة المباشرة محتاج LiveKit شغال (`docker compose up -d livekit` من أول الريبو). المتصفح اللي بيصوّر ليه كاميرا ومايك وهميين، والمدرّسة بتدخل من متصفح تاني مش متصوّر (`s.joinAs`). ولو البيئة مش واصلة لـ`cdn.jsdelivr.net`، المكتبة بتتجاب من npm وتتحفظ في `.cdn/`.',
    '',
  );
  writeFileSync(join(here, '../../docs/course/README.md'), lines.join('\n'));
  console.log(`docs/course/README.md: ${all.length} series, ${total} lessons`);
}
