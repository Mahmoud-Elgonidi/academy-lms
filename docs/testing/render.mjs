// Writes ACCEPTANCE.md from cases.json (the one source of the manual acceptance cases).
// node docs/testing/render.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const { phases, cases } = JSON.parse(readFileSync(join(here, 'cases.json'), 'utf8'));

const ids = new Set();
for (const c of cases) {
  if (ids.has(c.id)) throw new Error(`Duplicate case id ${c.id}`);
  ids.add(c.id);
  if (!phases.some((p) => p.n === c.phase)) throw new Error(`${c.id}: no phase ${c.phase}`);
  if (!c.steps?.length || !c.expect?.length) throw new Error(`${c.id}: steps and expect are required`);
}

const count = (list, p) => list.filter((c) => c.priority === p).length;
const out = [
  '# قائمة الاعتماد اليدوية',
  '',
  'الحالات التي يُجرَّب بها النظام يدويًا على الخادم قبل اعتماده. المصدر `cases.json`، ويُكتب هذا الملف منه بـ `node docs/testing/render.mjs` (لا تعدّله يدويًا).',
  '',
  `**${cases.length} حالة:** P0 ${count(cases, 'P0')}، وP1 ${count(cases, 'P1')}، وP2 ${count(cases, 'P2')}. منها ${cases.filter((c) => c.edge).length} حالة حدّية.`,
  '',
  '**شرط الاعتماد:** نجاح كل حالات P0، وألا تبقى أي حالة P0 أو P1 فاشلة.',
  '',
  '## المراحل',
  '',
  ...phases.map((p) => `${p.n}. [${p.title}](#${p.n}-${p.title.replace(/\s+/g, '-')}) (${cases.filter((c) => c.phase === p.n).length})`),
  '',
];

for (const p of phases) {
  const list = cases.filter((c) => c.phase === p.n);
  out.push(`## ${p.n}. ${p.title}`, '', p.about, '');
  let area = null;
  for (const c of list) {
    if (c.area !== area) { area = c.area; out.push(`### ${area}`, ''); }
    out.push(`#### - [ ] \`${c.id}\` ${c.title}`, '');
    out.push(`**${c.priority}**${c.edge ? ' · حدّي' : ''} · **مَن:** ${c.who} · **أين:** ${c.where}`, '');
    if (c.pre) out.push(`**يسبقها:** ${c.pre}`, '');
    out.push('**الخطوات:**', '', ...c.steps.map((s, i) => `${i + 1}. ${s}`), '');
    out.push('**النتيجة المتوقعة:**', '', ...c.expect.map((e) => `- ${e}`), '');
    if (c.rule) out.push(`<sub>المرجع: ${c.rule}</sub>`, '');
  }
}

writeFileSync(join(here, 'ACCEPTANCE.md'), out.join('\n'));
console.log(`ACCEPTANCE.md: ${cases.length} cases in ${phases.length} phases`);
