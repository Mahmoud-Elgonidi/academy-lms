import { NextIntlClientProvider } from 'next-intl';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import ar from '../../../../messages/ar.json';
import en from '../../../../messages/en.json';
import type { Bookers } from '@/lib/site/page';
import { JoinAcademyForm } from '../join-academy';
import { FieldError, placeRefusals } from './trial-request';
import { SCHOOL_AR, SCHOOL_EN, refusedSchool } from './school-words';

/*
 * The trial form on a page for adults (site-builder.md, 9.2) speaks to
 * professionals: a session, a trainer, an area, never a lesson, a teacher,
 * a subject, a student, a child or a guardian, in every step, hint, button
 * and confirmation (`site.adults.trial.*`, `site.adults.join.*` over the
 * usual words). A families page keeps its words, and the form block's
 * choice of who books shows or hides the "for whom?" question.
 */

// The form's Server Actions are the server's; only the words are drawn here.
vi.mock('@/lib/site/actions', () => ({ askForTrial: vi.fn(), bookTrial: vi.fn(), sendTrialCode: vi.fn(), trialTimes: vi.fn(), askTheAcademy: vi.fn() }));

const MESSAGES = { ar, en } as const;
const TRIALS = { booking: 'family', response_hours: 4, notice_hours: 3, help_choose_subject: true, help_choose_subject_id: 's1' } as const;

function form(locale: 'ar' | 'en', who: Bookers, adultsWords: boolean, teach = false): string {
  return renderToStaticMarkup(
    <NextIntlClientProvider locale={locale} messages={MESSAGES[locale]} timeZone="UTC">
      <JoinAcademyForm
        token="t"
        subjects={[{ id: 's1', name: 'Risk' }]}
        countries={[]}
        utm={{}}
        teach={teach}
        trials={TRIALS}
        whatsapp="+966500000000"
        academy="Risk Centre"
        timeFormat="h12"
        who={who}
        adultsWords={adultsWords}
      />
    </NextIntlClientProvider>,
  );
}

