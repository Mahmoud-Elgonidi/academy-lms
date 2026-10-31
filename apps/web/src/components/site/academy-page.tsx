import type { CSSProperties } from 'react';
import { getLocale, getTranslations } from 'next-intl/server';
import { countriesIn } from '@/lib/site/countries';
import { initialOf } from '@/lib/site/initial';
import type { AcademySite } from '@/lib/site/server';
import type { Branding } from '@/lib/viewer';
import { AcademyCode, AcademyPlans, type ShownPlan } from './academy-plans';
import { AppShowcase } from './app-showcase';
import { AppleIcon, GooglePlayIcon, Icon, WhatsAppIcon, type IconName } from './icons';
import { JoinAcademyForm } from './join-academy';
import { Reveal, SolidOnScroll, Tilt } from './motion';
import { GuardianScreen, Phone, StudentScreen, TeacherScreen, type PhoneWords } from './phones';
import { Starfield } from './starfield';
import './site.css';

const WHY_ICONS: IconName[] = ['users', 'report', 'calendar', 'map', 'shield', 'heart'];
const ACCENTS = ['', 'gold', 'blue', 'rose', 'violet', ''];

type Words = { title: string; text: string; points: string[] };

/** A price per lesson, written as the API writes money ("40 ر.س." in Arabic with Latin digits, "SAR 40" in English). */
function perLesson(amountMinor: number, currency: string, lessons: number, locale: string): string | null {
  if (lessons < 2 || amountMinor <= 0) return null;
  try {
    const digits = new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2;
    const value = Math.round(amountMinor / lessons) / 10 ** digits;
    const arabic = locale.startsWith('ar');
    const text = new Intl.NumberFormat(arabic ? 'ar-u-nu-latn' : locale, {
      style: 'currency',
      currency,
      minimumFractionDigits: Number.isInteger(value) ? 0 : Math.min(2, digits),
      maximumFractionDigits: Number.isInteger(value) ? 0 : Math.min(2, digits),
    }).format(value).replace(/[‎‏]/g, '').trim();
    return arabic ? `⁨${text}⁩` : text;
  } catch {
    return null;
  }
}

/** The region a visitor sees prices in first: the academy's default, else the first. */
function startRegion(regions: AcademySite['regions']): string | null {
  return (regions.find((r) => r.default) ?? regions[0])?.id ?? null;
}

/**
 * An academy's own page, for families looking for lessons and teachers
 * looking for a place: what it offers, its subjects and levels, the teachers
 * who chose to show, how it starts, the app, its plans with prices per
 * region, what families say, and the two forms (a free trial; teach with
 * us). Its words come from /admin (GET /v1/site), its look from its brand.
 */
