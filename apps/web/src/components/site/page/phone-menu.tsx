'use client';

import { useRef } from 'react';

/**
 * The header's menu on narrow screens (the editorial look): a disclosure
 * that opens the same links as the wide menu and closes once one is chosen.
 * Without script it still opens and closes.
 */
export function PhoneMenu({ items, label }: { items: { href: string; label: string }[]; label: string }) {
  const menu = useRef<HTMLDetailsElement>(null);
  if (items.length === 0) return null;
  return (
    <details ref={menu} className="sp-menu">
      <summary aria-label={label}>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" fill="none" /></svg>
      </summary>
      <nav aria-label={label} onClick={(event) => { if ((event.target as HTMLElement).closest('a')) menu.current?.removeAttribute('open'); }}>
        {items.map((item) => <a key={item.href} href={item.href}>{item.label}</a>)}
      </nav>
    </details>
  );
}
