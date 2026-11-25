import { NextIntlClientProvider } from 'next-intl';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import ar from '../../messages/ar.json';
import adultsAr from '../../messages/adults/ar.json';
import { overlaid, type MessageTree } from '@/lib/audience';
import type { Conversation } from '@/lib/messages/types';
import { AudienceProvider } from './audience';
import { ChatThread } from './messages/chat-thread';
import { CheersCard } from './journey/parts';

/*
 * The screens in an academy for adults (docs/architecture/02, section 29):
 * the overlay's words (as i18n/request.ts lays them), no cheers from family,
 * and a conversation that names guardians only when the student has them.
 * A families academy reads its screens as before.
 */

// Server Actions are the server's; only what the screens draw is looked at here.
vi.mock('@/lib/actions/shared', () => ({ sendChatMessage: vi.fn() }));
vi.mock('@/app/[locale]/family/actions', () => ({ cheersSeen: vi.fn(), openChest: vi.fn() }));
vi.mock('@/app/[locale]/family/games/actions', () => ({ hatchEgg: vi.fn() }));
vi.mock('@/i18n/navigation', () => ({ Link: ({ children }: { children: React.ReactNode }) => children, usePathname: () => '/family', useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }) }));

const ADULTS = overlaid(ar as MessageTree, adultsAr as MessageTree);

function draw(node: React.ReactNode, adults: boolean): string {
  return renderToStaticMarkup(
    <NextIntlClientProvider locale="ar" messages={adults ? ADULTS : ar} timeZone="UTC">
      <AudienceProvider audience={adults ? 'adults' : 'families'}>{node}</AudienceProvider>
    </NextIntlClientProvider>,
  );
}

const member = (id: string, side: 'teacher' | 'student' | 'guardian', me = false) => ({ id, name: `Name ${id}`, side, label: side, me });
const conversation = (members: Conversation['members']): Conversation => ({
  id: 'c1', with: { id: 's1', name: 'Name s1' }, members, messages: [], can_write: true, why_not: null, notice: 'n',
});

describe('an academy for adults on screen', () => {
  it('says a conversation is read by the academy alone when the trainee has no guardians', () => {
    const html = draw(<ChatThread conversation={conversation([member('t1', 'teacher', true), member('s1', 'student')])} />, true);
    expect(html).toContain(adultsAr.chat.first);
    expect(html).not.toContain(ar.chat.first);
    expect(html).not.toContain('معك:');
  });

  it('keeps a families conversation as it was: the guardians read along', () => {
    const html = draw(<ChatThread conversation={conversation([member('t1', 'teacher', true), member('s1', 'student'), member('g1', 'guardian')])} />, false);
    expect(html).toContain(ar.chat.first);
    expect(html).toContain('Name g1');
  });

  it('on a families academy says who reads a conversation with no guardian too', () => {
    const html = draw(<ChatThread conversation={conversation([member('t1', 'teacher', true), member('s1', 'student')])} />, false);
    expect(html).toContain(ar.chat.firstNoGuardians);
  });

  it('shows no cheers from family', () => {
    const cheers = [{ id: 'x', kind: 'clap', from: 'Mum', at: null }] as never;
    expect(draw(<CheersCard cheers={cheers} />, true)).toBe('');
    expect(draw(<CheersCard cheers={cheers} />, false)).not.toBe('');
  });

  it('speaks of sessions and trainers in the overlay, and leaves families their words', () => {
    expect(ADULTS.family).toMatchObject({ nextLesson: 'الجلسة القادمة' });
    expect((ar as MessageTree).family).toMatchObject({ nextLesson: 'الحصة القادمة' });
    expect(ADULTS.start).toMatchObject({ closed: { familyOnly: adultsAr.start.closed.familyOnly } });
  });
});
