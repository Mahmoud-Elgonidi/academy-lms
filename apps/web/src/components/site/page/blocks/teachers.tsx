import type { CSSProperties } from 'react';
import { initialOf } from '@/lib/site/initial';
import { ageGroupsFor, type TeachersBlock, type Tone } from '@/lib/site/page';
import { comma, type PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { LegacySection, Section } from '../section';

const DEFAULTS = { title: 'teachers.title', lead: 'teachers.lead', eyebrow: 'teachers.eyebrow' };

/**
 * The teachers who chose to show (live: their own cards, as the API orders
 * and limits them for this block): today's cards, large portraits, or a
 * compact line each.
 */
export function Teachers({ block, ctx, tone }: { block: TeachersBlock; ctx: PageCtx; tone: Tone }) {
  const teachers = ctx.site.teachers;
  if (teachers.length === 0) return null;
  const { show_bio, show_languages, show_ages, show_badges } = block.data;
  const t = ctx.t;
  const languages = new Intl.DisplayNames([ctx.locale], { type: 'language' });
  const range = (from: string | null, to: string | null) =>
    from || to ? t('teachers.levels', { from: from ?? t('teachers.fromStart'), to: to ?? t('teachers.toEnd') }) : null;
  const age = (code: string) => (t.has(`teachers.ages.${code}`) ? t(`teachers.ages.${code}`) : code);

  if (block.variant === 'cards') {
    return (
      <LegacySection block={block} tone={tone}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} />
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
              {show_bio && teacher.bio ? <p>{teacher.bio}</p> : null}
              <p className="ls-tags">
                {show_languages && teacher.languages.length > 0 ? <span>{t('teachers.explains')} {teacher.languages.map((code) => languages.of(code) ?? code).join('، ')}</span> : null}
                {show_ages ? ageGroupsFor(teacher.age_groups, ctx.audience).map((a) => <span key={a}>{age(a)}</span>) : null}
                {show_badges && teacher.accepts_trials ? <span data-tone="good">{t('teachers.trials')}</span> : null}
                {show_badges && teacher.accepts_groups ? <span data-tone="group">{t('teachers.groups')}</span> : null}
              </p>
            </article>
          ))}
        </div>
      </LegacySection>
    );
  }

  const portraits = block.variant === 'portraits';
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} defaults={DEFAULTS} />
      <ul className="sp-people" data-variant={block.variant}>
        {teachers.map((teacher, i) => {
          const facts = [
            show_languages && teacher.languages.length > 0 ? `${t('teachers.explains')} ${teacher.languages.map((code) => languages.of(code) ?? code).join(comma(ctx))}` : null,
            ...(show_ages ? ageGroupsFor(teacher.age_groups, ctx.audience).map(age) : []),
          ].filter((f): f is string => Boolean(f));
          return (
            <li key={teacher.id} className="sp-person" data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
              <div className="sp-face" data-empty={teacher.photo_url ? undefined : ''}>
                {teacher.photo_url ? (
                  // eslint-disable-next-line @next/next/no-img-element -- the teacher's own picture, on the academy's public files
                  <img src={teacher.photo_url} alt="" loading="lazy" decoding="async" />
                ) : (
                  <span aria-hidden="true">{initialOf(teacher.name)}</span>
                )}
              </div>
              <div className="sp-person-words">
                <h3>{teacher.name}</h3>
                {teacher.subjects.length > 0 ? (
                  <p className="sp-meta">
                    {teacher.subjects.map((s, n) => {
                      const levels = portraits ? range(s.level_from, s.level_to) : null;
                      return (
                        <span key={s.name}>
                          {n > 0 ? comma(ctx) : null}
                          <bdi>{s.name}</bdi>
                          {levels ? <> (<bdi>{levels}</bdi>)</> : null}
                        </span>
                      );
                    })}
                  </p>
                ) : null}
                {portraits && show_bio && teacher.bio ? <p className="sp-bio">{teacher.bio}</p> : null}
                {portraits && facts.length > 0 ? <p className="sp-facts">{facts.join(comma(ctx))}</p> : null}
                {show_badges && (teacher.accepts_trials || teacher.accepts_groups) ? (
                  <p className="sp-badges">
                    {teacher.accepts_trials ? <span>{t('teachers.trials')}</span> : null}
                    {teacher.accepts_groups ? <span>{t('teachers.groups')}</span> : null}
                  </p>
                ) : null}
              </div>
            </li>
          );
        })}
      </ul>
    </Section>
  );
}