export async function AcademyPage({ site, look, utm, chosenSubject }: {
  site: AcademySite;
  look: Branding;
  utm: Record<string, string | undefined>;
  chosenSubject?: string;
}) {
  const t = await getTranslations('site');
  const all = await getTranslations();
  const locale = await getLocale();
  const other = locale === 'ar' ? 'en' : 'ar';
  const { content } = site;
  const shown = (section: string) => content.sections.includes(section);
  const words = all.raw('sitePhones') as PhoneWords;
  const steps = t.raw('steps.items') as { title: string; text: string }[];
  const appSteps = t.raw('app.steps') as Words[];
  const titleWords = content.hero.title.split(/\s+/).filter(Boolean);
  const whatsapp = site.whatsapp ? `https://wa.me/${site.whatsapp.replace(/^\+/, '')}` : null;
  const hasStores = Boolean(site.app.android_url || site.app.ios_url);
  const languages = new Intl.DisplayNames([locale], { type: 'language' });
  const regionCountries = site.regions.flatMap((r) => r.countries);
  const subjects = site.subjects.filter((s) => s.name);
  const teachers = shown('teachers') ? site.teachers : [];

  // Private or group: said on each plan only when the academy sells both (a group plan always says so).
  const isGroup = (plan: AcademySite['plans'][number]) => plan.format === 'group';
  const bothKinds = site.plans.some(isGroup) && site.plans.some((p) => !isGroup(p));
  const plans: ShownPlan[] = site.plans.map((plan) => {
    const facts = [
      ...(isGroup(plan) && plan.max_students ? [t('pricing.upTo', { count: plan.max_students })] : []),
      t('pricing.lessons', { count: plan.sessions_per_block }),
      t('pricing.minutes', { count: plan.session_minutes }),
      t('pricing.perWeek', { count: plan.sessions_per_week }),
      t('pricing.validity', { days: plan.validity_days }),
    ];
    if (plan.shared) facts.push(t('pricing.shared'));
    const prices: ShownPlan['prices'] = {};
    for (const p of plan.prices) {
      prices[p.region_id] = { price: p.price, perLesson: perLesson(p.amount_minor, p.currency, plan.sessions_per_block, locale) };
    }
    return { id: plan.id, name: plan.name, facts, prices, format: isGroup(plan) ? 'group' : bothKinds ? 'private' : null };
  });

  const range = (from: string | null, to: string | null) =>
    from || to ? t('teachers.levels', { from: from ?? t('teachers.fromStart'), to: to ?? t('teachers.toEnd') }) : null;

  return (
    <div className="ls ls-academy">
      <Reveal>
        <SolidOnScroll className="ls-header">
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
            <nav className="ls-nav" aria-label={site.academy.name}>
              {content.features.length > 0 ? <a href="#why">{t('nav.why')}</a> : null}
              {shown('subjects') && subjects.length > 0 ? <a href="#subjects">{t('nav.subjects')}</a> : null}
              {teachers.length > 0 ? <a href="#teachers">{t('nav.teachers')}</a> : null}
              {shown('pricing') && plans.length > 0 ? <a href="#pricing">{t('nav.pricing')}</a> : null}
              {shown('faq') && content.faqs.length > 0 ? <a href="#faq">{t('nav.faq')}</a> : null}
            </nav>
            <div className="ls-header-actions">
              <a className="ls-lang" href={`/${other}`} hrefLang={other} lang={other}>{t('nav.language')}</a>
              <a className="ls-btn ls-btn-ghost ls-btn-small" href={`/${locale}/sign-in`}>{t('nav.signIn')}</a>
              <a className="ls-btn ls-btn-main ls-btn-small ls-wide-only" href="#join">{t('cta.trialShort')}</a>
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
                  <a className="ls-btn ls-btn-main" href="#join">{t('cta.trial')}</a>
                  <a className="ls-btn ls-btn-ghost" href={`/${locale}/sign-in`}>{t('cta.signIn')}</a>
                </div>
                <div className="ls-trust">
                  {(t.raw('trust') as string[]).map((line) => (
                    <span key={line}><Icon name="check" />{line}</span>
                  ))}
                </div>
              </div>
              <div className="ls-stage" role="img" aria-label={t('phonesLabel')}>
                <Tilt className="ls-tilt" max={10}>
                  <Phone position="start"><StudentScreen w={words.student} /></Phone>
                  <Phone position="center"><GuardianScreen w={words.guardian} /></Phone>
                  <Phone position="end"><TeacherScreen w={words.teacher} /></Phone>
                  <div className="ls-float-card" data-at="top" aria-hidden="true">
                    <span>{t('floating.report')}</span>
                    <b>{t('floating.reportValue')}</b>
                    <div className="ls-stars-row">{[0, 1, 2, 3, 4].map((i) => <Icon key={i} name="star" />)}</div>
                  </div>
                  <div className="ls-float-card" data-at="bottom" aria-hidden="true">
                    <span>{t('floating.attendance')}</span>
                    <b>{t('floating.attendanceValue')}</b>
                  </div>
                </Tilt>
              </div>
            </div>
          </section>

          {content.features.length > 0 ? (
            <section className="ls-section" data-tone="white" id="why">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('why.eyebrow')}</span>
                  <h2>{t('why.title')}</h2>
                  <p>{t('why.lead')}</p>
                </header>
                <div className="ls-cards">
                  {content.features.map((f, i) => (
                    <article key={`${f.title}-${i}`} className="ls-card ls-feature" data-accent={ACCENTS[i % ACCENTS.length]} data-reveal="" style={{ '--i': i % 3 } as CSSProperties}>
                      <span className="ls-icon"><Icon name={WHY_ICONS[i] ?? 'star'} /></span>
                      <h3>{f.title}</h3>
                      <p>{f.text}</p>
                    </article>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          {shown('subjects') && subjects.length > 0 ? (
            <section className="ls-section" id="subjects">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('subjects.eyebrow')}</span>
                  <h2>{t('subjects.title')}</h2>
                  <p>{t('subjects.lead')}</p>
                </header>
                <div className="ls-subjects">
                  {subjects.map((subject, i) => (
                    <article key={subject.id} className="ls-card ls-subject" data-accent={ACCENTS[(i + 1) % ACCENTS.length]} data-reveal="" style={{ '--i': i % 3 } as CSSProperties}>
                      <div className="ls-subject-top">
                        <span className="ls-icon" aria-hidden="true">{subject.name.trim().charAt(0)}</span>
                        <h3>{subject.name}</h3>
                      </div>
                      {subject.categories.length > 0 ? (
                        <p className="ls-tags" aria-label={t('subjects.tracks')}>
                          {subject.categories.map((c) => <span key={c}>{c}</span>)}
                        </p>
                      ) : null}
                      {subject.levels.length > 0 ? (
                        <div className="ls-ladder-wrap">
                          <small>{t('subjects.levels')}</small>
                          <ol className="ls-ladder">
                            {subject.levels.map((level, n) => (
                              <li key={`${level}-${n}`} style={{ '--n': n, '--of': subject.levels.length } as CSSProperties}>
                                <i aria-hidden="true" />
                                <span>{level}</span>
                              </li>
                            ))}
                          </ol>
                        </div>
                      ) : null}
                      {subject.programs.length > 0 ? (
                        <div className="ls-programs">
                          <small>{t('subjects.programs')}</small>
                          <ul>
                            {subject.programs.map((p) => (
                              <li key={p.name}><strong>{p.name}</strong>{p.description ? <span>{p.description}</span> : null}</li>
                            ))}
                          </ul>
                        </div>
                      ) : null}
                      <a className="ls-more" href={`?subject=${encodeURIComponent(subject.id)}#join`}>{t('subjects.ask')}</a>
                    </article>
                  ))}
                </div>
              </div>
            </section>
          ) : null}

          {teachers.length > 0 ? (
            <section className="ls-section" data-tone="white" id="teachers">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('teachers.eyebrow')}</span>
                  <h2>{t('teachers.title')}</h2>
                  <p>{t('teachers.lead')}</p>
                </header>
                <div className="ls-teachers">
                  {teachers.map((teacher, i) => (
                    <article key={teacher.id} className="ls-card ls-teacher" data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
                      <div className="ls-teacher-face">
                        {teacher.photo_url ? (
                          // eslint-disable-next-line @next/next/no-img-element -- the teacher's own picture, on the academy's public files
                          <img src={teacher.photo_url} alt="" loading="lazy" />
                        ) : (
                          <span aria-hidden="true">{initialOf(teacher.name)}</span>
                        )}
                      </div>
                      <h3>{teacher.name}</h3>
                      {teacher.subjects.length > 0 ? (
                        <ul className="ls-teacher-subjects">
                          {teacher.subjects.map((s) => {
                            const levels = range(s.level_from, s.level_to);
                            return <li key={s.name}><strong>{s.name}</strong>{levels ? <small>{levels}</small> : null}</li>;
                          })}
                        </ul>
                      ) : null}
                      {teacher.bio ? <p>{teacher.bio}</p> : null}
                      <p className="ls-tags">
                        {teacher.languages.length > 0 ? <span>{t('teachers.explains')} {teacher.languages.map((code) => languages.of(code) ?? code).join('، ')}</span> : null}
                        {teacher.age_groups.map((age) => <span key={age}>{t.has(`teachers.ages.${age}`) ? t(`teachers.ages.${age}`) : age}</span>)}
                        {teacher.accepts_trials ? <span data-tone="good">{t('teachers.trials')}</span> : null}
                        {teacher.accepts_groups ? <span data-tone="group">{t('teachers.groups')}</span> : null}
                      </p>
                    </article>
                  ))}
                </div>
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
                  screens={[<GuardianScreen key="g" w={words.guardian} />, <StudentScreen key="s" w={words.student} />]}
                  footer={
                    <>
                      <AcademyCode code={site.academy.code} label={t('app.code')} />
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
                    </>
                  }
                />
              </div>
            </section>
          ) : null}

          {shown('pricing') && plans.length > 0 ? (
            <section className="ls-section" data-tone="white" id="pricing">
              <div className="ls-wrap">
                <header className="ls-head" data-reveal="">
                  <span className="ls-eyebrow">{t('pricing.eyebrow')}</span>
                  <h2>{t('pricing.title')}</h2>
                  <p>{t('pricing.lead')}</p>
                </header>
                <AcademyPlans plans={plans} regions={site.regions.map((r) => ({ id: r.id, name: r.name }))} start={startRegion(site.regions)} />
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
            <span id="teach" className="ls-anchor" aria-hidden="true" />
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
                <JoinAcademyForm
                  token={site.form_token}
                  subjects={subjects.map((s) => ({ id: s.id, name: s.name }))}
                  countries={countriesIn(locale, regionCountries)}
                  utm={{ source: utm.utm_source, medium: utm.utm_medium, campaign: utm.utm_campaign }}
                  teach={shown('teach')}
                  chosenSubject={subjects.some((s) => s.id === chosenSubject) ? chosenSubject : undefined}
                />
              </div>
            </div>
          </section>
        </main>

        <footer className="ls-footer">
          <div className="ls-wrap ls-footer-row">
            <span>© {new Date().getFullYear()} {site.academy.name} · {t('footer.rights')}</span>
            <nav aria-label={site.academy.name}>
              {shown('teach') ? <a href="#teach">{t('cta.teach')}</a> : null}
              <a href={`/${locale}/sign-in`}>{t('nav.signIn')}</a>
              <a href="/privacy">{t('footer.privacy')}</a>
              <a href="/terms">{t('footer.terms')}</a>
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
