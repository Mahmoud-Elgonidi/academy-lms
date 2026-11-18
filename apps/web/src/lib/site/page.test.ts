import { describe, expect, it } from 'vitest';
import {
  characterOf, cssRatio, defaultKey, embedUrl, isKnownBlock, jsonLd, lightnessOf, navOf, onAccent, ratioOf, resolveAction, safeUrl,
  say, sayList, structuredData, toneOf, whatsappUrl, type LinkContext, type PageBlock, type SitePageData,
} from './page';
import { fixtureSite } from './fixtures/site';

const ctx = (over: Partial<LinkContext> = {}): LinkContext => ({ locale: 'ar', whatsapp: '+966500000000', form: true, teach: false, anchors: new Set(['why', 'b-x1']), ...over });

describe('where a button leads', () => {
  it('sends a trial to the form, signing in to its page, WhatsApp to the chat', () => {
    expect(resolveAction({ kind: 'trial' }, ctx())).toEqual({ href: '#join', external: false });
    expect(resolveAction({ kind: 'sign_in' }, ctx({ locale: 'en' }))).toEqual({ href: '/en/sign-in', external: false });
    expect(resolveAction({ kind: 'whatsapp' }, ctx())).toEqual({ href: 'https://wa.me/966500000000', external: true });
  });

  it('drops a button that cannot work on the page', () => {
    expect(resolveAction({ kind: 'trial' }, ctx({ form: false }))).toBeNull();
    expect(resolveAction({ kind: 'teach' }, ctx({ teach: false }))).toBeNull();
    expect(resolveAction({ kind: 'teach' }, ctx({ teach: true }))).toEqual({ href: '#teach', external: false });
    expect(resolveAction({ kind: 'whatsapp' }, ctx({ whatsapp: null }))).toBeNull();
    expect(resolveAction({ kind: 'block', block: 'gone' }, ctx())).toBeNull();
    expect(resolveAction({ kind: 'block', block: 'why' }, ctx())).toEqual({ href: '#why', external: false });
  });

  it('opens another host in a new tab and keeps this portal’s pages in place', () => {
    expect(resolveAction({ kind: 'url', url: 'https://example.com/a' }, ctx())).toEqual({ href: 'https://example.com/a', external: true });
    expect(resolveAction({ kind: 'url', url: '/en/sign-in' }, ctx())).toEqual({ href: '/en/sign-in', external: false });
  });

  it('never writes an address with a scheme the page refuses (defence in depth)', () => {
    for (const bad of ['javascript:alert(1)', 'JAVASCRIPT:alert(1)', 'data:text/html,x', 'http://example.com', '//evil.example', '/fr/x', 'vbscript:x', 'https://a b']) {
      expect(safeUrl(bad)).toBeNull();
      expect(resolveAction({ kind: 'url', url: bad }, ctx())).toBeNull();
    }
    expect(safeUrl('mailto:hello@example.com')).toBe('mailto:hello@example.com');
    expect(safeUrl('tel:+966112345678')).toBe('tel:+966112345678');
    expect(safeUrl('tel:12345')).toBeNull();
    expect(safeUrl(`https://example.com/${'a'.repeat(600)}`)).toBeNull();
  });

  it('makes a WhatsApp chat only from a real number', () => {
    expect(whatsappUrl('+20 100 123 4567')).toBe('https://wa.me/201001234567');
    expect(whatsappUrl('')).toBeNull();
    expect(whatsappUrl(null)).toBeNull();
  });
});

describe('the default wording', () => {
  it('uses the academy’s words, else the portal’s for its audience', () => {
    expect(say('مرحبًا', () => 'default')).toBe('مرحبًا');
    expect(say(null, () => 'default')).toBe('default');
    expect(say('   ', () => 'default')).toBe('default');
    expect(defaultKey('why.title', 'adults')).toBe('adults.why.title');
    expect(defaultKey('why.title', 'families')).toBe('why.title');
    expect(defaultKey('subjects.title', 'adults')).toBe('subjects.title');
  });

  it('keeps a list never set (the default) apart from one cleared on purpose', () => {
    expect(sayList(null, () => ['a'])).toEqual(['a']);
    expect(sayList([], () => ['a'])).toEqual([]);
    expect(sayList(['b'], () => ['a'])).toEqual(['b']);
  });
});

