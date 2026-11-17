import type { CSSProperties } from 'react';
import { say, type SubjectsBlock, type Tone } from '@/lib/site/page';
import { comma, type PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { LegacySection, Section } from '../section';

const ACCENTS = ['', 'gold', 'blue', 'rose', 'violet', ''];
const DEFAULTS = { title: 'subjects.title', lead: 'subjects.lead', eyebrow: 'subjects.eyebrow' };

/** The subjects the block shows, in its order, from the academy's live list (ids no longer there are skipped). */
export function subjectsOf(block: SubjectsBlock, ctx: PageCtx) {
  const byId = new Map(ctx.site.subjects.filter((s) => s.name).map((s) => [s.id, s]));
  const ids = block.data.subject_ids.length > 0 ? block.data.subject_ids : [...byId.keys()];
  return ids.map((id) => byId.get(id)).filter((s): s is NonNullable<typeof s> => Boolean(s));
}

/** What the academy teaches (live: Catalog and Curriculum): today's cards with levels and programs, compact rows, or names only. */
export function Subjects({ block, ctx, tone }: { block: SubjectsBlock; ctx: PageCtx; tone: Tone }) {
  const subjects = subjectsOf(block, ctx);
  if (subjects.length === 0) return null;
  const { show_levels, show_programs, show_categories } = block.data;
  const ask = ctx.links.form ? say(block.data.ask_label, () => ctx.t('subjects.ask')) : null;
  const askHref = (id: string) => `?subject=${encodeURIComponent(id)}#join`;

  if (block.variant === 'cards') {
    return (
      <LegacySection block={block} tone={tone}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} />
        <div className="ls-subjects">
          {subjects.map((subject, i) => (
            <article key={subject.id} className="ls-card ls-subject" data-accent={ACCENTS[(i + 1) % ACCENTS.length]} data-reveal="" style={{ '--i': i % 3 } as CSSProperties}>
              <div className="ls-subject-top">
                <span className="ls-icon" aria-hidden="true">{subject.name.trim().charAt(0)}</span>
                <h3>{subject.name}</h3>
              </div>
              {show_categories && subject.categories.length > 0 ? (
                <p className="ls-tags" aria-label={ctx.t('subjects.tracks')}>
                  {subject.categories.map((c) => <span key={c}>{c}</span>)}
                </p>
              ) : null}
              {show_levels && subject.levels.length > 0 ? (
                <div className="ls-ladder-wrap">
                  <small>{ctx.t('subjects.levels')}</small>
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
              {show_programs && subject.programs.length > 0 ? (
                <div className="ls-programs">
                  <small>{ctx.t('subjects.programs')}</small>
                  <ul>
                    {subject.programs.map((p) => (
                      <li key={p.name}><strong>{p.name}</strong>{p.description ? <span>{p.description}</span> : null}</li>
                    ))}
                  </ul>
                </div>
              ) : null}
              {ask ? <a className="ls-more" href={askHref(subject.id)}>{ask}</a> : null}
            </article>
          ))}
        </div>
      </LegacySection>
    );
  }

  if (block.variant === 'chips') {
    return (
      <Section block={block} tone={tone}>
        <Head block={block} ctx={ctx} defaults={DEFAULTS} />
        <ul className="sp-chips" data-reveal="">
          {subjects.map((subject) => (
            <li key={subject.id}>
              {ask ? <a href={askHref(subject.id)} title={ask}>{subject.name}</a> : <span>{subject.name}</span>}
            </li>
          ))}
        </ul>
      </Section>
    );
  }

  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} defaults={DEFAULTS} />
      <ul className="sp-rows">
        {subjects.map((subject, i) => (
          <li key={subject.id} className="sp-row" data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
            <div className="sp-row-main">
              <h3>{subject.name}</h3>
              {show_categories && subject.categories.length > 0 ? <p className="sp-meta">{subject.categories.join(comma(ctx))}</p> : null}
            </div>
            <div className="sp-row-detail">
              {show_levels && subject.levels.length > 0 ? (
                <p><span className="sp-label">{ctx.t('subjects.levels')}</span> {subject.levels.join(ctx.locale === 'ar' ? ' ← ' : ' → ')}</p>
              ) : null}
              {show_programs && subject.programs.length > 0 ? (
                <p><span className="sp-label">{ctx.t('subjects.programs')}</span> {subject.programs.map((p) => p.name).join(comma(ctx))}</p>
              ) : null}
            </div>
            {ask ? <a className="sp-link" href={askHref(subject.id)}>{ask}</a> : null}
          </li>
        ))}
      </ul>
    </Section>
  );
}