const readable = (html: string) => [html.replace(/<[^>]*>/g, ' '), ...[...html.matchAll(/(?:alt|aria-label|title|placeholder)="([^"]*)"/g)].map((m) => m[1])].join(' ');

/** A message as read, without its placeholders ({subject}) and tags (<name>). */
const said = (text: string) => text.replace(/\{\w+\}/g, ' ').replace(/<\/?\w+>/g, ' ');

type Tree = { [key: string]: string | string[] | Tree };
/** Every string under a node, with its path. */
function strings(node: Tree | string | string[], path = ''): [string, string][] {
  if (typeof node === 'string') return [[path, node]];
  if (Array.isArray(node)) return node.map((text, i) => [`${path}.${i}`, text]);
  return Object.entries(node).flatMap(([key, value]) => strings(value, path ? `${path}.${key}` : key));
}
/** The usual words with the adults' own laid over them, as the page for adults reads them. */
function overlaid(base: Tree, over: Tree | undefined): Tree {
  const out: Tree = { ...base };
  for (const [key, value] of Object.entries(over ?? {})) {
    out[key] = typeof value === 'object' && !Array.isArray(value) && typeof base[key] === 'object' && !Array.isArray(base[key]) ? overlaid(base[key] as Tree, value) : value;
  }
  return out;
}

/*
 * What the adults' flow never says: the "for whom?" choice and the child's
 * fields (an adults-only form), and the language switch's name. The pick and
 * lesson pages reached by link are the flow's too (`speaksToAdults`).
 */
const NOT_IN_ADULTS_FLOW = [
  /^who\.(title|lead|titleChildren|leadChildren|child|childHint|me|meHint|parentName|childName|childAge|agePick|years)$/,
  /^booked\.(titleChild|another)$/,
  /^errors\.(childName|childAge)$/,
  /^language$/,
];
const JOIN_NOT_IN_ADULTS_FLOW = [/^fields\.(for_child|child_name|child_birth_year|subject_id)$/, /^pointsSelf\./];

describe('the trial form for adults', () => {
  for (const locale of ['ar', 'en'] as const) {
    const site = MESSAGES[locale].site as unknown as Tree;
    const adults = site.adults as Tree;

    it(`says every word of its flow in training words: ${locale}`, () => {
      const trial = strings(overlaid(site.trial as Tree, adults.trial as Tree)).filter(([path]) => !NOT_IN_ADULTS_FLOW.some((rule) => rule.test(path)));
      const join = strings(overlaid(site.join as Tree, adults.join as Tree)).filter(([path]) => !JOIN_NOT_IN_ADULTS_FLOW.some((rule) => rule.test(path)));
      const wrong = [...trial, ...join].flatMap(([path, text]) => refusedSchool(said(text)).map((word) => `${path}: ${word}`));
      expect(wrong).toEqual([]);
    });

    it(`opens on the visitor's own details, with no "for whom?" and no school word: ${locale}`, () => {
      const html = form(locale, 'adults', true, true);
      expect(html).not.toContain('name="who"');
      expect(html).toContain(MESSAGES[locale].site.trial.who.titleAdults);
      expect(html).toContain(MESSAGES[locale].site.adults.trial.who.subject);
      expect(html).toContain(MESSAGES[locale].site.adults.join.tabs.trial);
      expect(refusedSchool(readable(html))).toEqual([]);
    });
  }

  it('keeps a families page as it was: the "for whom?" choice and its words', () => {
    const html = form('ar', 'both', false);
    expect(html).toContain('name="who"');
    expect(html).toContain(ar.site.trial.who.title);
    expect(html).toContain(ar.site.trial.who.subject);
    expect(html).toContain(ar.site.trial.who.child);
  });

  it('asks a guardian for the child alone when the form takes children only', () => {
    const html = form('ar', 'children', false);
    expect(html).not.toContain('name="who"');
    expect(html).toContain(ar.site.trial.who.titleChildren);
    expect(html).toContain(ar.site.trial.who.parentName);
    expect(html).toContain(ar.site.trial.who.childName);
    expect(html).not.toContain(ar.site.trial.who.me);
  });

  it("says why the page's form refused who the trial is for, on the first step", () => {
    // The API's sentences (site::site.errors.adults_only / children_only), on the key for_child.
    const adultsOnly = 'هذا النموذج لمن يحجز لنفسه: اكتب اسمك أنت وأرسل الطلب، أو تواصل مع الأكاديمية لترتيب غير ذلك.';
    const childrenOnly = 'هذا النموذج لحجز حصة لطفل: اكتب اسم الطفل وعمره وأرسل الطلب. وإن كانت الحصة لك فتواصل مع الأكاديمية.';
    for (const message of [adultsOnly, childrenOnly]) {
      const placed = placeRefusals({ for_child: message }, ['who', 'when', 'contact']);
      expect(placed).toEqual({ at: 'who', wrong: { who: message } });
      const html = renderToStaticMarkup(<FieldError field="who" message={placed.wrong.who} />);
      expect(html).toContain('id="lt-who-error"');
      expect(html).toContain(message);
    }
    expect(renderToStaticMarkup(<FieldError field="who" message={undefined} />)).toBe('');
    expect(placeRefusals({ starts_at: 'x' }, ['who', 'contact'])).toEqual({ at: null, wrong: {} });
  });

  it('sees the school words where they are', () => {
    expect(refusedSchool('احجز حصتك مع المعلّم، والطالب في الصف، وواجب المادة')).toEqual(['حصتك', 'المعلم', 'والطالب', 'الصف', 'وواجب', 'المادة']);
    expect(refusedSchool('جلسة تعريفية مع مدرّب في مجالك، وصفحتنا ووصف البرنامج')).toEqual([]);
    expect(refusedSchool('Book a Lesson with the teacher at school, or a session with a trainer')).toEqual(['Lesson', 'teacher', 'school']);
    expect(SCHOOL_AR).toBeInstanceOf(RegExp);
    expect(SCHOOL_EN).toBeInstanceOf(RegExp);
  });
});
