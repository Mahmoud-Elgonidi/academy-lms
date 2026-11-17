import type { SpacerBlock, Tone } from '@/lib/site/page';

/** Room between blocks, or a quiet rule (a tool for rhythm; it says nothing). */
export function Spacer({ block, tone }: { block: SpacerBlock; tone: Tone }) {
  return (
    <div className="sp-spacer" id={block.anchor} data-block="spacer" data-variant={block.variant} data-size={block.data.size} data-tone={tone} aria-hidden="true">
      {block.variant === 'divider' ? <hr /> : null}
    </div>
  );
}
