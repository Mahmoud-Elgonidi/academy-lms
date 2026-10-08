import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Empty, Heading } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { NewTicketOptions, Ticket, TicketSummary } from '@/lib/support/types';
import { NewTicket } from './new-ticket';
import { TicketList } from './ticket-list';
import { TicketView } from './ticket-view';

type Base = '/family/help' | '/teach/help';

/**
 * A family's or a teacher's tickets: writing a new one (what it can be about
 * comes from the API, by who is asking), and the list. A family's are about
 * the learner on screen. Live through LiveRefresh (tickets.changed).
 */
export async function HelpIndex({ base, side, learnerId }: { base: Base; side: 'family' | 'teacher'; learnerId?: string }) {
  const query = learnerId ? { learner_id: learnerId } : undefined;
  const [t, options, tickets] = await Promise.all([
    getTranslations('support'),
    get<{ data: NewTicketOptions }>('/v1/support/new', { query }),
    get<{ data: TicketSummary[] }>('/v1/support/tickets', { query }),
  ]);
  const canWrite = options.data.kinds.length > 0;
  return (
    <div>
      <Heading title={t(side === 'teacher' ? 'teacherTitle' : 'title')} hint={t(side === 'teacher' ? 'teacherLead' : 'lead')} />
      <div className="space-y-5">
        {canWrite ? <NewTicket options={options.data} base={base} side={side} /> : <Empty>{t('noneKids')}</Empty>}
        <section>
          <h2 className="mb-2 font-heading font-semibold">{t('list')}</h2>
          <TicketList tickets={tickets.data} base={base} />
        </section>
      </div>
    </div>
  );
}

/** One ticket, with the way back to the list. */
export async function HelpTicket({ base, id }: { base: Base; id: string }) {
  const [t, ticket] = await Promise.all([getTranslations('support'), get<{ data: Ticket }>('/v1/support/tickets/{ticket}', { params: { ticket: id } })]);
  return (
    <div>
      <Link href={base} className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {t('back')}
      </Link>
      <TicketView ticket={ticket.data} />
    </div>
  );
}
