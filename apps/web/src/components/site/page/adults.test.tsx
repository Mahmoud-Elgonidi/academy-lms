import { createTranslator, NextIntlClientProvider } from 'next-intl';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import ar from '../../../../messages/ar.json';
import en from '../../../../messages/en.json';
import { fixtureSite } from '@/lib/site/fixtures/site';
import { ageGroupsFor, onAccent, type PageBlock, type SitePageData } from '@/lib/site/page';
import type { AcademySite } from '@/lib/site/server';
import type { Branding } from '@/lib/viewer';
import { screensFor, type PhoneWords } from '../phones';
import { refusedSchool } from '../trial/school-words';
import type { Say } from './context';
import { SitePageView } from './view';

/*
 * A page for adults (site-builder.md, 9.2) never speaks of children, parents
 * or families: not in the academy's blocks, not in the portal's own words,
 * not on the phones of the opening or the app block (the learner's own
 * screens, no guardian, no child, no game), not in a teacher's age groups.
 * The families' page keeps its guardian and child screens.
 */

// The form and its trial flows are tested on their own (and speak to adults without the "for my child" choice).
vi.mock('../join-academy', () => ({ JoinAcademyForm: () => <form data-form="" /> }));

const MESSAGES = { ar, en } as const;
const LOOK: Branding = {
  name: { ar: 'مركز المخاطر', en: 'Risk Centre' },
  tokens: { primary: '#1D5FB8', attention: '#B7791F', danger: '#B42318', info: '#1D5FB8', neutral: 'slate', heading_font: 'IBM Plex Sans Arabic', body_font: 'IBM Plex Sans Arabic', radius: 'soft' },
  radii: { sm: '0.5rem', md: '0.75rem', lg: '1rem', xl: '1.5rem' },
  fonts_url: '',
};

/** The refused words as whole words: Arabic with its attached letters (و ف ب ل ال; a word ends at any non-letter, the Arabic comma too), English any case. */
const AR = /(?<![\u0621-\u065F\u0671-\u06D3])[وفب]?(?:لل|ال|ل|ك)?(?:ابن|ابنك|ابنتك|ابني|ابنتي|ابنة|أبناء|أبنائك|أبنائكم|طفل|طفلك|طفلة|أطفال|أطفالك|ولي الأمر|ولي أمر|ولية الأمر|ولية أمر|أولياء|أسرة|أسرتك|أسر|عائلة|عائلتك|عائلات|صغار)(?![\u0621-\u065F\u0671-\u06D3])/gu;
const EN = /\b(?:child|children|kid|kids|parent|parents|guardian|guardians|family|families|son|sons|daughter|daughters)\b/gi;

/** What a visitor reads or hears: the text between tags, and the alt, label and title words. */
function readable(html: string): string {
  const said = [...html.matchAll(/(?:alt|aria-label|title|placeholder)="([^"]*)"/g)].map((m) => m[1]);
  return [html.replace(/<[^>]*>/g, ' '), ...said].join(' ');
}
const refused = (text: string) => [...(text.match(AR) ?? []), ...(text.match(EN) ?? [])];

function translators(locale: 'ar' | 'en') {
  const messages = MESSAGES[locale];
  return {
    messages,
    t: createTranslator({ locale, messages, namespace: 'site' }) as unknown as Say,
    phones: (createTranslator({ locale, messages }) as unknown as Say).raw('sitePhones') as PhoneWords,
  };
}

function render(site: AcademySite, page: SitePageData, locale: 'ar' | 'en'): string {
  const { messages, t, phones } = translators(locale);
  return renderToStaticMarkup(
    <NextIntlClientProvider locale={locale} messages={messages} timeZone="UTC">
      <SitePageView site={site} page={page} look={LOOK} locale={locale} t={t} phones={phones} accent="#1D5FB8" accentInk={onAccent('#1D5FB8')} preview={null} utm={{}} />
    </NextIntlClientProvider>,
  );
}

/** An adult page at its fullest: the phones opening, the app's phones, steps, form and testimonials with the portal's words, teachers with their ages. */
function adultPage(name: 'courses' | 'training', locale: 'ar' | 'en') {
  const site = fixtureSite(name, locale);
  // A teacher who also teaches children and teens.
  site.teachers = site.teachers.map((teacher, i) => (i === 0 ? { ...teacher, age_groups: ['kids', 'middle', 'teen', 'adults'] } : teacher));
  const page = site.page!;
  const base = page.blocks[0]!;
  const extra = (type: PageBlock['type'], variant: string, data: object): PageBlock => ({ ...base, id: `x${type}`.slice(0, 8), anchor: `b-x${type}`, type, variant, nav: null, data } as unknown as PageBlock);
  const H = { eyebrow: null, title: null, lead: null };
  const blocks: PageBlock[] = page.blocks.map((block) => {
    if (block.type === 'hero') return { ...block, variant: 'phones', data: { ...block.data, kicker: null, trust: null, show: { kicker: true, lead: true, buttons: true, trust: true, media: true } } } as PageBlock;
    // The fixture's quotes are an academy's own words (one is a parent's); an adult academy's are its learners'.
    if (block.type === 'testimonials') return { ...block, data: { ...block.data, title: null, items: [{ name: 'Rana', role: null, quote: 'Useful.', photo: null, rating: 5 }] } } as PageBlock;
    return block;
  });
  blocks.push(
    extra('app', 'phones', { ...H, points: null, show_code: true, show_stores: true, media: null }),
    extra('steps', 'line', { ...H, items: null }),
    extra('features', 'cards', { ...H, columns: 3, items: [{ icon: 'users', title: 'x', text: '' }] }),
    extra('teachers', 'cards', { ...H, show_bio: true, show_languages: true, show_ages: true, show_badges: true }),
    extra('subjects', 'cards', { ...H, subject_ids: [], show_levels: true, show_programs: true, show_categories: true, ask_label: null }),
    extra('pricing', 'cards', { ...H, highlight: null, highlight_label: null, per_lesson: true, note: null }),
    extra('testimonials', 'grid', { ...H, items: [{ name: 'Rana', role: null, quote: 'Useful.', photo: null, rating: 5 }] }),
    extra('faq', 'accordion', { ...H, items: [{ q: 'Q?', a: 'A.' }] }),
    extra('form', 'split', { ...H, points: null, teach: false, whatsapp_button: true }),
    extra('contact', 'cards', { ...H, email: 'hello@example.com', phone: null, address: null, hours: null, whatsapp: true }),
  );
  return { site, page: { ...page, settings: { ...page.settings, audience: 'adults' as const }, blocks } };
}

