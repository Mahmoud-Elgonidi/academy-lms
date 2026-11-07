import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Empty, Heading, Notice, join } from '@/components/ui';
import { AreaOff } from '@/components/area-off';
import { areaGet } from '@/lib/modules-server';
import type { Conversation, ConversationRow } from '@/lib/messages/types';
import { ChatThread } from './chat-thread';
import { StartChat } from './start-chat';

type Base = '/family/messages' | '/teach/messages';

/** The portal's home a calm "off" leads back to. */
function homeOf(base: Base): string {
  return base.startsWith('/teach') ? '/teach' : '/family';
}

/**
 * A teacher's or a family's conversations: a teacher's students, or the
 * learner's teachers, the newest first, with what is new since they last
 * read. One not opened yet starts on a tap. Live through LiveRefresh
 * (messages.changed). While the academy has messages off, a calm word.
 */
export async function ChatIndex({ base, title, learnerId }: { base: Base; title: string; learnerId?: string }) {
  const query = learnerId ? { learner_id: learnerId } : undefined;
  const [t, found] = await Promise.all([
    getTranslations('chat'),
    areaGet<{ data: ConversationRow[]; meta: { notice: string } }>('/v1/messages/conversations', { query }),
  ]);
  if (found.off) return <AreaOff title={title} by={found.by} icon="chat" home={homeOf(base)} />;
  const list = found.data;
  return (
    <div>
      <Heading title={title} />
      <div className="space-y-4">
        <Notice>{list.meta.notice}</Notice>
        {list.data.length === 0 ? (
          <Empty>{t(learnerId ? 'emptyFamily' : 'emptyTeacher')}</Empty>
        ) : (
          <ul className="space-y-2">
            {list.data.map((row) => {
              const face = (
                <>
                  <span aria-hidden="true" className="grid size-11 shrink-0 place-items-center rounded-full bg-brand-soft font-heading font-bold text-brand-strong">
                    {row.with.name.trim().charAt(0)}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-semibold">{row.with.name}</span>
                    <span className="block truncate text-sm text-muted">
                      {row.last_message ? `${row.last_message.mine ? `${t('you')}: ` : ''}${row.last_message.body}` : t('start')}
                    </span>
                  </span>
                  <span className="flex shrink-0 flex-col items-end gap-1 text-xs text-muted">
                    {row.last_message?.at ? <span>{row.last_message.at.label}</span> : null}
                    {row.unread > 0 ? (
                      <span className="min-w-6 rounded-full bg-brand px-1.5 text-center font-bold leading-5 text-white tabular-nums">{row.unread}</span>
                    ) : !row.can_write ? (
                      <Icon name="lock" size={16} />
                    ) : null}
                  </span>
                </>
              );
              const look = 'flex w-full items-center gap-3 rounded-card border border-line bg-white p-3 text-start transition-colors hover:border-brand-line';
              return (
                <li key={row.with.id}>
                  {row.id ? (
                    <Link href={`${base}/${row.id}`} className={look}>
                      {face}
                    </Link>
                  ) : (
                    <StartChat base={base} withId={row.with.id} learnerId={learnerId} className={join(look, 'cursor-pointer')}>
                      {face}
                    </StartChat>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}

/** One conversation, with the way back to the list. */
export async function ChatConversation({ base, id }: { base: Base; id: string }) {
  const [t, found] = await Promise.all([
    getTranslations('chat'),
    areaGet<{ data: Conversation }>('/v1/messages/conversations/{conversation}', { params: { conversation: id } }),
  ]);
  if (found.off) return <AreaOff title={t('teacherTitle')} by={found.by} icon="chat" home={homeOf(base)} />;
  const conversation = found.data;
  return (
    <div>
      <Link href={base} className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {t('back')}
      </Link>
      <ChatThread conversation={conversation.data} />
    </div>
  );
}
