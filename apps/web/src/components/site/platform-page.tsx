import type { CSSProperties } from 'react';
import { getLocale, getTranslations } from 'next-intl/server';
import { countriesIn } from '@/lib/site/countries';
import { initialOf } from '@/lib/site/initial';
import type { PlatformSite } from '@/lib/site/server';
import { AppShowcase } from './app-showcase';
import { AppleIcon, GooglePlayIcon, Icon, Mark, WhatsAppIcon, type IconName } from './icons';
import { JoinPlatformForm } from './join-platform';
import { CountUp, Reveal, SolidOnScroll, Tilt } from './motion';
import { GuardianScreen, Phone, StudentScreen, TeacherScreen, type PhoneWords } from './phones';
import { Starfield } from './starfield';
import './site.css';

const FEATURE_ICONS: IconName[] = ['video', 'calendar', 'card', 'report', 'map', 'book', 'sparkles', 'users', 'wallet', 'shield', 'globe', 'chat'];
const ACCENTS = ['', 'gold', 'blue', 'rose', 'violet', ''];

type Words = { title: string; text: string; points: string[] };

/**
 * The platform's own page, for academies looking for a system: what it does,
 * who it is for, the app, how to start, the plans, what others say, and the
 * request to join. Its words and plans come from the console (GET
 * /v1/site/platform); the rest is the portal's own (platformSite).
 */
