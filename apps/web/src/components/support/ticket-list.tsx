import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Empty } from '@/components/ui';
import type { TicketSummary } from '@/lib/support/types';
import { STATUS_TONE } from './status';

/** The person's own tickets, the active ones first; each opens its conversation. */
export async function TicketList({ tickets, base }: { tickets: TicketSummary[]; base: '/family/help' | '/teach/help' }) {
  const t = await getTranslations('support');
  if (tickets.length === 0) return <Empty>{t('empty')}</Empty>;
  return (
    <ul className="space-y-2">
      {tickets.map((ticket) => (
        <li key={ticket.id}>
          <Link
            href={`${base}/${ticket.id}`}
            className="flex items-center gap-3 rounded-card border border-line bg-white p-4 transition-colors hover:border-brand-line"
          >
            <div className="min-w-0 flex-1 space-y-1">
              <p className="text-xs text-muted">
                {ticket.reference} · {ticket.kind.label}
                {ticket.learner ? ` · ${t('about', { name: ticket.learner.name })}` : ''}
              </p>
              <p className="font-semibold break-words">{ticket.subject}</p>
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone={STATUS_TONE[ticket.status.key]}>{ticket.status.label}</Badge>
                {ticket.your_turn ? <span className="text-xs font-bold text-brand-strong">{t('yourTurn')}</span> : null}
                {ticket.last_message_at ? <span className="text-xs text-muted">{ticket.last_message_at.label}</span> : null}
              </div>
            </div>
            <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
          </Link>
        </li>
      ))}
    </ul>
  );
}
