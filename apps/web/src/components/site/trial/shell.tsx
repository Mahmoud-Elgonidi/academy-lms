import type { ReactNode } from 'react';
import { getLocale, getTranslations } from 'next-intl/server';
import { initialOf } from '@/lib/site/initial';
import { whatsappLink } from '@/lib/site/trial';
import type { Branding } from '@/lib/viewer';
import { Icon, WhatsAppIcon } from '../icons';
import '../site.css';
import './trial.css';

/**
 * The family's own trial pages (a link from WhatsApp, no sign-in): the
 * academy's name and colours over one calm card, in the academy page's look.
 * Whole without script or motion.
 */
export async function TrialShell({ look, path, children }: { look: Branding; /** This page's path after the locale ("/trial/…"). */ path: string; children: ReactNode }) {
  const locale = await getLocale();
  const t = await getTranslations('site.trial');
  const name = locale === 'en' ? look.name.en : look.name.ar;
  const other = locale === 'ar' ? 'en' : 'ar';
  return (
    <div className="ls ls-academy lt-page">
      <header className="lt-page-head">
        <a href={`/${locale}`} className="ls-brand">
          {look.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- the academy's own logo, from its branding
            <img src={look.logo_url} alt="" />
          ) : (
            <span className="ls-initial" aria-hidden="true">{initialOf(name)}</span>
          )}
          <span>{name}</span>
        </a>
        <a className="lt-lang" href={`/${other}${path}`} hrefLang={other}>{t('language')}</a>
      </header>
      <main className="lt-page-main">{children}</main>
    </div>
  );
}

/**
 * A calm card for a link that no longer leads anywhere, or a step the academy
 * takes itself: what happened in plain words, and what to do next.
 */
export async function CalmCard({ icon, title, text, whatsapp, whatsappWords, home, homeLabel, children }: {
  icon: 'clock' | 'chat' | 'calendar' | 'shield' | 'check';
  title: string;
  text: string;
  whatsapp?: string | null;
  whatsappWords?: string;
  home?: string;
  homeLabel?: string;
  children?: ReactNode;
}) {
  const t = await getTranslations('site.trial');
  const chat = whatsappLink(whatsapp, whatsappWords);
  return (
    <section className="lt-card lt-calm" data-tone="attention" aria-labelledby="lt-calm-title">
      <span className="lt-calm-icon" aria-hidden="true"><Icon name={icon} /></span>
      <h1 id="lt-calm-title">{title}</h1>
      <p>{text}</p>
      {children}
      <div className="lt-actions">
        {chat ? <a className="ls-btn ls-btn-dark ls-btn-small" href={chat} target="_blank" rel="noopener"><WhatsAppIcon />{t('whatsapp')}</a> : null}
        {home ? <a className="ls-btn lt-btn-soft ls-btn-small" href={home}>{homeLabel}</a> : null}
      </div>
    </section>
  );
}
