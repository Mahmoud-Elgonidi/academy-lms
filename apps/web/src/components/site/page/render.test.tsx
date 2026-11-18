import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { createTranslator, NextIntlClientProvider } from 'next-intl';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import ar from '../../../../messages/ar.json';
import en from '../../../../messages/en.json';
import { fixtureSite } from '@/lib/site/fixtures/site';
import { onAccent, type PageBlock, type SitePageData, type SitePreviewInfo } from '@/lib/site/page';
import type { AcademySite } from '@/lib/site/server';
import type { Branding } from '@/lib/viewer';
import type { PhoneWords } from '../phones';
import type { Say } from './context';
import { h1Of, headerModeOf, SitePageView } from './view';

// The form and its trial flows are today's, tested on their own; here it stands in.
vi.mock('../join-academy', () => ({ JoinAcademyForm: ({ teach }: { teach: boolean }) => <form data-form="" data-teach={teach ? '' : undefined} /> }));

const MESSAGES = { ar, en } as const;

const LOOK: Branding = {
  name: { ar: 'أكاديمية المدار', en: 'Madar Academy' },
  tokens: { primary: '#0D735E', attention: '#B7791F', danger: '#B42318', info: '#1D5FB8', neutral: 'stone', heading_font: 'IBM Plex Sans Arabic', body_font: 'IBM Plex Sans Arabic', radius: 'soft' },
  radii: { sm: '0.5rem', md: '0.75rem', lg: '1rem', xl: '1.5rem' },
  fonts_url: '',
};

function render(site: AcademySite, page: SitePageData, { locale = 'ar' as 'ar' | 'en', preview = null as SitePreviewInfo | null } = {}): string {
  const messages = MESSAGES[locale];
  const t = createTranslator({ locale, messages, namespace: 'site' }) as unknown as Say;
  const all = createTranslator({ locale, messages }) as unknown as Say;
  return renderToStaticMarkup(
    <NextIntlClientProvider locale={locale} messages={messages} timeZone="UTC">
      <SitePageView
        site={site}
        page={page}
        look={LOOK}
        locale={locale}
        t={t}
        phones={all.raw('sitePhones') as PhoneWords}
        accent="#0D735E"
        accentInk={onAccent('#0D735E')}
        preview={preview}
        utm={{}}
      />
    </NextIntlClientProvider>,
  );
}

const page = (name: string, locale: 'ar' | 'en' = 'ar') => {
  const site = fixtureSite(name, locale);
  return { site, page: site.page! };
};
const count = (html: string, needle: RegExp) => (html.match(needle) ?? []).length;

