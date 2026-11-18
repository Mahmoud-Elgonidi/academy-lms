'use client';

import { useRef, useState, type CSSProperties } from 'react';
import type { Media } from '@/lib/site/page';
import { Icon } from '../../icons';

type Item = { media: Media; caption: string | null };

/**
 * The academy's pictures: equal tiles, a masonry that keeps each picture's
 * ratio, or a strip that scrolls (snap, buttons, no autoplay). A picture
 * opens larger in a dialog (focus held inside, Escape closes).
 */
export function GalleryView({ items, variant, ratio, labels }: {
  items: Item[];
  variant: 'grid' | 'masonry' | 'carousel';
  ratio: string | null;
  labels: { open: string; close: string; previous: string; next: string };
}) {
  const dialog = useRef<HTMLDialogElement>(null);
  const strip = useRef<HTMLUListElement>(null);
  const [open, setOpen] = useState<number | null>(null);
  const show = (i: number) => {
    setOpen(i);
    dialog.current?.showModal();
  };
  const step = (dir: 1 | -1) => {
    const el = strip.current;
    if (!el) return;
    // Forward is the reading direction: leftward in Arabic.
    const rtl = getComputedStyle(el).direction === 'rtl';
    el.scrollBy({ left: dir * (rtl ? -1 : 1) * el.clientWidth * 0.8, behavior: 'smooth' });
  };
  const shown = open === null ? null : items[open];

  return (
    <div className="sp-gallery" data-variant={variant}>
      <ul ref={strip} className="sp-gallery-list" style={ratio ? ({ '--ratio': ratio } as CSSProperties) : undefined}>
        {items.map((item, i) => (
          <li key={`${item.media.url}-${i}`} data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
            <figure>
              <button type="button" className="sp-gallery-open" onClick={() => show(i)} aria-label={`${labels.open}: ${item.media.alt || item.caption || ''}`.trim()}>
                <span className="sp-frame" style={{ aspectRatio: variant === 'grid' && ratio ? ratio : item.media.width && item.media.height ? `${item.media.width} / ${item.media.height}` : '4 / 3' }}>
                  {/* eslint-disable-next-line @next/next/no-img-element -- the academy's own picture */}
                  <img src={item.media.url} alt={item.media.alt} width={item.media.width ?? undefined} height={item.media.height ?? undefined} loading="lazy" decoding="async" />
                </span>
              </button>
              {item.caption ? <figcaption>{item.caption}</figcaption> : null}
            </figure>
          </li>
        ))}
      </ul>
      {variant === 'carousel' && items.length > 1 ? (
        <div className="sp-deck-nav">
          <button type="button" onClick={() => step(-1)} aria-label={labels.previous}><Icon name="chevron" className="sp-back" /></button>
          <button type="button" onClick={() => step(1)} aria-label={labels.next}><Icon name="chevron" className="sp-fwd" /></button>
        </div>
      ) : null}
      <dialog ref={dialog} className="sp-lightbox" onClose={() => setOpen(null)} onClick={(event) => { if (event.target === dialog.current) dialog.current?.close(); }}>
        {shown ? (
          <figure>
            {/* eslint-disable-next-line @next/next/no-img-element -- the academy's own picture */}
            <img src={shown.media.url} alt={shown.media.alt} width={shown.media.width ?? undefined} height={shown.media.height ?? undefined} />
            {shown.caption ? <figcaption>{shown.caption}</figcaption> : null}
          </figure>
        ) : null}
        <button type="button" className="sp-lightbox-close" onClick={() => dialog.current?.close()} aria-label={labels.close} autoFocus>
          <Icon name="close" />
        </button>
      </dialog>
    </div>
  );
}
