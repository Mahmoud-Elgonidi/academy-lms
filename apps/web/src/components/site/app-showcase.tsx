'use client';

import type { ReactNode } from 'react';
import { Phone } from './phones';
import { useActiveStep } from './motion';

/**
 * The app told step by step: the phone stays beside the words and shows the
 * screen of the step in the middle of the screen. Without script, the first
 * screen shows and every step reads in turn.
 */
export function AppShowcase({ steps, screens, footer }: {
  steps: { title: string; text: string; points: string[] }[];
  screens: ReactNode[];
  footer?: ReactNode;
}) {
  const [active, at] = useActiveStep(steps.length);
  return (
    <div className="ls-app">
      <div className="ls-app-steps">
        {steps.map((step, i) => (
          <article key={step.title} ref={at(i)} data-step={i} className="ls-app-step" data-active={active === i ? '' : undefined}>
            <span className="ls-eyebrow">{`0${i + 1}`}</span>
            <h3>{step.title}</h3>
            <p>{step.text}</p>
            <ul>
              {step.points.map((point) => <li key={point}>{point}</li>)}
            </ul>
          </article>
        ))}
        {footer}
      </div>
      <div className="ls-app-phone">
        <div className="ls-app-glow" aria-hidden="true" />
        <Phone>
          {screens.map((screen, i) => (
            <div key={i} data-screen={i} data-active={active === i ? '' : undefined}>
              {screen}
            </div>
          ))}
        </Phone>
      </div>
    </div>
  );
}