describe('a page for adults', () => {
  for (const name of ['courses', 'training'] as const) {
    for (const locale of ['ar', 'en'] as const) {
      it(`says nothing of children, parents or families: ${name}, ${locale}`, () => {
        const { site, page } = adultPage(name, locale);
        const html = render(site, page, locale);
        expect(html).toContain('ls-phone');
        expect(html).toContain('ls-s-cert');
        expect(refused(readable(html))).toEqual([]);
      });
    }
  }

  // An academy for adults trains institutions and professionals: the trial section's own words (site.adults.join) say no school word either.
  for (const locale of ['ar', 'en'] as const) {
    for (const variant of ['split', 'centered'] as const) {
      it(`says the trial section in training words, never a school's: ${variant}, ${locale}`, () => {
        const { site, page } = adultPage('training', locale);
        const blocks = page.blocks.map((block) => (block.type === 'form' ? { ...block, variant, style: { ...block.style, eyebrow: true } } as PageBlock : block));
        for (const booking of ['family', 'staff'] as const) {
          const html = render({ ...site, trials: site.trials ? { ...site.trials, booking } : site.trials }, { ...page, blocks }, locale);
          const section = html.slice(html.indexOf('data-block="form"'));
          const form = section.slice(0, section.indexOf('</section>'));
          expect(form).toContain(MESSAGES[locale].site.adults.join.points[0]);
          expect(refusedSchool(readable(form))).toEqual([]);
          expect(refused(readable(form))).toEqual([]);
        }
      });
    }
  }

  it('shows the learner’s own screens on the phones, never a guardian’s, a child’s or a game', () => {
    for (const locale of ['ar', 'en'] as const) {
      const { phones } = translators(locale);
      const set = screensFor('adults', phones);
      const html = renderToStaticMarkup(<>{set.start}{set.center}{set.end}</>);
      expect(refused(readable(html))).toEqual([]);
      expect(html).not.toContain('ls-s-sky');            // the child's island
      expect(html).not.toContain(phones.guardian.child);  // "following Yusuf"
      expect(html).toContain(phones.adults.learner.title);
      expect(html).toContain(phones.adults.session.certificate);
      expect(html).not.toContain(phones.teacher.lessons[0]);
    }
  });

  it('keeps the families’ guardian, child and journey screens', () => {
    const { phones } = translators('ar');
    const set = screensFor('families', phones);
    const html = renderToStaticMarkup(<>{set.start}{set.center}{set.end}</>);
    expect(html).toContain('ls-s-sky');
    expect(html).toContain(phones.guardian.child);
    expect(html).toContain(phones.guardian.reportLine);
  });

  it('sees the refused words where they are', () => {
    expect(refused('ابنتي تنتظر الحصة، وولية أمر، وللأسرة، وأسرع طريق')).toEqual(['ابنتي', 'وولية أمر', 'وللأسرة']);
    expect(refused('My daughter, a Parent and the family')).toEqual(['daughter', 'Parent', 'family']);
  });

  it('names only the adults’ age group on a teacher’s card', () => {
    expect(ageGroupsFor(['kids', 'teen', 'adults'], 'adults')).toEqual(['adults']);
    expect(ageGroupsFor(['kids', 'teen'], 'adults')).toEqual([]);
    expect(ageGroupsFor(['kids', 'teen'], 'families')).toEqual(['kids', 'teen']);
  });
});

describe('the cards block', () => {
  it('draws the three designs, a picture where there is one, and a button that opens the whole card', () => {
    const { site, page } = { site: fixtureSite('all', 'ar'), page: fixtureSite('all', 'ar').page! };
    const html = render(site, page, 'ar');
    for (const variant of ['grid', 'list', 'overlay']) {
      expect(html).toMatch(new RegExp(`class="sp-cards" data-variant="${variant}"`));
    }
    expect(html).toContain('إدارة المخاطر');
    expect(html).toContain('class="sp-card-label">6 أسابيع</p>');
    expect(html).toMatch(/<a class="sp-card-link" href="#join"><span>اطلب التفاصيل<\/span><\/a>/);
    expect(html).toMatch(/<a class="sp-card-link" href="https:\/\/example.com\/continuity" target="_blank" rel="noopener"><span>اعرف المزيد<\/span><\/a>/);
    // A card with no button is not a link.
    expect(html).toMatch(/<li class="sp-card" data-reveal="" style="--i:1"><div class="sp-card-words"><h3>دورة في مجموعة<\/h3><\/div><\/li>/);
  });
});
