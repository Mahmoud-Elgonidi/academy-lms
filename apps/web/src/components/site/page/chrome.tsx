import { initialOf } from '@/lib/site/initial';
import { navOf, safeUrl, whatsappUrl } from '@/lib/site/page';
import { WhatsAppIcon } from '../icons';
import { SolidOnScroll } from '../motion';
import type { PageCtx } from './context';
import { labelOf, PageButton } from './link';
import { PhoneMenu } from './phone-menu';

export type HeaderMode = 'night' | 'light' | 'solid' | 'minimal';

const NETWORKS: Record<string, string> = {
  instagram: 'Instagram', x: 'X', facebook: 'Facebook', youtube: 'YouTube', tiktok: 'TikTok', linkedin: 'LinkedIn', snapchat: 'Snapchat', telegram: 'Telegram',
};

/**
 * The page's header: the academy's mark, the menu (blocks with a menu
 * label), the language, signing in and the main button. Over a dark opening
 * it is today's header (white words, night once scrolled); over a light one
 * it keeps dark ink; solid is a bar in the page's paper; minimal is the mark
 * and one button.
 */
export function PageHeader({ ctx, mode }: { ctx: PageCtx; mode: HeaderMode }) {
  const { site, look, locale, t, page } = ctx;
  const header = page.settings.header;
  const other = locale === 'ar' ? 'en' : 'ar';
  const nav = mode === 'minimal' ? [] : navOf(page.blocks);
  const cta = header.cta;
  const ctaLabel = cta ? labelOf(cta, ctx, cta.action.kind === 'trial' ? 'cta.trialShort' : undefined) : '';
  return (
    <SolidOnScroll className={mode === 'night' ? 'ls-header' : `ls-header sp-header sp-header-${mode}`}>
      <div className="ls-wrap ls-header-row">
        <a href={`/${locale}`} className="ls-brand">
          {look.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- the academy's own logo, from its branding
            <img src={look.logo_url} alt="" />
          ) : (
            <span className="ls-initial" aria-hidden="true">{initialOf(site.academy.name)}</span>
          )}
          <span>{site.academy.name}</span>
        </a>
        {mode === 'minimal' ? null : (
          <nav className="ls-nav" aria-label={site.academy.name}>
            {nav.map((item) => <a key={item.href} href={item.href}>{item.label}</a>)}
          </nav>
        )}
        <div className="ls-header-actions">
          {header.language_switch ? (
            <a className="ls-lang" href={ctx.preview ? `/${other}/site-preview${ctx.previewToken ? `?t=${encodeURIComponent(ctx.previewToken)}` : ''}` : `/${other}`} hrefLang={other} lang={other}>{t('nav.language')}</a>
          ) : null}
          {header.sign_in && mode !== 'minimal' ? (
            <PageButton link={{ label: t('nav.signIn'), action: { kind: 'sign_in' } }} ctx={ctx} className="ls-btn ls-btn-ghost ls-btn-small" />
          ) : null}
          {cta ? (
            <PageButton link={cta} ctx={ctx} className={`ls-btn ls-btn-main ls-btn-small${mode === 'minimal' ? '' : ' ls-wide-only'}`}>{ctaLabel}</PageButton>
          ) : null}
          {/* Today's night header has no menu on phones (the migrated page stays as it was); an editorial page over a dark opening gets one. */}
          {mode !== 'minimal' && (mode !== 'night' || ctx.character === 'editorial') ? <PhoneMenu items={nav} label={t('blocks.menu')} /> : null}
        </div>
      </div>
    </SolidOnScroll>
  );
}

/** The page's footer: the academy's line, its links and networks when it set them, then today's legal row (always there). */
export function PageFooter({ ctx }: { ctx: PageCtx }) {
  const { site, locale, t, page } = ctx;
  const footer = page.settings.footer;
  const links = footer.links.map((l) => ({ label: l.label, href: safeUrl(l.url) })).filter((l): l is { label: string; href: string } => Boolean(l.href && l.label));
  const social = footer.social.map((s) => ({ name: NETWORKS[s.network] ?? s.network, href: safeUrl(s.url) })).filter((s): s is { name: string; href: string } => Boolean(s.href));
  const rich = Boolean(footer.text) || links.length > 0 || social.length > 0;
  return (
    <footer className="ls-footer">
      {rich ? (
        <div className="ls-wrap sp-footer-top">
          <div>
            <strong className="sp-footer-name">{site.academy.name}</strong>
            {footer.text ? <p>{footer.text}</p> : null}
          </div>
          {links.length > 0 ? (
            <nav aria-label={t('blocks.footer.links')}>
              <ul>{links.map((l) => <li key={l.href}><a href={l.href} target={l.href.startsWith('https://') ? '_blank' : undefined} rel={l.href.startsWith('https://') ? 'noopener' : undefined}>{l.label}</a></li>)}</ul>
            </nav>
          ) : null}
          {social.length > 0 ? (
            <nav aria-label={t('blocks.footer.social')}>
              <ul>{social.map((s) => <li key={s.href}><a href={s.href} target="_blank" rel="noopener me">{s.name}</a></li>)}</ul>
            </nav>
          ) : null}
        </div>
      ) : null}
      <div className="ls-wrap ls-footer-row">
        <span>© {new Date().getFullYear()} {site.academy.name} · {t('footer.rights')}</span>
        <nav aria-label={site.academy.name}>
          {ctx.links.teach ? <a href="#teach">{t('cta.teach')}</a> : null}
          <a href={`/${locale}/sign-in`}>{t('nav.signIn')}</a>
          <a href="/privacy">{t('footer.privacy')}</a>
          <a href="/terms">{t('footer.terms')}</a>
        </nav>
      </div>
    </footer>
  );
}

/** The floating WhatsApp button (when the page has a number and keeps it on). */
export function FloatingWhatsApp({ ctx }: { ctx: PageCtx }) {
  const href = ctx.page.settings.floating_whatsapp ? whatsappUrl(ctx.page.settings.whatsapp ?? ctx.site.whatsapp) : null;
  if (!href) return null;
  return <a className="ls-whatsapp" href={href} target="_blank" rel="noopener" aria-label={ctx.t('cta.talk')}><WhatsAppIcon /></a>;
}
