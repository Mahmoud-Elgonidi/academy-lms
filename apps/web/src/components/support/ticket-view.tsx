'use client';

import { useTranslations } from 'next-intl';
import { useRef, useState, useTransition } from 'react';
import { closeTicket, reopenTicket, replyToTicket } from '@/lib/actions/shared';
import { Icon } from '@/components/icons';
import { Badge, Button, Card, Notice, Textarea, join } from '@/components/ui';
import { fileHref, type Ticket } from '@/lib/support/types';
import { STATUS_TONE } from './status';

const MAX_BYTES = 10 * 1024 * 1024;

/**
 * One of the person's tickets: the conversation both ways (their own lines on
 * one side, the academy's on the other, what was done in between), an answer
 * with words or a picture, "it is not solved" within the week, and closing it.
 */
export function TicketView({ ticket }: { ticket: Ticket }) {
  const t = useTranslations('support');
  const common = useTranslations('common');
  const [file, setFile] = useState<File | null>(null);
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();
  const form = useRef<HTMLFormElement>(null);
  const key = useRef<string | null>(null);

  const reply = (data: FormData) => {
    if (file && file.size > MAX_BYTES) {
      setMessage(t('tooBig'));
      return;
    }
    if (file) data.set('file', file, file.name);
    key.current ??= crypto.randomUUID();
    start(async () => {
      setMessage(undefined);
      const done = await replyToTicket(ticket.id, data, key.current!);
      key.current = null;
      if (!done.ok) {
        setMessage(done.message ?? common('problem'));
        return;
      }
      form.current?.reset();
      setFile(null);
    });
  };
  const act = (run: () => Promise<{ ok: boolean; message?: string }>, confirm?: string) => {
    if (confirm && !window.confirm(confirm)) return;
    start(async () => {
      const done = await run();
      setMessage(done.ok ? undefined : (done.message ?? common('problem')));
    });
  };

  return (
    <Card className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-xs text-muted">
            {ticket.reference} · {ticket.kind.label}
            {ticket.learner ? ` · ${t('about', { name: ticket.learner.name })}` : ''}
          </p>
          <h2 className="font-heading text-lg font-bold break-words">{ticket.subject}</h2>
          {ticket.lesson ? (
            <p className="text-sm text-muted">{[ticket.lesson.subject, ticket.lesson.starts_at?.label, ticket.lesson.with].filter(Boolean).join(' · ')}</p>
          ) : null}
        </div>
        <Badge tone={STATUS_TONE[ticket.status.key]}>{ticket.status.label}</Badge>
      </div>

      <ol className="space-y-3" aria-label={t('conversation')}>
        {ticket.messages.map((m) =>
          m.kind === 'event' ? (
            <li key={m.id} className="text-center text-xs text-muted">
              <span className="inline-block rounded-full border border-line bg-paper px-3 py-1">
                {m.event} · {m.at?.label}
              </span>
            </li>
          ) : (
            <li key={m.id} className={join('flex flex-col gap-1', m.side === 'requester' ? 'items-end' : 'items-start')}>
              <div
                className={join(
                  'max-w-[92%] rounded-card px-4 py-3 text-[15px] leading-7 whitespace-pre-line break-words sm:max-w-[80%]',
                  m.side === 'requester' ? 'bg-brand-soft' : 'bg-paper',
                )}
              >
                {m.body}
                {m.file ? (
                  m.file.mime.startsWith('image/') && m.file.mime !== 'image/heic' ? (
                    <a href={fileHref(ticket.id, m.id)} target="_blank" rel="noopener" className="mt-2 block">
                      {/* eslint-disable-next-line @next/next/no-img-element -- the picture comes through the BFF with the person's token */}
                      <img src={fileHref(ticket.id, m.id)} alt={m.file.name} className="max-h-56 max-w-full rounded-control border border-line" loading="lazy" />
                    </a>
                  ) : (
                    <a href={fileHref(ticket.id, m.id)} target="_blank" rel="noopener" className="mt-2 flex items-center gap-1 text-sm font-semibold text-brand-strong underline">
                      <Icon name="clip" size={16} />
                      {m.file.name}
                    </a>
                  )
                ) : null}
              </div>
              <span className="text-xs text-muted">
                {m.side === 'requester' ? (m.author ?? t('you')) : (m.author ?? t('academy'))} · {m.at?.label}
              </span>
            </li>
          ),
        )}
      </ol>

      {ticket.can_reopen ? (
        <div className="space-y-2 rounded-control bg-paper p-3 text-sm">
          <p>{t('reopenHint', { date: ticket.reopen_until?.label ?? '' })}</p>
          <Button type="button" variant="quiet" disabled={pending} onClick={() => act(() => reopenTicket(ticket.id))}>
            {t('reopen')}
          </Button>
        </div>
      ) : null}

      {message ? <Notice tone="danger">{message}</Notice> : null}

      {ticket.can_reply ? (
        <form ref={form} action={reply} className="space-y-3 border-t border-line pt-4">
          <Textarea name="body" rows={3} maxLength={5000} placeholder={t('replyPlaceholder')} aria-label={t('reply')} />
          <div className="flex flex-wrap items-center justify-between gap-2">
            <label className="flex cursor-pointer items-center gap-2 text-sm text-muted">
              <Icon name="clip" size={18} />
              <span className="max-w-56 truncate">{file ? file.name : t('attach')}</span>
              <input type="file" accept="image/*,application/pdf" className="sr-only" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
            </label>
            <Button type="submit" disabled={pending}>
              <Icon name="send" size={18} className="rtl:-scale-x-100" />
              {t('send')}
            </Button>
          </div>
        </form>
      ) : (
        <p className="text-sm text-muted">{t('closedNote')}</p>
      )}

      {ticket.can_close ? (
        <button type="button" className="text-sm text-muted underline" disabled={pending} onClick={() => act(() => closeTicket(ticket.id), t('closeHint'))}>
          {t('close')}
        </button>
      ) : null}
    </Card>
  );
}
