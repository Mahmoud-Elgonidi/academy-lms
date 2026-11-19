import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import type { Media } from '@/lib/site/page';
import { Picture } from './media';

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