describe('the page as blocks', () => {
  it('draws every block type and variant without failing, in both languages', () => {
    for (const locale of ['ar', 'en'] as const) {
      const { site, page: doc } = page('all', locale);
      const html = render(site, doc, { locale });
      expect(html.length).toBeGreaterThan(10_000);
      for (const block of doc.blocks) {
        expect(html, `${block.type}.${block.variant}`).toContain(`id="${block.anchor}"`);
      }
    }
  });

  it('has exactly one h1, the hero’s title', () => {
    for (const name of ['all', 'courses', 'training', 'legacy']) {
      const { site, page: doc } = page(name);
      const html = render(site, doc);
      expect(count(html, /<h1[ >]/g), name).toBe(1);
    }
  });

  it('takes the first titled block for the h1 when there is no hero, else a hidden one with the academy’s name', () => {
    const { site, page: doc } = page('courses');
    const noHero = { ...doc, blocks: doc.blocks.filter((b) => b.type !== 'hero') };
    // The stats band has no title; the courses list after it has one.
    expect(h1Of(noHero.blocks)).toBe(noHero.blocks.find((b) => b.type === 'subjects')!.id);
    expect(count(render(site, noHero), /<h1[ >]/g)).toBe(1);
    const only = { ...doc, blocks: [{ id: 'sp1', type: 'spacer', variant: 'space', anchor: 'b-sp1', nav: null, style: doc.blocks[0]!.style, data: { size: 'sm' } } as PageBlock] };
    const html = render(site, only);
    expect(html).toMatch(/<h1 class="ls-sr">أكاديمية المدار<\/h1>/);
  });

  it('keeps today’s page for a migrated academy: the night hero with phones, the old anchors, the menu and labels', () => {
    const { site, page: doc } = page('legacy');
    const html = render(site, doc);
    expect(html).toContain('class="ls-hero"');
    expect(html).toContain('ls-phone');
    for (const anchor of ['why', 'subjects', 'teachers', 'start', 'app', 'pricing', 'testimonials', 'faq', 'join', 'teach']) {
      expect(html).toContain(`id="${anchor}"`);
    }
    expect(html).toContain('<a href="#why">لماذا نحن</a>');
    expect(html).toContain('class="ls-eyebrow">لماذا نحن</span>');
    expect(html).toContain('data-character="playful"');
    expect(html).not.toContain('sp-header');
  });

  it('skips a block type it does not know', () => {
    const { site, page: doc } = page('courses');
    const odd = { ...doc, blocks: [...doc.blocks, { id: 'zz', type: 'countdown', variant: 'x', anchor: 'b-zz', nav: null, style: doc.blocks[0]!.style, data: {} } as unknown as PageBlock] };
    expect(() => render(site, odd)).not.toThrow();
    expect(render(site, odd)).not.toContain('b-zz');
  });

  it('draws no trial button when the page has no form', () => {
    const { site, page: doc } = page('courses');
    const noForm = { ...doc, blocks: doc.blocks.filter((b) => b.type !== 'form') };
    const html = render(site, noForm);
    expect(html).not.toContain('href="#join"');
    expect(render(site, doc)).toContain('href="#join"');
  });

  it('opens the "teach with us" tab only when the form shows it', () => {
    const { site, page: doc } = page('legacy');
    expect(render(site, doc)).toContain('data-teach=""');
    const noTeach = { ...doc, blocks: doc.blocks.map((b) => (b.type === 'form' ? { ...b, data: { ...b.data, teach: false } } : b)) as PageBlock[] };
    const html = render(site, noTeach);
    expect(html).not.toContain('data-teach=""');
    expect(html).not.toContain('href="#teach"');
  });

  it('marks the preview, and keeps its form from sending', () => {
    const { site, page: doc } = page('courses');
    const html = render(site, doc, { preview: { revision: 3, published_revision: 2, enabled: false } });
    expect(html).toContain('معاينة: لم تُنشر بعد');
    expect(html).toContain('الصفحة مطفأة الآن');
    expect(html).toContain('sp-preview-guard');
    expect(render(site, doc)).not.toContain('sp-preview-guard');
  });

  it('writes the rich text as elements and drops links it does not allow', () => {
    const { site, page: doc } = page('all');
    const rich = {
      ...doc,
      blocks: [{
        id: 'rt', type: 'rich_text', variant: 'article', anchor: 'b-rt', nav: null, style: doc.blocks[0]!.style,
        data: { eyebrow: null, title: 'T', lead: null, body: [
          { t: 'p', c: [{ text: '<img src=x onerror=alert(1)>' }, { text: 'bad', href: 'javascript:alert(1)' }, { text: 'ok', href: 'https://example.com', b: true }] },
          { t: 'ul', items: [[{ text: 'one' }]] },
        ] },
      } as PageBlock],
    };
    const html = render(site, rich);
    expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
    expect(html).not.toContain('javascript:');
    expect(html).toContain('<a href="https://example.com" target="_blank" rel="noopener"><strong>ok</strong></a>');
    expect(html).toContain('<ul><li>one</li></ul>');
  });

  it('puts the header over a dark opening, and a solid bar otherwise', () => {
    const legacy = page('legacy').page;
    expect(headerModeOf(legacy, 'light')).toBe('night');
    const courses = page('courses').page;
    expect(headerModeOf(courses, 'light')).toBe('solid');
    expect(headerModeOf({ ...courses, settings: { ...courses.settings, header: { ...courses.settings.header, style: 'overlay' } } }, 'light')).toBe('light');
    expect(headerModeOf(page('training').page, 'light')).toBe('night');
  });

  it('loads nothing from YouTube or Vimeo before a click, and never autoplays the hero in the markup', () => {
    const { site, page: doc } = page('all');
    const html = render(site, doc);
    expect(html).not.toContain('youtube-nocookie.com/embed');
    expect(html).not.toContain('player.vimeo.com');
    const training = page('training');
    const hero = render(training.site, training.page);
    expect(hero).toContain('preload="none"');
    expect(hero).not.toMatch(/<video[^>]*autoplay/);
  });
});

describe('the opening and the header, after a look at the real API', () => {
  it('stands a split hero without its picture in the middle (no empty half)', () => {
    const { site, page: doc } = page('courses');
    const blocks = doc.blocks.map((b) => (b.type === 'hero' ? { ...b, variant: 'split' as const, style: { ...b.style, align: 'start' as const }, data: { ...b.data, media: null, video: null } } : b));
    const html = render(site, { ...doc, blocks });
    expect(html).toMatch(/data-block="hero" data-variant="centered" data-tone="[a-z]+" data-align="center"/);
  });

  it('gives an editorial page over a dark opening the phone menu, and keeps today’s header without one', () => {
    const training = page('training');
    // The training fixture names no block in its menu; give one a label.
    const blocks = training.page.blocks.map((b, i) => (i === 1 ? { ...b, nav: 'Courses' } : b));
    const html = render(training.site, { ...training.page, blocks });
    expect(headerModeOf(training.page, 'light')).toBe('night');
    expect(html).toContain('class="sp-menu"');
    const legacy = page('legacy');
    expect(render(legacy.site, legacy.page)).not.toContain('class="sp-menu"');
  });
});

describe('the page’s code', () => {
  it('never writes raw HTML (no dangerouslySetInnerHTML under components/site/page)', () => {
    const dir = join(__dirname);
    const files: string[] = [];
    const walk = (d: string) => {
      for (const name of readdirSync(d)) {
        const path = join(d, name);
        if (statSync(path).isDirectory()) walk(path);
        else if (/\.tsx?$/.test(name) && !name.endsWith('.test.tsx')) files.push(path);
      }
    };
    walk(dir);
    expect(files.length).toBeGreaterThan(20);
    for (const file of files) expect(readFileSync(file, 'utf8'), file).not.toContain('dangerouslySetInnerHTML');
  });
});
