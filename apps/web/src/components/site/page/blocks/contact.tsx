import type { CSSProperties, ReactNode } from 'react';
import { whatsappUrl, type ContactBlock, type Tone } from '@/lib/site/page';
import { Icon, WhatsAppIcon } from '../../icons';
import type { PageCtx } from '../context';
import { Head } from '../heading';
import { Section } from '../section';

/** The only place the page shows an email or a phone: what the academy typed here on purpose. */
export function Contact({ block, ctx, tone }: { block: ContactBlock; ctx: PageCtx; tone: Tone }) {
  const { email, phone, address, hours } = block.data;
  const t = ctx.t;
  const wa = block.data.whatsapp ? whatsappUrl(ctx.page.settings.whatsapp ?? ctx.site.whatsapp) : null;
  const ways: { key: string; icon: ReactNode; label: string; value: string; href: string | null }[] = [];
  if (wa) ways.push({ key: 'wa', icon: <WhatsAppIcon />, label: t('blocks.contact.whatsapp'), value: (ctx.page.settings.whatsapp ?? ctx.site.whatsapp ?? '').replace(/^\+/, '+'), href: wa });
  if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) ways.push({ key: 'email', icon: <Icon name="mail" />, label: t('blocks.contact.email'), value: email, href: `mailto:${email}` });
  if (phone && /^\+[0-9]{6,15}$/.test(phone)) ways.push({ key: 'phone', icon: <Icon name="call" />, label: t('blocks.contact.phone'), value: phone, href: `tel:${phone}` });
  if (address) ways.push({ key: 'address', icon: <Icon name="pin" />, label: t('blocks.contact.address'), value: address, href: null });
  if (hours) ways.push({ key: 'hours', icon: <Icon name="clock" />, label: t('blocks.contact.hours'), value: hours, href: null });
  if (ways.length === 0) return null;
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} defaults={{ title: 'blocks.contact.title' }} />
      <ul className="sp-contact" data-variant={block.variant}>
        {ways.map((way, i) => (
          <li key={way.key} data-reveal="" style={{ '--i': i } as CSSProperties}>
            <span className="sp-icon">{way.icon}</span>
            <span className="sp-contact-label">{way.label}</span>
            {way.href ? (
              <a href={way.href} target={way.key === 'wa' ? '_blank' : undefined} rel={way.key === 'wa' ? 'noopener' : undefined} dir={way.key === 'address' || way.key === 'hours' ? undefined : 'ltr'}>{way.value}</a>
            ) : (
              <span className="sp-contact-value">{way.value}</span>
            )}
          </li>
        ))}
      </ul>
    </Section>
  );
}
