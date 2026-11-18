import type { ReactNode } from 'react';
import { defaultLabelKey, resolveAction, type PageLink } from '@/lib/site/page';
import { WhatsAppIcon } from '../icons';
import type { PageCtx } from './context';

/** A button's words: the academy's, else the default for where it leads. */
export function labelOf(link: PageLink, ctx: PageCtx, fallbackKey?: string): string {
  return link.label.trim() || ctx.t(fallbackKey ?? defaultLabelKey(link.action));
}

/**
 * A button of the page (6.2): the primary is solid in the accent, the
 * secondary a text button whose underline grows. A button that cannot work
 * here (a trial with no form, WhatsApp with no number) is not drawn.
 */
export function PageButton({ link, ctx, kind = 'primary', fallbackKey, className, children }: {
  link: PageLink | null | undefined;
  ctx: PageCtx;
  kind?: 'primary' | 'secondary' | 'ghost';
  fallbackKey?: string;
  className?: string;
  children?: ReactNode;
}) {
  if (!link) return null;
  const to = resolveAction(link.action, ctx.links);
  if (!to) return null;
  const opensElsewhere = to.external || (ctx.preview && !to.href.startsWith('#'));
  return (
    <a
      className={className ?? `sp-btn sp-btn-${kind}`}
      href={to.href}
      target={opensElsewhere ? '_blank' : undefined}
      rel={opensElsewhere ? 'noopener' : undefined}
    >
      {link.action.kind === 'whatsapp' ? <WhatsAppIcon /> : null}
      <span>{children ?? labelOf(link, ctx, fallbackKey)}</span>
    </a>
  );
}
