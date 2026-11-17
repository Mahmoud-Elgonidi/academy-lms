import type { ReactNode } from 'react';
import { safeUrl, type Inline, type RichNode } from '@/lib/site/page';

/** One run of text: bold, italic, a link (only to an address the page allows). */
function Run({ run }: { run: Inline }) {
  let out: ReactNode = run.text;
  if (run.i) out = <em>{out}</em>;
  if (run.b) out = <strong>{out}</strong>;
  const href = safeUrl(run.href);
  if (href) {
    const elsewhere = href.startsWith('https://') || href.startsWith('mailto:');
    out = <a href={href} target={elsewhere ? '_blank' : undefined} rel={elsewhere ? 'noopener' : undefined}>{out}</a>;
  }
  return out;
}

function Runs({ runs }: { runs: Inline[] }) {
  return runs.map((run, i) => <Run key={i} run={run} />);
}

/** The academy's limited rich text (1.5) as elements; it is never HTML. */
export function Rich({ nodes, className = 'sp-prose' }: { nodes: RichNode[]; className?: string }) {
  if (nodes.length === 0) return null;
  return (
    <div className={className}>
      {nodes.map((node, i) => {
        switch (node.t) {
          case 'p': return <p key={i}><Runs runs={node.c} /></p>;
          case 'h3': return <h3 key={i}><Runs runs={node.c} /></h3>;
          case 'h4': return <h4 key={i}><Runs runs={node.c} /></h4>;
          case 'quote': return <blockquote key={i}><p><Runs runs={node.c} /></p></blockquote>;
          case 'ul': return <ul key={i}>{node.items.map((item, n) => <li key={n}><Runs runs={item} /></li>)}</ul>;
          case 'ol': return <ol key={i}>{node.items.map((item, n) => <li key={n}><Runs runs={item} /></li>)}</ol>;
          default: return null;
        }
      })}
    </div>
  );
}
