'use client';

import { useState, type ReactNode } from 'react';
import { Icon } from '../../icons';

/**
 * One quote at a time, with buttons to move between them (no autoplay).
 * Without script the first shows; the others are a click away.
 */
export function QuoteDeck({ slides, labels }: { slides: ReactNode[]; labels: { previous: string; next: string; of: string } }) {
  const [at, setAt] = useState(0);
  const count = slides.length;
  const go = (step: number) => setAt((now) => (now + step + count) % count);
  const position = labels.of.replace('{current}', String(at + 1)).replace('{total}', String(count));
  return (
    <div className="sp-deck" data-reveal="">
      <div className="sp-deck-stage" aria-live="polite">
        {slides.map((slide, i) => (
          <div key={i} className="sp-deck-slide" hidden={i !== at}>{slide}</div>
        ))}
      </div>
      {count > 1 ? (
        <div className="sp-deck-nav">
          <button type="button" onClick={() => go(-1)} aria-label={labels.previous}><Icon name="chevron" className="sp-back" /></button>
          <span>{position}</span>
          <button type="button" onClick={() => go(1)} aria-label={labels.next}><Icon name="chevron" className="sp-fwd" /></button>
        </div>
      ) : null}
    </div>
  );
}
