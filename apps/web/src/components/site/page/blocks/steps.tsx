import type { CSSProperties } from 'react';
import { sayList, type StepsBlock, type Tone } from '@/lib/site/page';
import { audienceRaw, type PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { LegacySection, Section } from '../section';

const DEFAULTS = { title: 'steps.title', eyebrow: 'steps.eyebrow' };

/** How it starts: a real sequence, so the numbers are drawn (today's connected line, or cards). */
export function Steps({ block, ctx, tone }: { block: StepsBlock; ctx: PageCtx; tone: Tone }) {
  const steps = sayList(block.data.items, () => audienceRaw<{ title: string; text: string }[]>(ctx, 'steps.items'));
  if (steps.length === 0) return null;

  if (block.variant === 'line') {
    return (
      <LegacySection block={block} tone={tone}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} />
        <div className="ls-steps" style={steps.length !== 4 ? ({ '--ls-steps': steps.length } as CSSProperties) : undefined} data-count={steps.length}>
          <div className="ls-steps-fill" aria-hidden="true" />
          {steps.map((s, i) => (
            <div key={`${s.title}-${i}`} className="ls-step" data-reveal="" style={{ '--i': i } as CSSProperties}>
              <b aria-hidden="true" />
              <h3>{s.title}</h3>
              {s.text ? <p>{s.text}</p> : null}
            </div>
          ))}
        </div>
      </LegacySection>
    );
  }

  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} defaults={DEFAULTS} />
      <ol className="sp-steps" style={{ '--count': steps.length } as CSSProperties}>
        {steps.map((s, i) => (
          <li key={`${s.title}-${i}`} data-reveal="" style={{ '--i': i } as CSSProperties}>
            <span className="sp-step-n" aria-hidden="true">{(i + 1).toLocaleString(ctx.locale === 'ar' ? 'ar-u-nu-latn' : 'en', { minimumIntegerDigits: 2 })}</span>
            <h3>{s.title}</h3>
            {s.text ? <p>{s.text}</p> : null}
          </li>
        ))}
      </ol>
    </Section>
  );
}
