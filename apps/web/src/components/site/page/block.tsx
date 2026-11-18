import { toneOf, type PageBlock } from '@/lib/site/page';
import type { PageCtx } from './context';
import { App } from './blocks/app';
import { Cards } from './blocks/cards';
import { Contact } from './blocks/contact';
import { Cta } from './blocks/cta';
import { Faq } from './blocks/faq';
import { Features } from './blocks/features';
import { Form } from './blocks/form';
import { Gallery } from './blocks/gallery';
import { Hero } from './blocks/hero';
import { ImageText } from './blocks/image-text';
import { Logos } from './blocks/logos';
import { Pricing } from './blocks/pricing';
import { RichText } from './blocks/rich-text';
import { Spacer } from './blocks/spacer';
import { Stats } from './blocks/stats';
import { Steps } from './blocks/steps';
import { Subjects } from './blocks/subjects';
import { Teachers } from './blocks/teachers';
import { Testimonials } from './blocks/testimonials';
import { Video } from './blocks/video';

/** One block of the page, drawn by its type; a type this portal does not know (a newer server) is skipped. */
export function Block({ block, ctx }: { block: PageBlock; ctx: PageCtx }) {
  const tone = toneOf(block, ctx.page.settings.mood);
  switch (block.type) {
    case 'hero': return <Hero block={block} ctx={ctx} tone={tone} />;
    case 'features': return <Features block={block} ctx={ctx} tone={tone} />;
    case 'subjects': return <Subjects block={block} ctx={ctx} tone={tone} />;
    case 'teachers': return <Teachers block={block} ctx={ctx} tone={tone} />;
    case 'pricing': return <Pricing block={block} ctx={ctx} tone={tone} />;
    case 'steps': return <Steps block={block} ctx={ctx} tone={tone} />;
    case 'app': return <App block={block} ctx={ctx} tone={tone} />;
    case 'testimonials': return <Testimonials block={block} ctx={ctx} tone={tone} />;
    case 'faq': return <Faq block={block} ctx={ctx} tone={tone} />;
    case 'stats': return <Stats block={block} ctx={ctx} tone={tone} />;
    case 'rich_text': return <RichText block={block} ctx={ctx} tone={tone} />;
    case 'image_text': return <ImageText block={block} ctx={ctx} tone={tone} />;
    case 'gallery': return <Gallery block={block} ctx={ctx} tone={tone} />;
    case 'video': return <Video block={block} ctx={ctx} tone={tone} />;
    case 'logos': return <Logos block={block} ctx={ctx} tone={tone} />;
    case 'cards': return <Cards block={block} ctx={ctx} tone={tone} />;
    case 'cta': return <Cta block={block} ctx={ctx} tone={tone} />;
    case 'form': return <Form block={block} ctx={ctx} tone={tone} />;
    case 'contact': return <Contact block={block} ctx={ctx} tone={tone} />;
    case 'spacer': return <Spacer block={block} tone={tone} />;
    default: return null;
  }
}