export async function PlatformPage({ site, utm, chosen, path }: { site: PlatformSite; utm: Record<string, string | undefined>; chosen?: string; path: '' | '/platform' }) {
  const t = await getTranslations('platformSite');
  const all = await getTranslations();
  const locale = await getLocale();
  const other = locale === 'ar' ? 'en' : 'ar';
  const { content } = site;
  const shown = (section: string) => content.sections.includes(section);
  const words = all.raw('sitePhones') as PhoneWords;
  const audiences = t.raw('audiences.items') as (Words & { art: string })[];
  const features = t.raw('features.items') as { title: string; text: string }[];
  const steps = t.raw('steps.items') as { title: string; text: string }[];
  const appSteps = t.raw('app.steps') as Words[];
  const titleWords = content.hero.title.split(/\s+/).filter(Boolean);
  const whatsapp = content.contact.whatsapp ? `https://wa.me/${content.contact.whatsapp.replace(/^\+/, '')}` : null;
  const hasStores = Boolean(site.app.android_url || site.app.ios_url);

  return (
    <div className="ls">
      <Reveal>
        <SolidOnScroll className="ls-header">
          <div className="ls-wrap ls-header-row">
            <a href={`/${locale}${path}`} className="ls-brand">
              <Mark />
              <span>{t('title')}</span>
            </a>
            <nav className="ls-nav" aria-label={t('title')}>
              {shown('features') ? <a href="#features">{t('nav.features')}</a> : null}
              {shown('app') ? <a href="#app">{t('nav.app')}</a> : null}
              {shown('pricing') ? <a href="#pricing">{t('nav.pricing')}</a> : null}
              {shown('faq') ? <a href="#faq">{t('nav.faq')}</a> : null}
            </nav>
            <div className="ls-header-actions">
              <a className="ls-lang" href={`/${other}${path}`} hrefLang={other} lang={other}>{t('nav.language')}</a>
              <a className="ls-btn ls-btn-main ls-btn-small" href="#join">{t('cta.start')}</a>
            </div>
          </div>
        </SolidOnScroll>

        <main>
          <section className="ls-hero">
            <div className="ls-aurora" aria-hidden="true"><span /><span /><span /></div>
            <Starfield />
            <div className="ls-grid-floor" aria-hidden="true" />
            <div className="ls-wrap ls-hero-grid">
              <div>
                <span className="ls-kicker"><i aria-hidden="true" />{t('kicker')}</span>
                <h1>
                  {titleWords.map((word, i) => (
                    <span key={`${word}-${i}`} className={`ls-word${i >= titleWords.length - 2 ? ' ls-glowing' : ''}`} style={{ '--w': i } as CSSProperties}>
                      {word}
                      {i < titleWords.length - 1 ? ' ' : ''}
                    </span>
                  ))}
                </h1>
                <p className="ls-lead">{content.hero.lead}</p>
                <div className="ls-cta">
                  <a className="ls-btn ls-btn-main" href="#join">{t('cta.start')}</a>
                  {content.demo_url ? <a className="ls-btn ls-btn-ghost" href={content.demo_url} target="_blank" rel="noopener">{t('cta.demo')}</a> : null}
                </div>
                <div className="ls-trust">
                  {(t.raw('trust') as string[]).map((line) => (
                    <span key={line}><Icon name="check" />{line}</span>
                  ))}
                </div>
              </div>
              <div className="ls-stage" role="img" aria-label={t('phonesLabel')}>
                <Tilt className="ls-tilt" max={10}>
                  <Phone position="start"><TeacherScreen w={words.teacher} /></Phone>
                  <Phone position="center"><StudentScreen w={words.student} /></Phone>
                  <Phone position="end"><GuardianScreen w={words.guardian} /></Phone>
                  <div className="ls-float-card" data-at="top" aria-hidden="true">
                    <span>{t('floating.revenue')}</span>
                    <b>{t('floating.revenueValue')}</b>
                    <div className="ls-bars">
                      {[38, 52, 46, 64, 58, 80, 92].map((h, i) => <i key={i} style={{ height: `${h}%`, '--b': i } as CSSProperties} />)}
                    </div>
                  </div>
                  <div className="ls-float-card" data-at="bottom" aria-hidden="true">
                    <span>{t('floating.attendance')}</span>
                    <b>{t('floating.attendanceValue')}</b>
                  </div>
                </Tilt>
              </div>
            </div>
          </section>

          {shown('stats') && content.stats.length > 0 ? (
            <div className="ls-stats">
              <div className="ls-wrap ls-stats-grid">
                {content.stats.map((stat, i) => (
                  <div key={i} className="ls-stat" data-reveal="zoom" style={{ '--i': i } as CSSProperties}>
                    <CountUp value={stat.value} />
                    <span>{stat.label}</span>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {shown('audiences') ? (
            <section className="ls-section" id="for">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('audiences.eyebrow')}</span>
                  <h2>{t('audiences.title')}</h2>
                  <p>{t('audiences.lead')}</p>
                </header>
                <div className="ls-audiences">
                  {audiences.map((a, i) => (
                    <article key={a.title} className="ls-card ls-audience" data-reveal="" style={{ '--i': i } as CSSProperties}>
                      <div className="ls-audience-art" aria-hidden="true">{a.art}</div>
                      <div className="ls-audience-body">
                        <h3>{a.title}</h3>
                        <p>{a.text}</p>
                        <ul>{a.points.map((p) => <li key={p}>{p}</li>)}</ul>
                      </div>
                    </article>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          {shown('features') ? (
            <section className="ls-section" data-tone="white" id="features">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('features.eyebrow')}</span>
                  <h2>{t('features.title')}</h2>
                  <p>{t('features.lead')}</p>
                </header>
                <div className="ls-cards">
                  {features.map((f, i) => (
                    <article key={f.title} className="ls-card ls-feature" data-accent={ACCENTS[i % ACCENTS.length]} data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
                      <span className="ls-icon"><Icon name={FEATURE_ICONS[i] ?? 'star'} /></span>
                      <h3>{f.title}</h3>
                      <p>{f.text}</p>
                    </article>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          {shown('app') ? (
            <section className="ls-section" data-tone="night" id="app">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('app.eyebrow')}</span>
                  <h2>{t('app.title')}</h2>
                  <p>{t('app.lead')}</p>
                </header>
                <AppShowcase
                  steps={appSteps}
                  screens={[<GuardianScreen key="g" w={words.guardian} />, <StudentScreen key="s" w={words.student} />, <TeacherScreen key="t" w={words.teacher} />]}
                  footer={
                    <div className="ls-stores">
                      {site.app.android_url ? (
                        <a className="ls-store" href={site.app.android_url} target="_blank" rel="noopener">
                          <GooglePlayIcon />
                          <span><small>{t('app.getOn')}</small>{t('app.googlePlay')}</span>
                        </a>
                      ) : null}
                      {site.app.ios_url ? (
                        <a className="ls-store" href={site.app.ios_url} target="_blank" rel="noopener">
                          <AppleIcon />
                          <span><small>{t('app.downloadOn')}</small>{t('app.appStore')}</span>
                        </a>
                      ) : null}
                      {!hasStores ? <span className="ls-kicker"><i aria-hidden="true" />{t('app.soon')}</span> : null}
                    </div>
                  }
                />
              </div>
            </section>
          ) : null}

          {shown('steps') ? (
            <section className="ls-section" id="start">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('steps.eyebrow')}</span>
                  <h2>{t('steps.title')}</h2>
                </header>
                <div className="ls-steps">
                  <div className="ls-steps-fill" aria-hidden="true" />
                  {steps.map((s, i) => (
                    <div key={s.title} className="ls-step" data-reveal="" style={{ '--i': i } as CSSProperties}>
                      <b aria-hidden="true" />
                      <h3>{s.title}</h3>
                      <p>{s.text}</p>
                    </div>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          {shown('pricing') && site.plans.length > 0 ? (
            <section className="ls-section" data-tone="white" id="pricing">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('pricing.eyebrow')}</span>
                  <h2>{t('pricing.title')}</h2>
                  <p>{t('pricing.lead')}</p>
                </header>
                <div className="ls-plans">
                  {site.plans.map((plan, i) => (
                    <article key={plan.code} className="ls-card ls-plan" data-highlight={plan.highlight ? '' : undefined} data-reveal="" style={{ '--i': i } as CSSProperties}>
                      {plan.highlight ? <span className="ls-ribbon">{t('pricing.popular')}</span> : null}
                      <h3>{plan.name}</h3>
                      <p className="ls-tagline">{plan.tagline ?? ''}</p>
                      <div className="ls-price">
                        {plan.price ? <><b>{plan.price}</b><span>{t('pricing.perMonth')}</span></> : <b>{t('pricing.noPrice')}</b>}
                      </div>
                      <ul>
                        {plan.limits.map((l) => (
                          <li key={l.key}><Icon name="check" /><span><strong>{l.value}</strong> · {l.label}</span></li>
                        ))}
                        {plan.features.map((f) => (
                          <li key={f.key} data-off={f.on ? undefined : ''}><Icon name={f.on ? 'check' : 'clock'} /><span>{f.label}</span></li>
                        ))}
                      </ul>
                      <a className={`ls-btn ${plan.highlight ? 'ls-btn-main' : 'ls-btn-dark'}`} href={`?plan=${encodeURIComponent(plan.code)}#join`}>{t('pricing.ask')}</a>
                    </article>
                  ))}
                </div>
                <p className="ls-head" style={{ marginBlock: '2.5rem 0' }} data-reveal="">
                  {t('pricing.custom')}{' '}
                  <a href={whatsapp ?? '#join'} className="ls-gradient-text" style={{ fontWeight: 800 }}>{t('pricing.customCta')}</a>
                </p>
              </div>
            </section>
          ) : null}

          {shown('testimonials') && content.testimonials.length > 0 ? (
            <section className="ls-section" id="testimonials">
              <header className="ls-head ls-wrap" data-reveal="">
                <span className="ls-eyebrow">{t('testimonials.eyebrow')}</span>
                <h2>{t('testimonials.title')}</h2>
              </header>
              <div className="ls-marquee" data-reveal="">
                <div className="ls-marquee-track" style={{ '--ls-marquee': `${Math.max(30, content.testimonials.length * 9)}s` } as CSSProperties}>
                  {[...content.testimonials, ...content.testimonials].map((q, i) => (
                    <figure key={i} className="ls-quote" aria-hidden={i >= content.testimonials.length ? true : undefined}>
                      <p>“{q.quote}”</p>
                      <footer>
                        <i aria-hidden="true">{initialOf(q.name)}</i>
                        <span><strong>{q.name}</strong>{q.role ? <small>{q.role}</small> : null}</span>
                      </footer>
                    </figure>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          {shown('faq') && content.faqs.length > 0 ? (
            <section className="ls-section" data-tone="white" id="faq">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('faq.eyebrow')}</span>
                  <h2>{t('faq.title')}</h2>
                </header>
                <div className="ls-faq">
                  {content.faqs.map((f, i) => (
                    <details key={i} data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
                      <summary>{f.q}</summary>
                      <p>{f.a}</p>
                    </details>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          <section className="ls-section ls-join" data-tone="night" id="join">
            <div className="ls-aurora" aria-hidden="true"><span /><span /><span /></div>
            <div className="ls-wrap ls-join-grid">
              <div data-reveal="start">
                <span className="ls-eyebrow">{t('join.eyebrow')}</span>
                <h2>{t('join.title')}</h2>
                <p className="ls-lead" style={{ marginBlockStart: '1rem' }}>{t('join.lead')}</p>
                <ul className="ls-join-points">
                  {(t.raw('join.points') as string[]).map((p) => <li key={p}><Icon name="star" />{p}</li>)}
                </ul>
                {whatsapp ? (
                  <div className="ls-cta">
                    <a className="ls-btn ls-btn-ghost" href={whatsapp} target="_blank" rel="noopener"><WhatsAppIcon />{t('cta.talk')}</a>
                  </div>
                ) : null}
              </div>
              <div data-reveal="zoom">
                <JoinPlatformForm token={site.form_token} countries={countriesIn(locale)} plans={site.plans.map((p) => ({ code: p.code, name: p.name }))} utm={{ source: utm.utm_source, medium: utm.utm_medium, campaign: utm.utm_campaign }} chosen={chosen} />
              </div>
            </div>
          </section>
        </main>

        <footer className="ls-footer">
          <div className="ls-wrap ls-footer-row">
            <span>© {new Date().getFullYear()} {site.legal_name} · {t('footer.rights')}</span>
            <nav aria-label={t('title')}>
              <a href="/privacy">{t('footer.privacy')}</a>
              <a href="/terms">{t('footer.terms')}</a>
              {content.contact.email ? <a href={`mailto:${content.contact.email}`} dir="ltr">{content.contact.email}</a> : null}
            </nav>
          </div>
        </footer>
        {whatsapp ? (
          <a className="ls-whatsapp" href={whatsapp} target="_blank" rel="noopener" aria-label={t('cta.talk')}><WhatsAppIcon /></a>
        ) : null}
      </Reveal>
    </div>
  );
}
