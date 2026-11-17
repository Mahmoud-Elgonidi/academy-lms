'use client';

import { useState, type ReactNode } from 'react';

/** In the builder's preview the form can be tried but sends nothing: a submit is stopped here, and a note says why. */
export function PreviewGuard({ children, note }: { children: ReactNode; note: string }) {
  const [tried, setTried] = useState(false);
  return (
    <div
      className="sp-preview-guard"
      onSubmitCapture={(event) => {
        event.preventDefault();
        event.stopPropagation();
        setTried(true);
      }}
    >
      <p className="sp-preview-note" role="status" data-tried={tried ? '' : undefined}>{note}</p>
      {children}
    </div>
  );
}