describe('ink on the accent', () => {
  it('is dark on light colours and white on mid and dark ones', () => {
    expect(onAccent('#F5D90A')).toBe('dark');
    expect(onAccent('#FFE066')).toBe('dark');
    expect(onAccent('#1D5FB8')).toBe('light');
    expect(onAccent('#0D735E')).toBe('light');
    expect(onAccent('#111827')).toBe('light');
    expect(onAccent('not a colour')).toBe('light');
  });

  it('measures lightness from black to white', () => {
    expect(lightnessOf('#000000')).toBeCloseTo(0, 3);
    expect(lightnessOf('#ffffff')).toBeCloseTo(1, 3);
  });
});

describe('the page’s shape', () => {
  const site = fixtureSite('courses', 'ar');
  const page = site.page!;

  it('reads as editorial for courses and playful for today’s page', () => {
    expect(characterOf(page)).toBe('editorial');
    expect(characterOf(fixtureSite('legacy', 'ar').page!)).toBe('playful');
  });

  it('turns paper blocks to night in a night mood', () => {
    const paper = page.blocks.find((b) => b.style.tone === 'paper')!;
    expect(toneOf(paper, 'light')).toBe('paper');
    expect(toneOf(paper, 'night')).toBe('night');
  });

  it('lists in the menu only blocks the academy labelled, in order', () => {
    expect(navOf(page.blocks).map((n) => n.label)).toEqual(['الدورات', 'لماذا نحن', 'المعلّمون', 'الأسعار', 'أسئلة']);
  });

  it('skips a block type it does not know', () => {
    expect(isKnownBlock({ type: 'hero' })).toBe(true);
    expect(isKnownBlock({ type: 'countdown' })).toBe(false);
  });

  it('holds a picture’s box from its sizes', () => {
    expect(ratioOf({ url: '/a.webp', width: 1600, height: 900, alt: '', mime: 'image/webp' })).toBe('1600 / 900');
    expect(ratioOf(null)).toBe('16 / 9');
    expect(cssRatio('4:3')).toBe('4 / 3');
    expect(cssRatio('auto')).toBeNull();
  });

  it('opens YouTube without cookies and Vimeo without tracking, and only for well-formed ids', () => {
    expect(embedUrl({ kind: 'youtube', id: 'dQw4w9WgXcQ', poster: null })).toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0');
    expect(embedUrl({ kind: 'vimeo', id: '76979871', poster: null })).toBe('https://player.vimeo.com/video/76979871?autoplay=1&dnt=1');
    expect(embedUrl({ kind: 'youtube', id: '"><script>', poster: null })).toBeNull();
  });
});

describe('structured data', () => {
  it('names the academy and its questions, and no text can close the script', () => {
    const page: SitePageData = {
      ...fixtureSite('courses', 'en').page!,
      blocks: [{ id: 'f1', type: 'faq', variant: 'accordion', anchor: 'faq', nav: null, style: { tone: 'white', align: 'start', space: 'normal', width: 'normal', eyebrow: false }, data: { eyebrow: null, title: null, lead: null, items: [{ q: 'Is it </script><script>alert(1)</script>?', a: 'No.' }] } } as PageBlock],
    };
    const data = structuredData(page, { name: 'Madar' }, 'https://madar.example', 'en', '/storage/logo.png');
    expect(data).toHaveLength(2);
    expect(data[0]).toMatchObject({ '@type': 'EducationalOrganization', name: 'Madar', url: 'https://madar.example/en', logo: 'https://madar.example/storage/logo.png' });
    const text = jsonLd(data[1]);
    expect(text).not.toContain('</script>');
    expect(JSON.parse(text)).toMatchObject({ '@type': 'FAQPage', mainEntity: [{ name: 'Is it </script><script>alert(1)</script>?' }] });
  });
});
