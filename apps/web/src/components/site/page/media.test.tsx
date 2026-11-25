import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import type { Media } from '@/lib/site/page';
import { Picture } from './media';
import { BackgroundVideo, FramedVideo } from './player';

/* A see-through image (a PNG cut-out) sits on the band's own colour: its frame drops the loading grey; a photo keeps it. */
describe('Picture', () => {
  const media = (mime: string): Media => ({ url: '/storage/x', width: 800, height: 600, alt: '', mime });

  it('lets a PNG, WebP, GIF or SVG show the band behind it', () => {
    for (const mime of ['image/png', 'image/webp', 'image/gif', 'image/svg+xml']) {
      expect(renderToStaticMarkup(<Picture media={media(mime)} />), mime).toContain('data-clear=""');
    }
  });

  it('keeps the grey behind a JPEG while it loads', () => {
    expect(renderToStaticMarkup(<Picture media={media('image/jpeg')} />)).not.toContain('data-clear');
  });
});

/* The server's video markup (6.4): muted in the HTML for whatever may start by itself, nothing loaded before it is near, the type on the source. */
describe('videos as the server writes them', () => {
  const video: Media = { url: '/storage/site/a/media/v.mp4', width: null, height: null, alt: '', mime: 'video/mp4' };
  const framed = (props: { autoplay?: boolean; ambient?: boolean }) =>
    renderToStaticMarkup(<FramedVideo video={video} poster={null} label="Play" ratio="16 / 9" {...props} />);

  it('writes an autoplay muted and looping, with its controls and no play button over it', () => {
    const html = framed({ autoplay: true });
    expect(html).toMatch(/<video[^>]* controls=""[^>]* muted=""[^>]* loop=""/);
    expect(html).toContain('preload="none"');
    expect(html).toContain('<source src="/storage/site/a/media/v.mp4" type="video/mp4"/>');
    expect(html).not.toContain('sp-play');
  });

  it('writes an ambient video muted, without controls, with the play button until it plays', () => {
    const html = framed({ ambient: true });
    expect(html).toContain('muted=""');
    expect(html).not.toContain('controls=""');
    expect(html).toContain('class="sp-play"');
  });

  it('keeps a plain video behind its play button, with its sound', () => {
    const html = framed({});
    expect(html).not.toContain('muted=""');
    expect(html).toContain('class="sp-play"');
  });

  it('writes the background video muted and hidden from screen readers', () => {
    const html = renderToStaticMarkup(<BackgroundVideo video={video} poster={null} />);
    expect(html).toContain('aria-hidden="true"');
    expect(html).toMatch(/<video[^>]* muted=""[^>]* loop=""[^>]* preload="none"/);
  });
});
