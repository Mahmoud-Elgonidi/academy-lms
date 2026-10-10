'use client';

import { useTranslations } from 'next-intl';
import { useEffect, useRef, useState, useTransition } from 'react';
import { Icon } from '@/components/icons';
import { Button, Card, Notice, join } from '@/components/ui';
import { sendChatMessage } from '@/lib/actions/shared';
import { MAX_LENGTH, type Conversation } from '@/lib/messages/types';

/**
 * One conversation: who is in it (the guardians always), the messages (mine
 * on one side, a guardian's tinted), what was hidden from each, and writing
 * (Enter sends, Shift+Enter a new line). The newest stays in view as the
 * page refreshes with each one.
 */
export function ChatThread({ conversation }: { conversation: Conversation }) {
  const t = useTranslations('chat');
  const common = useTranslations('common');
  const [message, setMessage] = useState<{ tone: 'danger' | 'attention'; text: string }>();
  const [pending, start] = useTransition();
  const form = useRef<HTMLFormElement>(null);
  const list = useRef<HTMLOListElement>(null);
  const key = useRef<string | null>(null);
  const last = conversation.messages.at(-1)?.id;

  useEffect(() => {
    if (list.current) list.current.scrollTop = list.current.scrollHeight;
  }, [last]);

  const others = conversation.members.filter((m) => m.id !== conversation.with.id && !m.me);
  const write = (data: FormData) => {
    const body = String(data.get('body') ?? '').trim();
    if (!body) return;
    key.current ??= crypto.randomUUID();
    start(async () => {
      setMessage(undefined);
      const done = await sendChatMessage(conversation.id, body, key.current!);
      key.current = null;
      if (!done.ok) {
        setMessage({ tone: 'danger', text: done.message ?? common('problem') });
        return;
      }
      form.current?.reset();
      if (done.hidden) setMessage({ tone: 'attention', text: done.hidden });
    });
  };

  return (
    <Card className="space-y-0 p-0!">
      <header className="border-b border-line px-4 py-3">
        <h1 className="font-heading text-lg font-bold">{conversation.with.name}</h1>
        <p className="text-xs text-muted">{t('inIt', { names: others.map((m) => `${m.name} (${m.label})`).join('، ') })}</p>
      </header>
      <p className="flex items-start gap-2 bg-brand-soft px-4 py-2 text-xs leading-6 text-brand-strong">
        <Icon name="eyeOff" size={16} className="mt-1 shrink-0" />
        {conversation.notice}
      </p>

      <ol ref={list} className="max-h-[60dvh] space-y-3 overflow-y-auto px-4 py-4" aria-label={t('conversation')}>
        {conversation.messages.length === 0 ? <li className="py-8 text-center text-sm text-muted">{t('first')}</li> : null}
        {conversation.messages.map((m) => (
          <li key={m.id} className={join('flex flex-col gap-1', m.mine ? 'items-end' : 'items-start')}>
            <div
              className={join(
                'max-w-[88%] rounded-card px-4 py-2.5 text-[15px] leading-7 whitespace-pre-line break-words sm:max-w-[75%]',
                m.mine ? 'bg-brand text-white' : m.side === 'guardian' ? 'bg-attention-soft' : 'bg-paper',
              )}
            >
              {m.body}
            </div>
            {m.hidden ? (
              <span className="flex items-center gap-1 text-xs text-muted">
                <Icon name="eyeOff" size={14} />
                {m.hidden}
              </span>
            ) : null}
            <span className="text-xs text-muted">
              {m.mine ? t('you') : `${m.author} · ${m.side_label}`} · {m.at?.label}
            </span>
          </li>
        ))}
      </ol>

      {message ? (
        <div className="px-4 pb-2">
          <Notice tone={message.tone}>{message.text}</Notice>
        </div>
      ) : null}

      {conversation.can_write ? (
        <form ref={form} action={write} className="flex items-end gap-2 border-t border-line p-3">
          <textarea
            name="body"
            rows={1}
            required
            maxLength={MAX_LENGTH}
            aria-label={t('write')}
            placeholder={t('write')}
            className="min-h-11 flex-1 resize-none rounded-[1.4rem] border border-line bg-white px-4 py-2.5 text-[15px] [field-sizing:content] focus:outline-2 focus:outline-brand"
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                e.preventDefault();
                form.current?.requestSubmit();
              }
            }}
          />
          <Button type="submit" disabled={pending} aria-label={t('send')}>
            <Icon name="send" size={18} className="rtl:-scale-x-100" />
            <span className="max-sm:sr-only">{t('send')}</span>
          </Button>
        </form>
      ) : (
        <p className="border-t border-line bg-paper px-4 py-3 text-sm text-muted">{conversation.why_not}</p>
      )}
    </Card>
  );
}
