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
  '# قايمة الاعتماد اليدوية',
  '',
  'الحالات اللي بيتجرّب بيها النظام بالإيد على السيرفر قبل اعتماده. المصدر `cases.json`، والملف ده بيتكتب منه بـ `node docs/testing/render.mjs` (ماتعدّلوش بإيدك).',
  '',
  `**${cases.length} حالة:** P0 ${count(cases, 'P0')}، وP1 ${count(cases, 'P1')}، وP2 ${count(cases, 'P2')}. منهم ${cases.filter((c) => c.edge).length} حالة حدّية.`,
  '',
  '**شرط الاعتماد:** كل حالات P0 عدّت، ومفيش حالة P0 ولا P1 لسه واقعة.',
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
    out.push(`**${c.priority}**${c.edge ? ' · حدّي' : ''} · **مين:** ${c.who} · **فين:** ${c.where}`, '');
    if (c.pre) out.push(`**قبلها:** ${c.pre}`, '');
    out.push('**الخطوات:**', '', ...c.steps.map((s, i) => `${i + 1}. ${s}`), '');
    out.push('**المفروض يحصل:**', '', ...c.expect.map((e) => `- ${e}`), '');
    if (c.rule) out.push(`<sub>المرجع: ${c.rule}</sub>`, '');
  }
}

writeFileSync(join(here, 'ACCEPTANCE.md'), out.join('\n'));
console.log(`ACCEPTANCE.md: ${cases.length} cases in ${phases.length} phases`);
